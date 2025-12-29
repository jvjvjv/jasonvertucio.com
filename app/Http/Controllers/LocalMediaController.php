<?php

namespace App\Http\Controllers;

use App\Models\LocalMedia;
use Illuminate\Http\Request;

class LocalMediaController extends Controller {
    public function index(Request $request) {
        // Get the raw JSON body first
        $data = $request->json()->all();

        $event = $data['NotificationType'] ?? null;

        if (!$event) {
            return response(['error' => 'No event type provided'], 400);
        }

        switch ($event) {
            case 'ItemAdded':
                $this->handleItemAdded($data);
                break;
            case 'ItemDeleted':
                $this->handleItemDeleted($data);
                break;
            case 'PlaybackStart':
            case 'PlaybackProgress':
                $this->handlePlayback($data);
                break;
            case 'PlaybackStop':
                $this->handlePlaybackStop($data);
                break;
            case 'PendingRestart':
                Log::info('Jellyfin pending restart', ['data' => $data]);
                break;
            default:
                Log::info('Unhandled Jellyfin event', ['event' => $event, 'data' => $data]);
                break;
        }

        return response([
            'status' => 'success',
            'event' => $event
        ], 200);
    }

    public function currentlyWatching() {
        $media = LocalMedia::whereNotNull('last_playback_at')
            ->orderBy('last_playback_at', 'desc')
            ->first();

        if (!$media) {
            return response()->json(null);
        }

        return response()->json([
            'title' => $media->title,
            'series_name' => $media->series_name,
            'artist_name' => $media->artist_name,
            'album_name' => $media->album_name,
            'season_number' => $media->season_number,
            'episode_number' => $media->episode_number,
            'media_type' => $media->media_type,
            'year' => $media->year,
            'event_type' => $media->event_type,
            'last_watched' => $media->last_playback_at->diffForHumans(),
            'play_count' => $media->play_count,
        ]);
    }

    protected function handleItemAdded($data) {
        if (!isset($data['ItemId']))
            return;

        $providerIds = [];
        if (isset($data['Provider_imdb'])) {
            $providerIds['imdb'] = $data['Provider_imdb'];
        }
        if (isset($data['Provider_tmdb'])) {
            $providerIds['tmdb'] = $data['Provider_tmdb'];
        }

        LocalMedia::updateOrCreate(
            ['jellyfin_item_id' => $data['ItemId']],
            [
                'event_type' => 'ItemAdded',
                'media_type' => $data['ItemType'] ?? null,
                'title' => $data['Name'] ?? 'Unknown',
                'series_name' => $data['SeriesName'] ?? null,
                'artist_name' => $data['Artist'] ?? $data['AlbumArtist'] ?? null,
                'album_name' => $data['Album'] ?? null,
                'season_number' => $data['SeasonNumber'] ?? null,
                'episode_number' => $data['EpisodeNumber'] ?? null,
                'year' => $data['Year'] ?? null,
                'provider_ids' => !empty($providerIds) ? $providerIds : null,
                'webhook_data' => $data,
            ]
        );
    }

    protected function handleItemDeleted($data) {
        if (!isset($data['ItemId']))
            return;

        $media = LocalMedia::where('jellyfin_item_id', $data['ItemId'])->first();
        if ($media) {
            $media->delete();
            Log::info('Deleted media item', ['jellyfin_item_id' => $data['ItemId']]);
        }
    }

    protected function handlePlayback($data) {
        if (!isset($data['ItemId']))
            return;

        $providerIds = [];
        if (isset($data['Provider_imdb'])) {
            $providerIds['imdb'] = $data['Provider_imdb'];
        }
        if (isset($data['Provider_tmdb'])) {
            $providerIds['tmdb'] = $data['Provider_tmdb'];
        }

        $media = LocalMedia::updateOrCreate(
            ['jellyfin_item_id' => $data['ItemId']],
            [
                'jellyfin_user_id' => $data['UserId'] ?? null,
                'event_type' => $data['NotificationType'] ?? 'Playback',
                'media_type' => $data['ItemType'] ?? null,
                'title' => $data['Name'] ?? 'Unknown',
                'series_name' => $data['SeriesName'] ?? null,
                'artist_name' => $data['Artist'] ?? $data['AlbumArtist'] ?? null,
                'album_name' => $data['Album'] ?? null,
                'season_number' => $data['SeasonNumber'] ?? null,
                'episode_number' => $data['EpisodeNumber'] ?? null,
                'year' => $data['Year'] ?? null,
                'provider_ids' => !empty($providerIds) ? $providerIds : null,
                'playback_position' => $data['PlaybackPositionTicks'] ?? 0,
                'playback_duration' => $data['RunTimeTicks'] ?? null,
                'is_paused' => $data['IsPaused'] ?? false,
                'last_playback_at' => now(),
                'webhook_data' => $data,
            ]
        );

        // Only increment play count on PlaybackStart, not Progress
        if (($data['NotificationType'] ?? '') === 'PlaybackStart') {
            $media->increment('play_count');
        }
    }

    protected function handlePlaybackStop($data) {
        if (!isset($data['ItemId']))
            return;

        $providerIds = [];
        if (isset($data['Provider_imdb'])) {
            $providerIds['imdb'] = $data['Provider_imdb'];
        }
        if (isset($data['Provider_tmdb'])) {
            $providerIds['tmdb'] = $data['Provider_tmdb'];
        }

        LocalMedia::updateOrCreate(
            ['jellyfin_item_id' => $data['ItemId']],
            [
                'jellyfin_user_id' => $data['UserId'] ?? null,
                'event_type' => 'PlaybackStop',
                'media_type' => $data['ItemType'] ?? null,
                'title' => $data['Name'] ?? 'Unknown',
                'series_name' => $data['SeriesName'] ?? null,
                'artist_name' => $data['Artist'] ?? $data['AlbumArtist'] ?? null,
                'album_name' => $data['Album'] ?? null,
                'season_number' => $data['SeasonNumber'] ?? null,
                'episode_number' => $data['EpisodeNumber'] ?? null,
                'year' => $data['Year'] ?? null,
                'provider_ids' => !empty($providerIds) ? $providerIds : null,
                'playback_position' => $data['PlaybackPositionTicks'] ?? 0,
                'playback_duration' => $data['RunTimeTicks'] ?? null,
                'is_paused' => false,
                'webhook_data' => $data,
            ]
        );
    }
}
