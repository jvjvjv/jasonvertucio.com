<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use App\Services\AdminNavigationService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Canvas\Models\Post;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommentModerationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function moderator(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $user->givePermissionTo('manage-blog');

        return $user;
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

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

    /**
     * @return list<string>
     */
    private function navigationHrefsFor(User $user): array
    {
        return collect(app(AdminNavigationService::class)->getAppBarItems($user))
            ->flatMap(fn (array $entry): array => array_column($entry['children'], 'href'))
            ->all();
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

    public function test_a_permitted_user_sees_the_queue(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'approved_at' => now(),
            'is_spam' => false,
        ]);

        $this->actingAs($this->moderator())
            ->get(route('admin.comments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('comments/Index', false)
                ->has('comments.data')
                ->where('comments.data.0.id', $comment->id)
                ->where('comments.data.0.is_spam', false)
            );
    }

    public function test_an_unpermitted_user_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.comments.index'))
            ->assertForbidden();
    }

    public function test_a_guest_is_refused(): void
    {
        $this->get(route('admin.comments.index'))->assertRedirect();
    }

    public function test_marking_spam_hides_the_comment_and_retains_the_row(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Spammy body',
            'approved_at' => now(),
            'is_spam' => false,
        ]);

        $this->actingAs($this->moderator())
            ->post(route('admin.comments.spam', $comment))
            ->assertRedirect();

        $comment->refresh();

        $this->assertTrue($comment->is_spam);
        $this->assertNull($comment->approved_at);
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Spammy body');
    }

    public function test_marking_not_spam_restores_with_a_fresh_timestamp(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Restored body',
            'approved_at' => null,
            'is_spam' => true,
        ]);

        $this->travelTo(now()->addMinutes(5));

        $this->actingAs($this->moderator())
            ->post(route('admin.comments.not-spam', $comment))
            ->assertRedirect();

        $comment->refresh();

        $this->assertFalse($comment->is_spam);
        $this->assertNotNull($comment->approved_at);
        $this->assertTrue($comment->approved_at->greaterThan($comment->created_at));

        $this->travelBack();

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertSee('Restored body');
    }

    public function test_no_path_produces_an_approved_spam_comment(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'approved_at' => now(),
            'is_spam' => false,
        ]);
        $moderator = $this->moderator();

        $this->actingAs($moderator)->post(route('admin.comments.spam', $comment));
        $this->actingAs($moderator)->post(route('admin.comments.not-spam', $comment));
        $this->actingAs($moderator)->post(route('admin.comments.spam', $comment));

        $this->assertSame(
            0,
            Comment::query()->whereNotNull('approved_at')->where('is_spam', true)->count()
        );
    }

    public function test_an_unpermitted_user_cannot_mark_spam(): void
    {
        $comment = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.comments.spam', $comment))
            ->assertForbidden();

        $this->assertFalse($comment->fresh()->is_spam);
    }

    #[DataProvider('moderatingPermissions')]
    public function test_either_permission_passes_the_moderation_gate(string $permission): void
    {
        $this->assertTrue($this->userWith($permission)->can('moderate-comments'));
    }

    public function test_a_user_holding_neither_permission_fails_the_moderation_gate(): void
    {
        Permission::firstOrCreate(['name' => 'manage-comments']);
        Permission::firstOrCreate(['name' => 'manage-blog']);

        $this->assertFalse($this->userWith('edit-resume')->can('moderate-comments'));
    }

    #[DataProvider('moderatingPermissions')]
    public function test_either_permission_opens_the_queue(string $permission): void
    {
        $this->actingAs($this->userWith($permission))
            ->get(route('admin.comments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('comments/Index', false)->has('comments.data'));
    }

    #[DataProvider('moderatingPermissions')]
    public function test_either_permission_marks_spam_and_restores_from_the_queue(string $permission): void
    {
        $comment = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);
        $moderator = $this->userWith($permission);

        $this->actingAs($moderator)->post(route('admin.comments.spam', $comment))->assertRedirect();

        $comment->refresh();
        $this->assertTrue($comment->is_spam);
        $this->assertNull($comment->approved_at);

        $this->actingAs($moderator)->post(route('admin.comments.not-spam', $comment))->assertRedirect();

        $comment->refresh();
        $this->assertFalse($comment->is_spam);
        $this->assertNotNull($comment->approved_at);
    }

    public function test_an_unpermitted_user_cannot_restore(): void
    {
        $comment = Comment::factory()->create(['approved_at' => null, 'is_spam' => true]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.comments.not-spam', $comment))
            ->assertForbidden();

        $this->assertTrue($comment->fresh()->is_spam);
    }

    public function test_a_guest_cannot_moderate_from_the_queue(): void
    {
        $approved = Comment::factory()->create(['approved_at' => now(), 'is_spam' => false]);
        $spam = Comment::factory()->create(['approved_at' => null, 'is_spam' => true]);

        $this->post(route('admin.comments.spam', $approved))->assertRedirect(route('login'));
        $this->post(route('admin.comments.not-spam', $spam))->assertRedirect(route('login'));

        $this->assertFalse($approved->fresh()->is_spam);
        $this->assertTrue($spam->fresh()->is_spam);
    }

    public function test_manage_comments_grants_nothing_beyond_moderation(): void
    {
        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers']);
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $user = $this->userWith('manage-comments');

        $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
        $this->assertFalse($user->can('manage-blog'));
    }

    #[DataProvider('moderatingPermissions')]
    public function test_the_navigation_offers_comments_to_either_permission(string $permission): void
    {
        $this->assertContains('/admin/comments', $this->navigationHrefsFor($this->userWith($permission)));
    }

    public function test_the_navigation_hides_comments_from_a_user_holding_neither_permission(): void
    {
        $this->assertNotContains('/admin/comments', $this->navigationHrefsFor($this->userWith('edit-resume')));
    }

    #[DataProvider('moderatingPermissions')]
    public function test_a_moderator_marks_spam_from_the_post_page(string $permission): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Spammy body',
            'approved_at' => now(),
            'is_spam' => false,
        ]);
        Mail::fake();

        $this->actingAs($this->userWith($permission))
            ->post(route('comments.spam', [$post->slug, $comment]))
            ->assertRedirect(route('post', $post->slug).'#comments')
            ->assertSessionHas('comment_marked_spam', true);

        $comment->refresh();

        $this->assertTrue($comment->is_spam);
        $this->assertNull($comment->approved_at);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'deleted_at' => null]);
        Mail::assertNothingOutgoing();
    }

    public function test_a_non_moderator_cannot_mark_their_own_comment_spam_from_the_post_page(): void
    {
        $post = $this->makePost();
        $author = User::factory()->create();
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'approved_at' => now(),
            'is_spam' => false,
        ]);

        $this->actingAs($author)
            ->post(route('comments.spam', [$post->slug, $comment]))
            ->assertForbidden();

        $this->assertFalse($comment->fresh()->is_spam);
    }

    public function test_a_guest_cannot_mark_spam_from_the_post_page(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create(['post_id' => $post->id, 'approved_at' => now(), 'is_spam' => false]);

        $this->post(route('comments.spam', [$post->slug, $comment]))->assertRedirect(route('login'));

        $this->assertFalse($comment->fresh()->is_spam);
    }

    public function test_marking_spam_through_another_posts_address_is_not_found(): void
    {
        $post = $this->makePost();
        $otherPost = $this->makePost();
        $comment = Comment::factory()->create(['post_id' => $post->id, 'approved_at' => now(), 'is_spam' => false]);

        $this->actingAs($this->userWith('manage-comments'))
            ->post(route('comments.spam', [$otherPost->slug, $comment]))
            ->assertNotFound();

        $this->assertFalse($comment->fresh()->is_spam);
    }

    public function test_marking_an_already_spam_comment_from_the_post_page_is_not_an_error(): void
    {
        $post = $this->makePost();
        $comment = Comment::factory()->create(['post_id' => $post->id, 'approved_at' => null, 'is_spam' => true]);

        $this->actingAs($this->userWith('manage-comments'))
            ->post(route('comments.spam', [$post->slug, $comment]))
            ->assertRedirect(route('post', $post->slug).'#comments')
            ->assertSessionHasNoErrors();

        $comment->refresh();

        $this->assertTrue($comment->is_spam);
        $this->assertNull($comment->approved_at);
    }

    public function test_replies_survive_a_comment_marked_spam_from_the_post_page(): void
    {
        $post = $this->makePost();
        $parent = Comment::factory()->create([
            'post_id' => $post->id,
            'message' => 'Parent body',
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 0,
        ]);
        $reply = Comment::factory()->create([
            'post_id' => $post->id,
            'parent_id' => $parent->id,
            'message' => 'Reply body',
            'approved_at' => now(),
            'is_spam' => false,
            'depth' => 1,
        ]);

        $this->actingAs($this->userWith('manage-comments'))
            ->post(route('comments.spam', [$post->slug, $parent]));

        $this->get(route('post', $post->slug))
            ->assertOk()
            ->assertDontSee('Parent body')
            ->assertSee('[comment removed]')
            ->assertSee('Reply body');

        $this->assertSame(1, $reply->fresh()->depth);
        $this->assertSame($parent->id, $reply->fresh()->parent_id);
    }
}
