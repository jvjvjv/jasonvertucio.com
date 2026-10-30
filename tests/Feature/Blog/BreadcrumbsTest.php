<?php

namespace Tests\Feature\Blog;

use App\Models\User;
use Canvas\Enums\Role;
use Canvas\Models\CanvasUser;
use Canvas\Models\Post;
use Canvas\Models\Tag;
use Canvas\Models\Topic;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BreadcrumbsTest extends TestCase
{
    use DatabaseTransactions;

    private function makePost(string $title = 'A post', ?string $publishedAt = 'past'): Post
    {
        return Post::create([
            'id' => (string) Str::uuid(),
            'title' => $title,
            'slug' => 'breadcrumb-'.Str::random(8),
            'summary' => 'Summary',
            'body' => 'Body text',
            'published_at' => $publishedAt === 'past' ? now()->subHour() : null,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function makeTaxon(string $model, string $table, string $name): Topic|Tag
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert([
            'id' => $id,
            'name' => $name,
            'slug' => 'breadcrumb-taxon-'.Str::random(8),
            'user_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $model::query()->findOrFail($id);
    }

    private function blogAuthor(): User
    {
        $user = User::factory()->create();
        CanvasUser::query()->create([
            'user_id' => $user->id,
            'role' => Role::Admin,
            'username' => 'author-'.Str::random(6),
        ]);

        return $user;
    }

    private function trailXPath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * Reads the trail back as a reader meets it.
     *
     * @return list<array{label: string, href: ?string, current: bool}>
     */
    private function trail(TestResponse $response): array
    {
        $xpath = $this->trailXPath($response);
        $entries = [];

        foreach ($xpath->query('//nav[@aria-label="Breadcrumb"]/ol/li') as $item) {
            $link = $xpath->query('.//a', $item)->item(0);
            $entries[] = [
                'label' => trim($item->textContent),
                'href' => $link instanceof DOMElement ? $link->getAttribute('href') : null,
                'current' => $xpath->query('.//*[@aria-current="page"]', $item)->length === 1,
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function labels(TestResponse $response): array
    {
        return array_column($this->trail($response), 'label');
    }

    public function test_the_blog_index_trail(): void
    {
        $this->assertSame(['Home', 'Blog'], $this->labels($this->get(route('blog'))->assertOk()));
    }

    public function test_the_topics_index_trail(): void
    {
        $this->assertSame(['Home', 'Blog', 'Topics'], $this->labels($this->get(route('topics'))->assertOk()));
    }

    public function test_the_tags_index_trail(): void
    {
        $this->assertSame(['Home', 'Blog', 'Tags'], $this->labels($this->get(route('tags'))->assertOk()));
    }

    public function test_a_topic_listing_trail(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'Laravel');

        $this->assertSame(
            ['Home', 'Blog', 'Topics', 'Laravel'],
            $this->labels($this->get(route('topicList', $topic->slug))->assertOk())
        );
    }

    public function test_a_tag_listing_trail(): void
    {
        $tag = $this->makeTaxon(Tag::class, 'canvas_tags', 'Apple');

        $this->assertSame(
            ['Home', 'Blog', 'Tags', 'Apple'],
            $this->labels($this->get(route('tagList', $tag->slug))->assertOk())
        );
    }

    public function test_a_post_trail(): void
    {
        $post = $this->makePost('A post about crumbs');

        $this->assertSame(
            ['Home', 'Blog', 'A post about crumbs'],
            $this->labels($this->get(route('post', $post->slug))->assertOk())
        );
    }

    public function test_a_topic_at_the_short_address_keeps_its_full_trail(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'Laravel');

        $trail = $this->trail($this->get(route('post', $topic->slug))->assertOk());

        $this->assertSame(['Home', 'Blog', 'Topics', 'Laravel'], array_column($trail, 'label'));
        $this->assertSame(route('topics'), $trail[2]['href']);
    }

    public function test_a_tag_at_the_short_address_keeps_its_full_trail(): void
    {
        $tag = $this->makeTaxon(Tag::class, 'canvas_tags', 'Apple');

        $trail = $this->trail($this->get(route('post', $tag->slug))->assertOk());

        $this->assertSame(['Home', 'Blog', 'Tags', 'Apple'], array_column($trail, 'label'));
        $this->assertSame(route('tags'), $trail[2]['href']);
    }

    public function test_the_feed_has_no_trail(): void
    {
        $this->get(route('feed'))->assertOk()->assertDontSee('Breadcrumb');
    }

    public function test_the_blog_index_shows_the_trail_when_there_are_no_posts(): void
    {
        Post::query()->update(['published_at' => null]);

        $response = $this->get(route('blog'))->assertOk()->assertSee("There aren't any posts.", false);

        $this->assertSame(['Home', 'Blog'], $this->labels($response));
    }

    public function test_a_topic_with_no_published_posts_shows_its_trail(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'Empty');

        $response = $this->get(route('topicList', $topic->slug))->assertOk()->assertSee("There aren't any posts.", false);

        $this->assertSame(['Home', 'Blog', 'Topics', 'Empty'], $this->labels($response));
    }

    public function test_the_topics_and_tags_indexes_show_their_trails_when_empty(): void
    {
        Topic::query()->delete();
        Tag::query()->delete();

        $this->assertSame(['Home', 'Blog', 'Topics'], $this->labels($this->get(route('topics'))->assertOk()));
        $this->assertSame(['Home', 'Blog', 'Tags'], $this->labels($this->get(route('tags'))->assertOk()));
    }

    public function test_an_author_preview_shows_the_notice_and_the_trail(): void
    {
        $this->actingAs($this->blogAuthor());
        $post = $this->makePost('A draft', null);

        $response = $this->get(route('post', $post->slug))->assertOk()->assertSee('not published');

        $this->assertSame(['Home', 'Blog', 'A draft'], $this->labels($response));
    }

    public function test_an_unknown_address_has_no_trail(): void
    {
        $this->get(route('post', 'no-such-post-'.Str::random(8)))->assertNotFound()->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_ancestors_are_links_and_the_last_entry_is_the_current_page(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'Laravel');

        $this->assertSame([
            ['label' => 'Home', 'href' => route('home'), 'current' => false],
            ['label' => 'Blog', 'href' => route('blog'), 'current' => false],
            ['label' => 'Topics', 'href' => route('topics'), 'current' => false],
            ['label' => 'Laravel', 'href' => null, 'current' => true],
        ], $this->trail($this->get(route('topicList', $topic->slug))->assertOk()));
    }

    public function test_on_the_blog_index_only_home_is_a_link(): void
    {
        $this->assertSame([
            ['label' => 'Home', 'href' => route('home'), 'current' => false],
            ['label' => 'Blog', 'href' => null, 'current' => true],
        ], $this->trail($this->get(route('blog'))->assertOk()));
    }

    public function test_separators_are_hidden_from_assistive_technology(): void
    {
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', 'Laravel');

        $xpath = $this->trailXPath($this->get(route('topicList', $topic->slug))->assertOk());

        $this->assertSame(3, $xpath->query('//nav[@aria-label="Breadcrumb"]/ol/li//*[@aria-hidden="true"]')->length);
    }

    public function test_markup_in_a_title_is_shown_literally(): void
    {
        $title = '<script>alert(1)</script> & <b>friends</b>';
        $post = $this->makePost($title);

        $response = $this->get(route('post', $post->slug))->assertOk();
        $xpath = $this->trailXPath($response);

        $this->assertSame($title, $this->trail($response)[2]['label']);
        $this->assertSame(0, $xpath->query('//nav[@aria-label="Breadcrumb"]//script | //nav[@aria-label="Breadcrumb"]//b')->length);
    }

    public function test_markup_in_a_topic_name_is_shown_literally(): void
    {
        $name = '<img src=x onerror=alert(1)>';
        $topic = $this->makeTaxon(Topic::class, 'canvas_topics', $name);

        $response = $this->get(route('topicList', $topic->slug))->assertOk();

        $this->assertSame($name, $this->trail($response)[3]['label']);
        $this->assertSame(0, $this->trailXPath($response)->query('//nav[@aria-label="Breadcrumb"]//img')->length);
    }
}
