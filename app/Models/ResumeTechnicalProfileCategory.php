<?php

namespace App\Models;

use Database\Factories\ResumeTechnicalProfileCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeTechnicalProfileCategory extends Model
{
    /** @use HasFactory<ResumeTechnicalProfileCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'version_id',
        'category',
        'is_main',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(ResumeTechnicalProfileSkill::class, 'profile_category_id')->orderBy('sort_order');
    }
}
