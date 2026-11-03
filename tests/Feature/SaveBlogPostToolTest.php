<?php

namespace Tests\Feature;

use App\Models\McpCall;
use App\Models\User;
use App\Services\Mcp\PublicMcpCache;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Canvas\Enums\Role;
use Canvas\Models\CanvasUser;
use Canvas\Models\Post;
use Canvas\Models\Tag;
use Canvas\Models\Topic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The one write tool on the public MCP endpoint, over its real HTTP
 * transport: who sees it, what it writes, and that a post saved through it
 * behaves like one saved in the blog editor.
 */
class SaveBlogPostToolTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('mcp');
        PublicMcpCache::flush(PublicMcpCache::GROUP_BLOG_POSTS);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    private function rpc(string $method, array $params = [], array $headers = []): TestResponse
    {
        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], array_merge(['Accept' => 'application/json'], $headers));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $headers
     */
    private function save(array $arguments, array $headers): TestResponse
    {
        return $this->rpc('tools/call', ['name' => 'save-blog-post', 'arguments' => $arguments], $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function structured(TestResponse $response): array
    {
        $response->assertOk();
        $this->assertNotTrue($response->json('result.isError'), (string) json_encode($response->json()));

        return $response->json('result.structuredContent');
    }

    private function assertToolError(TestResponse $response, string $message): void
    {
        $response->assertOk();
        $this->assertTrue($response->json('result.isError'), 'Expected the tool to report an error.');
        $this->assertStringContainsString($message, (string) json_encode($response->json('result.content')));
    }

    /**
     * @return array<string, string>
     */
    private function tokenFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mcp-test')->plainTextToken];
    }

    /**
     * Drop the token holder an earlier MCP request resolved. The test shares
     * one application across requests, so `Auth::shouldUse('sanctum')` would
     * otherwise make the blog treat the next page view as an author preview.
     */
    private function asVisitor(): static
    {
        Auth::shouldUse('web');
        Auth::forgetGuards();

        return $this;
    }

    private function author(?Role $canvasRole = Role::Admin): User
    {
        Permission::firstOrCreate(['name' => 'manage-blog']);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-blog');

        if ($canvasRole !== null) {
            CanvasUser::query()->create([
                'user_id' => $user->id,
                'role' => $canvasRole,
                'username' => 'author-'.Str::random(6),
            ]);
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function listedTools(array $headers = []): array
    {
        return collect($this->rpc('tools/list', [], $headers)->assertOk()->json('result.tools'))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    private function existingPost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Existing title',
            'slug' => 'existing-'.Str::random(8),
            'summary' => 'Existing summary',
            'body' => '<p>Existing body</p>',
            'published_at' => null,
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    private function makeTaxon(string $model, string $table, string $slug): Topic|Tag
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert([
            'id' => $id,
            'name' => Str::headline($slug),
            'slug' => $slug,
            'user_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $model::query()->findOrFail($id);
    }

    private function postCount(): int
    {
        return DB::table('canvas_posts')->count();
    }

    public function test_a_manage_blog_token_holder_sees_the_tool(): void
    {
        $this->assertSame([
            'get-recent-blog-posts',
            'get-resume-data',
            'get-site-info',
            'save-blog-post',
        ], $this->listedTools($this->tokenFor($this->author())));
    }

    public function test_an_anonymous_caller_does_not_see_the_tool(): void
    {
        $this->assertNotContains('save-blog-post', $this->listedTools());
    }

    public function test_a_token_holder_without_manage_blog_does_not_see_the_tool(): void
    {
        $this->assertNotContains('save-blog-post', $this->listedTools($this->tokenFor(User::factory()->create())));
    }

    public function test_a_manage_blog_holder_without_a_canvas_account_does_not_see_the_tool(): void
    {
        $this->assertNotContains('save-blog-post', $this->listedTools($this->tokenFor($this->author(null))));
    }

    public function test_invocation_by_name_is_refused_without_the_permission(): void
    {
        $before = $this->postCount();

        foreach ([[], $this->tokenFor(User::factory()->create()), $this->tokenFor($this->author(null))] as $headers) {
            $response = $this->save(['title' => 'Sneaky', 'body' => '<p>Nope</p>'], $headers);

            $this->assertTrue(
                $response->json('error') !== null || $response->json('result.isError') === true,
                'Expected an unauthorized call to fail.'
            );
        }

        $this->assertSame($before, $this->postCount());
    }

    public function test_a_draft_is_created_by_default(): void
    {
        $user = $this->author();

        $result = $this->structured($this->save([
            'title' => 'My draft',
            'body' => '<p>Draft body</p>',
            'summary' => 'Draft summary',
        ], $this->tokenFor($user)));

        $this->assertSame('draft', $result['status']);
        $this->assertNull($result['published_at']);

        $row = DB::table('canvas_posts')->where('id', $result['id'])->first();

        $this->assertSame('My draft', $row->title);
        $this->assertSame('<p>Draft body</p>', $row->body);
        $this->assertNull($row->published_at);
        $this->assertSame($user->id, $row->user_id);

        $this->asVisitor()->get(route('post', $result['slug']))->assertNotFound();
    }

    public function test_the_slug_follows_the_title_under_the_editors_rules(): void
    {
        $headers = $this->tokenFor($this->author());

        $result = $this->structured($this->save(['title' => "  Jason's C++ Tips: Part 1!  "], $headers));

        $this->assertSame('jason-s-c-tips-part-1', $result['slug']);
    }

    public function test_a_colliding_slug_gets_the_editors_suffix(): void
    {
        $headers = $this->tokenFor($this->author());
        $this->existingPost(['slug' => 'taken-slug']);

        $result = $this->structured($this->save(['title' => 'Taken slug'], $headers));

        $this->assertSame('taken-slug-2', $result['slug']);
    }

    public function test_an_untitled_draft_gets_the_editors_placeholder_slug(): void
    {
        $result = $this->structured($this->save(['body' => '<p>Notes</p>'], $this->tokenFor($this->author())));

        $this->assertSame('post-'.$result['id'], $result['slug']);
    }

    public function test_an_update_changes_only_the_supplied_fields(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'topic-'.Str::random(6));
        $tag = $this->makeTaxon(Tag::class, 'canvas_tags', 'tag-'.Str::random(6));
        $post = $this->existingPost(['slug' => 'hand-set-slug', 'topic_id' => $topic->id]);
        $post->tags()->sync([$tag->id]);

        $result = $this->structured($this->save([
            'id' => $post->id,
            'title' => 'A new title',
        ], $this->tokenFor($this->author())));

        $post->refresh()->load('tags');

        $this->assertSame('A new title', $post->title);
        $this->assertSame('<p>Existing body</p>', $post->body);
        $this->assertSame('Existing summary', $post->summary);
        $this->assertSame($topic->id, $post->topic_id);
        $this->assertSame([$tag->id], $post->tags->pluck('id')->all());
        $this->assertSame('hand-set-slug', $result['slug'], 'A hand-set slug must not follow the title.');
        $this->assertNull($post->published_at);
    }

    public function test_a_slug_that_followed_the_old_title_follows_the_new_one(): void
    {
        $headers = $this->tokenFor($this->author());

        $created = $this->structured($this->save(['title' => 'First title'], $headers));
        $updated = $this->structured($this->save(['id' => $created['id'], 'title' => 'Second title'], $headers));

        $this->assertSame('first-title', $created['slug']);
        $this->assertSame('second-title', $updated['slug']);
    }

    public function test_an_unknown_post_is_reported_as_not_found(): void
    {
        $this->assertToolError(
            $this->save(['id' => (string) Str::uuid(), 'title' => 'x'], $this->tokenFor($this->author())),
            'Post not found.'
        );
    }

    public function test_a_post_the_caller_may_not_update_is_reported_as_not_found(): void
    {
        $user = $this->author(Role::Contributor);
        $post = $this->existingPost();

        $this->assertToolError(
            $this->save(['id' => $post->id, 'title' => 'Hijacked'], $this->tokenFor($user)),
            'Post not found.'
        );

        $this->assertSame('Existing title', $post->refresh()->title);
    }

    public function test_a_post_is_published_immediately(): void
    {
        $result = $this->structured($this->save([
            'title' => 'Live now',
            'body' => '<p>Hello, readers</p>',
            'publish' => 'now',
        ], $this->tokenFor($this->author())));

        $this->assertSame('published', $result['status']);
        $this->asVisitor()->get(route('post', $result['slug']))->assertOk()->assertSee('Hello, readers');
    }

    public function test_publishing_refreshes_the_feed_and_the_public_recent_posts_tool(): void
    {
        $headers = $this->tokenFor($this->author());

        Cache::put('blog.feed', 'stale feed', now()->addDay());
        $listedBefore = $this->structured($this->rpc('tools/call', ['name' => 'get-recent-blog-posts', 'arguments' => []]));
        $this->assertNotContains('Fresh off the tool', array_column($listedBefore['posts'], 'title'));

        $this->structured($this->save(['title' => 'Fresh off the tool', 'publish' => 'now'], $headers));

        $this->assertFalse(Cache::has('blog.feed'), 'Publishing must flush the blog feed cache.');

        $listedAfter = $this->structured($this->rpc('tools/call', ['name' => 'get-recent-blog-posts', 'arguments' => []]));
        $this->assertContains('Fresh off the tool', array_column($listedAfter['posts'], 'title'));
    }

    public function test_a_post_is_scheduled(): void
    {
        $at = now()->addDays(3)->startOfMinute();

        $result = $this->structured($this->save([
            'title' => 'Coming soon',
            'publish' => 'schedule',
            'published_at' => $at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ], $this->tokenFor($this->author())));

        $this->assertSame('scheduled', $result['status']);
        $this->assertTrue($at->equalTo(Post::query()->findOrFail($result['id'])->published_at));
        $this->asVisitor()->get(route('post', $result['slug']))->assertNotFound();
    }

    public function test_invalid_schedule_times_are_rejected_and_change_nothing(): void
    {
        $headers = $this->tokenFor($this->author());
        $post = $this->existingPost();

        $cases = [
            'past' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'),
            'no timezone' => now()->addDay()->format('Y-m-d\TH:i:s'),
            'missing' => null,
        ];

        foreach ($cases as $case => $time) {
            $arguments = ['id' => $post->id, 'title' => 'Changed', 'publish' => 'schedule'];

            if ($time !== null) {
                $arguments['published_at'] = $time;
            }

            $this->assertToolError($this->save($arguments, $headers), 'published');

            $post->refresh();
            $this->assertSame('Existing title', $post->title, "A {$case} schedule time must change nothing.");
            $this->assertNull($post->published_at);
        }
    }

    public function test_a_publication_time_without_schedule_is_rejected(): void
    {
        $before = $this->postCount();

        $this->assertToolError($this->save([
            'title' => 'Sneaky date',
            'published_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z'),
        ], $this->tokenFor($this->author())), 'published');

        $this->assertSame($before, $this->postCount());
    }

    public function test_publishing_without_a_title_is_rejected(): void
    {
        $headers = $this->tokenFor($this->author());
        $post = $this->existingPost(['title' => null]);
        $before = $this->postCount();

        $this->assertToolError($this->save(['body' => '<p>Untitled</p>', 'publish' => 'now'], $headers), 'title is required');
        $this->assertToolError($this->save(['id' => $post->id, 'publish' => 'now'], $headers), 'title is required');

        $this->assertSame($before, $this->postCount());
        $this->assertNull($post->refresh()->published_at);
    }

    public function test_a_live_post_is_never_taken_offline(): void
    {
        $headers = $this->tokenFor($this->author());
        $post = $this->existingPost(['published_at' => now()->subWeek()->startOfSecond()]);
        $publishedAt = $post->published_at;

        foreach (['draft' => [], 'schedule' => ['published_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z')]] as $publish => $extra) {
            $this->assertToolError(
                $this->save(['id' => $post->id, 'publish' => $publish] + $extra, $headers),
                'not supported here'
            );
        }

        $this->assertTrue($publishedAt->equalTo($post->refresh()->published_at));
    }

    public function test_editing_a_live_post_keeps_it_live_with_its_original_date(): void
    {
        $headers = $this->tokenFor($this->author());
        $post = $this->existingPost(['published_at' => now()->subWeek()->startOfSecond()]);
        $publishedAt = $post->published_at;

        $result = $this->structured($this->save(['id' => $post->id, 'body' => '<p>Corrected</p>'], $headers));
        $this->structured($this->save(['id' => $post->id, 'publish' => 'now'], $headers));

        $this->assertSame('published', $result['status']);
        $post->refresh();
        $this->assertSame('<p>Corrected</p>', $post->body);
        $this->assertTrue($publishedAt->equalTo($post->published_at));
    }

    public function test_existing_topic_and_tags_are_attached(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'topic-'.Str::random(6));
        $tags = [
            $this->makeTaxon(Tag::class, 'canvas_tags', 'tag-a-'.Str::random(6)),
            $this->makeTaxon(Tag::class, 'canvas_tags', 'tag-b-'.Str::random(6)),
        ];

        $result = $this->structured($this->save([
            'title' => 'Organized',
            'topic' => $topic->slug,
            'tags' => [$tags[0]->slug, $tags[1]->slug],
        ], $this->tokenFor($this->author())));

        $post = Post::query()->with('tags')->findOrFail($result['id']);

        $this->assertSame($topic->id, $post->topic_id);
        $this->assertEqualsCanonicalizing([$tags[0]->id, $tags[1]->id], $post->tags->pluck('id')->all());
        $this->assertSame(['topics' => [], 'tags' => []], $result['ignored']);
    }

    public function test_unknown_topic_and_tag_slugs_are_reported_not_created(): void
    {
        $tag = $this->makeTaxon(Tag::class, 'canvas_tags', 'tag-'.Str::random(6));
        $topics = DB::table('canvas_topics')->count();
        $tagCount = DB::table('canvas_tags')->count();

        $result = $this->structured($this->save([
            'title' => 'Mislabelled',
            'topic' => 'no-such-topic',
            'tags' => [$tag->slug, 'no-such-tag'],
        ], $this->tokenFor($this->author())));

        $post = Post::query()->with('tags')->findOrFail($result['id']);

        $this->assertNull($post->topic_id);
        $this->assertSame([$tag->id], $post->tags->pluck('id')->all());
        $this->assertSame(['topics' => ['no-such-topic'], 'tags' => ['no-such-tag']], $result['ignored']);
        $this->assertSame($topics, DB::table('canvas_topics')->count());
        $this->assertSame($tagCount, DB::table('canvas_tags')->count());
    }

    public function test_a_repeated_write_is_executed_each_time(): void
    {
        $headers = $this->tokenFor($this->author());
        $before = $this->postCount();

        $first = $this->structured($this->save(['title' => 'Twice'], $headers));
        $second = $this->structured($this->save(['title' => 'Twice'], $headers));

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame($before + 2, $this->postCount());
    }

    public function test_the_call_record_omits_the_submitted_content(): void
    {
        McpCall::query()->delete();
        $user = $this->author();

        $this->structured($this->save([
            'title' => 'Secret working title',
            'body' => '<p>Unmistakable body text 7f3a</p>',
            'summary' => 'Private summary',
        ], $this->tokenFor($user)));

        $record = McpCall::query()->where('tool_name', 'save-blog-post')->firstOrFail();
        $serialized = (string) json_encode($record->toArray());

        $this->assertSame($user->id, $record->user_id);
        $this->assertStringNotContainsString('Unmistakable body text', $serialized);
        $this->assertStringNotContainsString('Secret working title', $serialized);
        $this->assertStringNotContainsString('Private summary', $serialized);
    }

    public function test_an_invalid_token_is_rejected_and_writes_nothing(): void
    {
        $before = $this->postCount();

        $this->save(['title' => 'Nope'], ['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_token');

        $this->assertSame($before, $this->postCount());
    }

    public function test_a_revoked_token_cannot_write(): void
    {
        $user = $this->author();
        $headers = $this->tokenFor($user);
        $user->tokens()->delete();
        $before = $this->postCount();

        $this->save(['title' => 'Revoked'], $headers)->assertStatus(401);

        $this->assertSame($before, $this->postCount());
    }

    public function test_writes_have_their_own_tighter_throttle(): void
    {
        config(['mcp-server.limits.write.per_minute' => 2]);
        $headers = $this->tokenFor($this->author());

        $this->structured($this->save(['title' => 'One'], $headers));
        $this->structured($this->save(['title' => 'Two'], $headers));
        $this->save(['title' => 'Three'], $headers)->assertStatus(429);

        // Reads on the same token are not held back by the write ceiling.
        $this->rpc('tools/list', [], $headers)->assertOk();
    }
}
