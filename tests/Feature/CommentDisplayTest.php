<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private function userWith(string $permission): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => $permission]);
        $user->givePermissionTo($permission);

        return $user;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function moderatingPermissions(): array
    {
        return [
            'manage-comments only' => ['manage-comments'],
            'manage-blog only' => ['manage-blog'],
        ];
    }

    private function spamAction(Post $post, Comment $comment): string
    {
        return 'action="'.route('comments.spam', [$post->slug, $comment]).'"';
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

    private function admin(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $user->givePermissionTo('manage-blog');

        return $user;
    }

    /**
     * Build a reply chain from depth 0 to the given depth, keyed by depth.
     *
     * @return array<int, Comment>
     */
    private function chain(Post $post, int $deepest): array
    {
        $parent = null;
        $comments = [];

        for ($depth = 0; $depth <= $deepest; $depth++) {
            $parent = $this->comment($post, [
                'message' => "Body at depth {$depth}",
                'name' => "Author At Depth {$depth}",
                'depth' => $depth,
                'parent_id' => $parent?->id,
            ]);
            $comments[$depth] = $parent;
        }

        return $comments;
    }

    /**
     * The rendered markup of one comment's own list item, up to its first child list.
     */
    private function nodeHtml(string $html, Comment $comment): string
    {
        $start = strpos($html, 'id="comment-'.$comment->id.'"');
        $this->assertNotFalse($start, "Comment {$comment->id} is not rendered.");

        $next = strpos($html, '<li id="comment-', $start);

        return substr($html, $start, ($next === false ? strlen($html) : $next) - $start);
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

    public function test_approved_comments_render(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['message' => 'Visible comment body']);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Visible comment body')
            ->assertSee($comment->name);
    }

    public function test_a_soft_deleted_leaf_comment_is_absent_with_no_tombstone(): void
    {
        $post = $this->makePost();
        $this->comment($post, ['message' => 'Surviving body']);
        $comment = $this->comment($post, ['message' => 'Deleted body', 'name' => 'Zebedee Quux']);
        $comment->delete();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Surviving body')
            ->assertDontSee('Deleted body')
            ->assertDontSee('Zebedee Quux')
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

        $chain = [];

        for ($depth = 0; $depth <= 3; $depth++) {
            $parent = $this->comment($post, [
                'message' => "Body at depth {$depth}",
                'depth' => $depth,
                'parent_id' => $parent?->id,
            ]);
            $chain[$depth] = $parent;
        }

        $chain[1]->delete();
        $this->comment($post, ['message' => 'Deleted leaf'])->delete();

        DB::enableQueryLog();
        $this->actingAs($this->admin())
            ->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Body at depth 3');
        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $q): bool => str_contains($q, 'from `comments`') && ! str_contains($q, 'count(*)'));
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(1, $queries->count(), "Expected one comment tree query, got:\n".$queries->implode("\n"));
    }

    #[DataProvider('moderatingPermissions')]
    public function test_a_moderator_sees_a_mark_as_spam_control_on_every_displayed_comment(string $permission): void
    {
        $post = $this->makePost();
        $registered = $this->comment($post, ['user_id' => User::factory()->create()->id]);
        $anonymous = $this->comment($post, ['user_id' => null]);

        $html = $this->actingAs($this->userWith($permission))
            ->get(route('post', $post->slug))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $this->spamAction($post, $registered)));
        $this->assertSame(1, substr_count($html, $this->spamAction($post, $anonymous)));
        $this->assertSame(2, substr_count($html, '>Mark as spam</summary>'));
    }

    public function test_a_logged_in_non_moderator_sees_no_mark_as_spam_control(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $this->comment($post, ['user_id' => $author->id]);
        $this->comment($post);

        $this->actingAs($author)
            ->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Mark as spam')
            ->assertDontSee('/spam"', false);
    }

    public function test_a_guest_sees_no_mark_as_spam_control(): void
    {
        $post = $this->makePost();
        $this->comment($post);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Mark as spam')
            ->assertDontSee('/spam"', false);
    }

    public function test_a_tombstone_carries_no_mark_as_spam_control(): void
    {
        $post = $this->makePost();
        $hidden = $this->comment($post, ['approved_at' => null, 'is_spam' => true]);
        $reply = $this->comment($post, ['parent_id' => $hidden->id, 'depth' => 1]);

        $html = $this->actingAs($this->userWith('manage-comments'))
            ->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('[comment removed]')
            ->getContent();

        $this->assertSame(0, substr_count($html, $this->spamAction($post, $hidden)));
        $this->assertSame(1, substr_count($html, $this->spamAction($post, $reply)));
    }

    public function test_the_mark_as_spam_control_needs_a_second_step(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post);

        $html = $this->actingAs($this->userWith('manage-comments'))
            ->get(route('post', $post->slug))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '~<details[^>]*>\s*<summary[^>]*>Mark as spam</summary>\s*<form[^>]*'.preg_quote($this->spamAction($post, $comment), '~').'~',
            $html
        );
        $this->assertDoesNotMatchRegularExpression('~<details[^>]*\bopen\b[^>]*>\s*<summary[^>]*>Mark as spam~', $html);
    }

    public function test_a_confirmation_shows_after_marking_spam_from_the_post(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['message' => 'Soon to be hidden']);

        $this->actingAs($this->userWith('manage-comments'))
            ->followingRedirects()
            ->post(route('comments.spam', [$post->slug, $comment]))
            ->assertOk()
            ->assertSee('Comment marked as spam and hidden from this post.')
            ->assertDontSee('Soon to be hidden');
    }

    public function test_the_moderation_control_adds_no_query_per_comment(): void
    {
        $moderator = $this->userWith('manage-comments');

        $queriesFor = function (int $commentCount) use ($moderator): int {
            $post = $this->makePost();

            for ($i = 0; $i < $commentCount; $i++) {
                $this->comment($post);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($moderator->fresh())->get(route('post', $post->slug))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->assertSame($queriesFor(3), $queriesFor(30));
    }

    public function test_a_deleted_comment_mid_thread_tombstones_and_keeps_descendants(): void
    {
        $post = $this->makePost();
        $root = $this->comment($post, ['message' => 'Root body', 'depth' => 0]);
        $deleted = $this->comment($post, [
            'message' => 'Deleted body',
            'name' => 'Zebedee Quux',
            'email' => 'zebedee@example.com',
            'depth' => 1,
            'parent_id' => $root->id,
        ]);
        $child = $this->comment($post, ['message' => 'Child body', 'depth' => 2, 'parent_id' => $deleted->id]);
        $grandchild = $this->comment($post, ['message' => 'Grandchild body', 'depth' => 3, 'parent_id' => $child->id]);
        $deleted->delete();

        $response = $this->get(route('post', $post->slug))->assertOk();

        $response->assertSee('[comment removed]');
        $response->assertDontSee('Deleted body');
        $response->assertDontSee('Zebedee Quux');
        $response->assertDontSee('zebedee@example.com');
        $response->assertSee('Child body');
        $response->assertSee('Grandchild body');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Child body'), strpos($html, 'id="comment-'.$deleted->id.'"'));
        $this->assertStringContainsString('[comment removed]', $this->nodeHtml($html, $deleted));

        $this->assertSame(2, $child->fresh()->depth);
        $this->assertSame(3, $grandchild->fresh()->depth);
        $this->assertSame($deleted->id, $child->fresh()->parent_id);
    }

    public function test_spam_and_deleted_tombstones_are_indistinguishable(): void
    {
        $post = $this->makePost();
        $spam = $this->comment($post, ['approved_at' => null, 'is_spam' => true]);
        $this->comment($post, ['message' => 'Reply to spam', 'depth' => 1, 'parent_id' => $spam->id]);
        $deleted = $this->comment($post);
        $this->comment($post, ['message' => 'Reply to deleted', 'depth' => 1, 'parent_id' => $deleted->id]);
        $deleted->delete();

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();

        $normalise = fn (Comment $comment): string => preg_replace(
            '/\s+/',
            ' ',
            str_replace((string) $comment->id, 'ID', strstr($this->nodeHtml($html, $comment), '<ol', true)),
        );

        $this->assertStringContainsString('[comment removed]', $normalise($spam));
        $this->assertSame($normalise($spam), $normalise($deleted));
    }

    public function test_a_tombstone_offers_no_controls_even_to_an_admin(): void
    {
        $post = $this->makePost();
        $spam = $this->comment($post, ['approved_at' => null, 'is_spam' => true]);
        $this->comment($post, ['depth' => 1, 'parent_id' => $spam->id]);
        $deleted = $this->comment($post);
        $this->comment($post, ['depth' => 1, 'parent_id' => $deleted->id]);
        $deleted->delete();

        $html = $this->actingAs($this->admin())->get(route('post', $post->slug))->assertOk()->getContent();

        foreach ([$spam, $deleted] as $tombstone) {
            $node = $this->nodeHtml($html, $tombstone);

            $this->assertStringContainsString('[comment removed]', $node);
            $this->assertStringNotContainsString('<summary', $node);
            $this->assertStringNotContainsString('<form', $node);
        }

        $this->assertSame(2, substr_count($html, '>Reply</summary>'));
        $this->assertSame(2, substr_count($html, '>Edit</summary>'));
        $this->assertSame(2, substr_count($html, '>Delete</summary>'));
    }

    public function test_a_hidden_comment_whose_replies_are_all_hidden_renders_nothing(): void
    {
        $post = $this->makePost();
        $this->comment($post, ['message' => 'Bystander body']);
        $deleted = $this->comment($post, ['message' => 'Deleted parent']);
        $deletedReply = $this->comment($post, ['message' => 'Deleted reply', 'depth' => 1, 'parent_id' => $deleted->id]);
        $this->comment($post, [
            'message' => 'Spam reply',
            'depth' => 1,
            'parent_id' => $deleted->id,
            'approved_at' => null,
            'is_spam' => true,
        ]);
        $deletedReply->delete();
        $deleted->delete();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Bystander body')
            ->assertDontSee('Deleted parent')
            ->assertDontSee('Deleted reply')
            ->assertDontSee('Spam reply')
            ->assertDontSee('[comment removed]')
            ->assertDontSee('id="comment-'.$deleted->id.'"', false);
    }

    public function test_deleted_comments_are_not_counted(): void
    {
        $post = $this->makePost();
        $this->comment($post);
        $this->comment($post);
        $this->comment($post)->delete();

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Comments\s*<span[^>]*>\(2\)<\/span>/', $html);
    }

    public function test_a_post_whose_only_comment_was_deleted_shows_the_empty_state(): void
    {
        $post = $this->makePost();
        $this->comment($post, ['message' => 'Gone body'])->delete();

        $html = $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('No comments yet')
            ->assertDontSee('Gone body')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/Comments\s*<span/', $html);
    }

    public function test_an_edited_comment_shows_the_marker(): void
    {
        $post = $this->makePost();
        $edited = Comment::factory()->edited()->create([
            'post_id' => $post->id,
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 0,
        ]);
        $untouched = $this->comment($post);

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();

        $this->assertStringContainsString('(edited)', $this->nodeHtml($html, $edited));
        $this->assertStringNotContainsString('(edited)', $this->nodeHtml($html, $untouched));
    }

    public function test_a_restored_comment_shows_no_edited_marker(): void
    {
        $post = $this->makePost();
        $comment = $this->comment($post, ['message' => 'Restored body']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.comments.spam', $comment));
        $this->actingAs($admin)->post(route('admin.comments.not-spam', $comment));

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Restored body')
            ->assertDontSee('(edited)');
    }

    public function test_an_author_sees_controls_on_their_own_comment_only(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $own = $this->comment($post, ['user_id' => $author->id]);
        $other = $this->comment($post);
        $claimed = $this->comment($post, ['user_id' => null, 'email' => $author->email]);

        $html = $this->actingAs($author)->get(route('post', $post->slug))->assertOk()->getContent();

        $ownNode = $this->nodeHtml($html, $own);
        $this->assertStringContainsString('>Edit</summary>', $ownNode);
        $this->assertStringContainsString('>Delete</summary>', $ownNode);
        $this->assertStringContainsString(route('comments.update', [$post->slug, $own]), $ownNode);
        $this->assertStringContainsString(route('comments.destroy', [$post->slug, $own]), $ownNode);
        $this->assertStringContainsString('Delete comment', $ownNode);

        foreach ([$other, $claimed] as $comment) {
            $node = $this->nodeHtml($html, $comment);
            $this->assertStringNotContainsString('>Edit</summary>', $node);
            $this->assertStringNotContainsString('>Delete</summary>', $node);
        }

        $this->assertSame(1, substr_count($html, '>Edit</summary>'));
        $this->assertSame(1, substr_count($html, '>Delete</summary>'));
    }

    public function test_a_guest_sees_no_controls(): void
    {
        $post = $this->makePost();
        $this->comment($post);
        $this->comment($post, ['user_id' => null]);

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '>Reply</summary>'));
        $this->assertStringNotContainsString('>Edit</summary>', $html);
        $this->assertStringNotContainsString('>Delete</summary>', $html);
        $this->assertStringNotContainsString('name="_method"', $html);
    }

    public function test_an_admin_sees_controls_on_every_displayed_comment(): void
    {
        $post = $this->makePost();
        $this->comment($post);
        $this->comment($post, ['user_id' => null]);

        $html = $this->actingAs($this->admin())->get(route('post', $post->slug))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, '>Edit</summary>'));
        $this->assertSame(2, substr_count($html, '>Delete</summary>'));
    }

    public function test_the_edit_field_is_pre_filled_with_the_current_message(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $author->id, 'message' => 'Fish & <chips>']);

        $html = $this->actingAs($author)->get(route('post', $post->slug))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*id="edit-message-'.$comment->id.'"[^>]*>Fish &amp; &lt;chips&gt;<\/textarea>/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*\sopen/', $html);
    }

    public function test_a_rejected_edit_is_reported_only_on_its_own_comment(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $target = $this->comment($post, ['user_id' => $author->id, 'message' => 'Target original']);
        $sibling = $this->comment($post, ['user_id' => $author->id, 'message' => 'Sibling original']);
        $rejected = str_repeat('z', 5001);

        $html = $this->actingAs($author)
            ->followingRedirects()
            ->put(route('comments.update', [$post->slug, $target]), [
                'editing' => $target->id,
                'message' => $rejected,
            ])
            ->assertOk()
            ->getContent();

        $error = 'Comments are limited to 5,000 characters.';
        $targetNode = $this->nodeHtml($html, $target);
        $siblingNode = $this->nodeHtml($html, $sibling);

        $this->assertSame(1, substr_count($html, $error));
        $this->assertSame(1, substr_count($html, $rejected));
        $this->assertStringContainsString($error, $targetNode);
        $this->assertStringContainsString($rejected, $targetNode);
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen/', $targetNode);

        $this->assertDoesNotMatchRegularExpression('/<details[^>]*\sopen/', $siblingNode);
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="edit-message-'.$sibling->id.'"[^>]*>Sibling original<\/textarea>/', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="message-root"[^>]*><\/textarea>/', $html);
        $this->assertSame('Target original', $target->fresh()->message);
    }

    public function test_update_and_delete_confirmations_are_shown(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $comment = $this->comment($post, ['user_id' => $author->id]);

        $this->actingAs($author)
            ->followingRedirects()
            ->put(route('comments.update', [$post->slug, $comment]), ['message' => 'Changed'])
            ->assertOk()
            ->assertSee('Your comment was updated.');

        $this->actingAs($author)
            ->followingRedirects()
            ->delete(route('comments.destroy', [$post->slug, $comment]))
            ->assertOk()
            ->assertSee('The comment was deleted.');
    }

    public function test_a_backlink_does_not_name_a_deleted_parent(): void
    {
        $post = $this->makePost();
        $chain = $this->chain($post, 4);
        $chain[3]->delete();

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();
        $node = $this->nodeHtml($html, $chain[4]);

        $this->assertStringNotContainsString('Author At Depth 3', $html);
        $this->assertStringContainsString('a removed comment', $node);
        $this->assertStringContainsString('href="#comment-'.$chain[3]->id.'"', $node);
        $this->assertStringContainsString('[comment removed]', $this->nodeHtml($html, $chain[3]));
    }

    public function test_a_backlink_does_not_name_a_spam_parent(): void
    {
        $post = $this->makePost();
        $chain = $this->chain($post, 4);
        $chain[3]->update(['is_spam' => true, 'approved_at' => null]);

        $html = $this->get(route('post', $post->slug))->assertOk()->getContent();
        $node = $this->nodeHtml($html, $chain[4]);

        $this->assertStringNotContainsString('Author At Depth 3', $html);
        $this->assertStringContainsString('a removed comment', $node);
        $this->assertStringContainsString('href="#comment-'.$chain[3]->id.'"', $node);
    }

    public function test_a_backlink_still_names_a_visible_parent(): void
    {
        $post = $this->makePost();
        $chain = $this->chain($post, 3);

        $node = $this->nodeHtml($this->get(route('post', $post->slug))->assertOk()->getContent(), $chain[3]);

        $this->assertStringContainsString('In reply to', $node);
        $this->assertStringContainsString('Author At Depth 2', $node);
        $this->assertStringNotContainsString('a removed comment', $node);
    }
}
