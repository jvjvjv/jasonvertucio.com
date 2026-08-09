<?php

namespace App\Models;

use App\Enums\SalaryPeriod;
use Database\Factories\ResumeExperienceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeExperience extends Model
{
    /** @use HasFactory<ResumeExperienceFactory> */
    use HasFactory;

    protected $fillable = [
        'version_id',
        'job_title',
        'job_title_label',
        'company',
        'location',
        'date_start',
        'date_end',
        'salary_start_amount',
        'salary_start_period',
        'salary_end_amount',
        'salary_end_period',
        'is_freelance',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'salary_start_amount' => 'decimal:2',
            'salary_start_period' => SalaryPeriod::class,
            'salary_end_amount' => 'decimal:2',
            'salary_end_period' => SalaryPeriod::class,
            'is_freelance' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }

    public function bullets(): HasMany
    {
        return $this->hasMany(ResumeExperienceBullet::class, 'experience_id')->orderBy('sort_order');
    }

    public function getDisplayJobTitleAttribute(): string
    {
        return $this->job_title_label ?: $this->job_title;
    }
}
