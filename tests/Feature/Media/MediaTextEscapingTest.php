<?php

namespace Tests\Feature\Media;

use App\Models\LocalMedia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MediaTextEscapingTest extends TestCase
{
    use DatabaseTransactions;

    private const MARKUP = '<img src=x onerror=alert(1)>';

    private function playSong(string $title, string $album): void
    {
        LocalMedia::forceCreate([
            'jellyfin_item_id' => 'item-escape-test',
            'event_type' => 'PlaybackStart',
            'media_type' => 'Audio',
            'title' => $title,
            'artist_name' => 'Some Artist',
            'album_name' => $album,
            'last_playback_at' => now()->addMinute(),
            'play_count' => 1,
        ]);
        Cache::forget(LocalMedia::CURRENTLY_WATCHING_CACHE_KEY);
    }

    public function test_markup_in_album_and_title_is_displayed_as_text(): void
    {
        $this->playSong(self::MARKUP, self::MARKUP);

        $html = $this->blade('<x-currently-watching />')->__toString();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString(e(self::MARKUP), $html);
    }

    public function test_ordinary_album_keeps_its_emphasis(): void
    {
        $this->playSong('So What', 'Blue Train');

        $html = $this->blade('<x-currently-watching />')->__toString();

        $this->assertStringContainsString('From the Album <em>Blue Train</em>', $html);
    }

    public function test_view_has_no_unescaped_output(): void
    {
        $this->assertStringNotContainsString(
            '{!!',
            file_get_contents(resource_path('views/components/currently-watching.blade.php'))
        );
    }
}
