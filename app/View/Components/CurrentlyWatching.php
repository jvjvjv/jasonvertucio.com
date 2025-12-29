<?php

namespace App\View\Components;

use App\Models\LocalMedia;
use Illuminate\View\Component;

class CurrentlyWatching extends Component {
    public $media;

    public function __construct() {
        $this->media = LocalMedia::whereNotNull('last_playback_at')
            ->orderBy('last_playback_at', 'desc')
            ->first();
    }

    public function title() {
        if (!$this->media) {
            return null;
        }

        $title = $this->media->title;
        $isMusic = in_array($this->media->media_type, ['Album', 'Song', 'Audio']);

        if ($isMusic) {
            // For music, format as "Artist - Song" or "Artist - Album"
            if ($this->media->artist_name) {
                $title = $this->media->artist_name;
                if ($this->media->title) {
                    $title .= " - {$this->media->title}";
                }
            }
        } elseif ($this->media->series_name) {
            $title = $this->media->series_name;
        } elseif ($this->media->year) {
            $title .= " ({$this->media->year})";
        }

        return $title;
    }

    public function subtitle() {
        if (!$this->media) {
            return null;
        }

        if (in_array($this->media->media_type, ['Episode', 'Season', 'Series',])) {
            $subtitle = '';
            if ($this->media->season_number && $this->media->episode_number) {
                $season = str_pad($this->media->season_number, 2, '0', STR_PAD_LEFT);
                $episode = str_pad($this->media->episode_number, 2, '0', STR_PAD_LEFT);
                $subtitle .= " S{$season}E{$episode}";
            }
            if ($this->media->title) {
                $subtitle .= " - {$this->media->title}";
            }
            return $subtitle;
        }

        if (in_array($this->media->media_type, ['Album', 'Song', 'Audio']) && $this->media->album_name) {
            return "From the Album <em>{$this->media->album_name}</em>";
        }


        return null;
    }

    public function description() {
        if (!$this->media?->webhook_data) {
            return null;
        }

        $data = [];
        try {
            $data = $this->media->webhook_data;
        } catch (\Exception $exception) {
            return null;
        }

        return $data['Overview'];
    }

    public function mediaType() {
        if (!$this->media || !$this->media->media_type) {
            return 'Media';
        }

        $typeMap = [
            'Movie' => 'Movie',
            'Episode' => 'TV Show',
            'Season' => 'TV Season',
            'Series' => 'TV Series',
            'Audio' => 'Song',
            'Album' => 'Album',
            'Song' => 'Song',
            'Video' => 'Video',
        ];

        return $typeMap[$this->media->media_type] ?? $this->media->media_type;
    }

    public function header() {
        if (!$this->media) {
            return 'Currently Watching';
        }

        $eventType = $this->media->event_type;
        $isMusic = in_array($this->media->media_type, ['Album', 'Song', 'Audio']);

        switch ($eventType) {
            case 'PlaybackStart':
            case 'PlaybackProgress':
                return $isMusic ? 'Currently Listening To' : 'Currently Watching';
            case 'PlaybackStop':
                return $isMusic ? 'Last Listened' : 'Last Watched';
            case 'ItemAdded':
                return 'Media Added';
            case 'ItemDeleted':
                return 'Media Deleted';
            default:
                return $isMusic ? 'Currently Listening To' : 'Currently Watching';
        }
    }

    public function timestampLabel() {
        if (!$this->media) {
            return 'Last updated';
        }

        $eventType = $this->media->event_type;
        $isMusic = in_array($this->media->media_type, ['Album', 'Song', 'Audio']);

        switch ($eventType) {
            case 'PlaybackStop':
                return $isMusic ? 'Last listened' : 'Last watched';
            case 'ItemAdded':
                return 'Added';
            case 'ItemDeleted':
                return 'Deleted';
            default:
                return 'Last updated';
        }
    }

    public function timestamp() {
        if (!$this->media || !$this->media->last_playback_at) {
            return 'recently';
        }

        return $this->media->last_playback_at->diffForHumans();
    }

    public function shouldDisplay() {
        return $this->media !== null;
    }

    public function mediaObject() {
        return $this->media;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render() {
        return view('components.currently-watching');
    }
}
