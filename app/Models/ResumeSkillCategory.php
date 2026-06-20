<?php

namespace App\Models;

use App\Enums\ResumeSkillGroup;
use Database\Factories\ResumeSkillCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeSkillCategory extends Model
{
    /** @use HasFactory<ResumeSkillCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'version_id',
        'group',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'group' => ResumeSkillGroup::class,
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(ResumeSkill::class, 'category_id')->orderBy('sort_order');
    }
}
