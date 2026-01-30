<?php

namespace Database\Factories;

use App\Models\LocalMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocalMediaFactory extends Factory
{
    protected $model = LocalMedia::class;

    public function definition(): array
    {
        $mediaTypes = ['movie', 'series', 'episode', 'music', 'video'];
        $eventTypes = ['PlaybackStart', 'PlaybackProgress', 'PlaybackStop'];
        $mediaType = $this->faker->randomElement($mediaTypes);

        $data = [
            'jellyfin_item_id' => $this->faker->uuid(),
            'jellyfin_user_id' => $this->faker->uuid(),
            'event_type' => $this->faker->randomElement($eventTypes),
            'media_type' => $mediaType,
            'title' => $this->faker->sentence(3),
            'year' => $this->faker->year(),
            'provider_ids' => [],
            'playback_position' => $this->faker->numberBetween(0, 7200),
            'playback_duration' => $this->faker->numberBetween(3600, 14400),
            'is_paused' => $this->faker->boolean(),
            'last_playback_at' => now(),
            'play_count' => $this->faker->numberBetween(1, 10),
            'webhook_data' => [],
        ];

        // Add type-specific fields
        if ($mediaType === 'series') {
            $data['series_name'] = $this->faker->words(2, true);
            $data['season_number'] = $this->faker->numberBetween(1, 5);
            $data['episode_number'] = $this->faker->numberBetween(1, 12);
        } elseif ($mediaType === 'episode') {
            $data['series_name'] = $this->faker->words(2, true);
            $data['season_number'] = $this->faker->numberBetween(1, 5);
            $data['episode_number'] = $this->faker->numberBetween(1, 12);
        } elseif ($mediaType === 'music') {
            $data['artist_name'] = $this->faker->name();
            $data['album_name'] = $this->faker->words(2, true);
        }

        return $data;
    }

    public function movie(): self
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => 'movie',
            'series_name' => null,
            'artist_name' => null,
            'album_name' => null,
        ]);
    }

    public function tvShow(): self
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => 'series',
            'series_name' => $this->faker->words(2, true),
            'season_number' => $this->faker->numberBetween(1, 5),
            'episode_number' => $this->faker->numberBetween(1, 12),
        ]);
    }

    public function music(): self
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => 'music',
            'artist_name' => $this->faker->name(),
            'album_name' => $this->faker->words(2, true),
            'series_name' => null,
        ]);
    }
}
