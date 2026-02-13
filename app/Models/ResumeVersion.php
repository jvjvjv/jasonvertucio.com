<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResumeVersion extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'version',
        'is_current',
        'docx_path',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    /**
     * Scope to get the current version.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function personalInfo(): HasOne
    {
        return $this->hasOne(ResumePersonalInfo::class, 'version_id');
    }

    public function skillCategories(): HasMany
    {
        return $this->hasMany(ResumeSkillCategory::class, 'version_id')->orderBy('sort_order');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(ResumeExperience::class, 'version_id')->orderBy('sort_order');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(ResumeEducation::class, 'version_id')->orderBy('sort_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ResumeProject::class, 'version_id')->orderBy('sort_order');
    }

    public function technicalProfileCategories(): HasMany
    {
        return $this->hasMany(ResumeTechnicalProfileCategory::class, 'version_id')->orderBy('sort_order');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResumeDownload::class, 'version_id');
    }
}
