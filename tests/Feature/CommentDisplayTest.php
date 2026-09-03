<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentDisplayTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makePost(): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => 'A post',
            'slug' => 'a-post-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body',
            'published_at' => now(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function comment(Post $post, array $attributes = []): Comment
    {
        return Comment::factory()->create(array_merge([
            'post_id' => $post->id,
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 0,
        ], $attributes));
    }

    public function test_no_facebook_assets_are_rendered(): void
    {
        $post = $this->makePost();

        $response = $this->get(route('post', $post->slug))->assertOk();

        $response->assertDontSee('connect.facebook.net');
        $response->assertDontSee('fb-root');
        $response->assertDontSee('fb-comments');
        $response->assertDontSee('fb:app_id');
    }

    public function test_the_facebook_webhook_path_is_gone(): void
    {
        $this->get('/mlopnadjs22tn')->assertNotFound();

        $this->assertFalse(
            collect(app('router')->getRoutes()->getRoutes())
                ->contains(fn ($route): bool => $route->uri() === 'mlopnadjs22tn'),
            'The Facebook webhook route is still registered.'
        );
    }

    public function test_approved_comments_render(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['message' => 'Visible comment body']);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Visible comment body')
            ->assertSee($comment->name);
    }

    public function test_soft_deleted_comments_are_absent_with_no_tombstone(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['message' => 'Deleted body']);
        $comment->delete();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Deleted body')
            ->assertDontSee('[comment removed]');
    }

    public function test_a_post_with_no_comments_shows_an_empty_state(): void
    {
        $post = $this->makePost();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('No comments yet');
    }

    public function test_a_spam_comment_mid_thread_tombstones_and_keeps_descendants(): void
    {
        $post = $this->makePost();
        $root = $this->comment($post, ['message' => 'Root body', 'depth' => 0]);
        $spam = $this->comment($post, [
            'message' => 'Spam body',
            'depth' => 1,
            'parent_id' => $root->id,
            'approved_at' => null,
            'is_spam' => true,
        ]);
        $child = $this->comment($post, ['message' => 'Child body', 'depth' => 2, 'parent_id' => $spam->id]);
        $grandchild = $this->comment($post, ['message' => 'Grandchild body', 'depth' => 3, 'parent_id' => $child->id]);

        $response = $this->get(route('post', $post->slug))->assertOk();

        $response->assertSee('[comment removed]');
        $response->assertDontSee('Spam body');
        $response->assertDontSee($spam->name);
        $response->assertSee('Child body');
        $response->assertSee('Grandchild body');

        $this->assertSame(2, $child->fresh()->depth);
        $this->assertSame(3, $grandchild->fresh()->depth);
        $this->assertSame($spam->id, $child->fresh()->parent_id);
    }

    public function test_a_spam_leaf_comment_renders_nothing(): void
    {
        $post = $this->makePost();
        $this->comment($post, [
            'message' => 'Spam leaf',
            'approved_at' => null,
            'is_spam' => true,
        ]);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Spam leaf')
            ->assertDontSee('[comment removed]');
    }

    public function test_a_deep_comment_is_indented_like_a_depth_two_comment(): void
    {
        $post = $this->makePost();
        $parent = null;
        $comments = [];

        for ($depth = 0; $depth <= 4; $depth++) {
            $parent = $this->comment($post, [
                'message' => "Body at depth {$depth}",
                'depth' => $depth,
                'parent_id' => $parent?->id,
            ]);
            $comments[$depth] = $parent;
        }

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();

        $indentOf = function (Comment $comment) use ($html): string {
            preg_match('/id="comment-'.$comment->id.'" class="([^"]*)"/', $html, $matches);

            return $matches[1] ?? '';
        };

        $this->assertSame($indentOf($comments[2]), $indentOf($comments[4]));
        $this->assertNotSame($indentOf($comments[1]), $indentOf($comments[2]));
        $this->assertStringContainsString('In reply to', $html);
    }

    public function test_a_depth_five_comment_offers_no_reply_control(): void
    {
        $post = $this->makePost();
        $parent = null;

        for ($depth = 0; $depth <= 5; $depth++) {
            $parent = $this->comment($post, [
                'message' => "Body at depth {$depth}",
                'depth' => $depth,
                'parent_id' => $parent?->id,
            ]);
        }

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();

        $this->assertSame(5, substr_count($html, '>Reply</summary>'));
    }

    public function test_the_thread_costs_one_comment_query(): void
    {
        $post = $this->makePost();
        $parent = null;

        for ($depth = 0; $depth <= 3; $depth++) {
            $parent = $this->comment($post, [
                'message' => "Body at depth {$depth}",
                'depth' => $depth,
                'parent_id' => $parent?->id,
            ]);
        }

        DB::enableQueryLog();
        $this->get(route('post', $post->slug))->assertOk();
        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $q): bool => str_contains($q, 'from `comments`') && ! str_contains($q, 'count(*)'));
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(1, $queries->count(), "Expected one comment tree query, got:\n".$queries->implode("\n"));
    }
}
