<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeExperience extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeExperienceFactory> */
    use HasFactory;

    protected $fillable = [
        'version_id',
        'job_title',
        'company',
        'location',
        'date_start',
        'date_end',
        'sort_order',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }

    public function bullets(): HasMany
    {
        return $this->hasMany(ResumeExperienceBullet::class, 'experience_id')->orderBy('sort_order');
    }
}
