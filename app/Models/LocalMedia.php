<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocalMedia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'jellyfin_item_id',
        'jellyfin_user_id',
        'event_type',
        'media_type',
        'title',
        'series_name',
        'artist_name',
        'album_name',
        'season_number',
        'episode_number',
        'year',
        'provider_ids',
        'playback_position',
        'playback_duration',
        'is_paused',
        'last_playback_at',
        'play_count',
        'webhook_data',
    ];

    protected $casts = [
        'provider_ids' => 'array',
        'webhook_data' => 'array',
        'last_playback_at' => 'datetime',
        'season_number' => 'integer',
        'episode_number' => 'integer',
        'year' => 'integer',
        'playback_position' => 'integer',
        'playback_duration' => 'integer',
        'play_count' => 'integer',
        'is_paused' => 'boolean',
    ];
}
