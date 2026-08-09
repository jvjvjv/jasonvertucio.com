<?php

namespace App\Models;

use Database\Factories\ResumeExperienceBulletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeExperienceBullet extends Model
{
    /** @use HasFactory<ResumeExperienceBulletFactory> */
    use HasFactory;

    protected $fillable = [
        'experience_id',
        'content',
        'sort_order',
    ];

    public function experience(): BelongsTo
    {
        return $this->belongsTo(ResumeExperience::class, 'experience_id');
    }
}
