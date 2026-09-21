<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaPlaybackMilestone extends Model
{
    protected $fillable = [
        'local_media_id',
        'title',
        'media_type',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }

    public function localMedia(): BelongsTo
    {
        return $this->belongsTo(LocalMedia::class);
    }
}
