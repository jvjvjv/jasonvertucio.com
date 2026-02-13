<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeTechnicalProfileSkill extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeTechnicalProfileSkillFactory> */
    use HasFactory;

    protected $fillable = [
        'profile_category_id',
        'skill',
        'years',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'years' => 'decimal:1',
        ];
    }

    public function profileCategory(): BelongsTo
    {
        return $this->belongsTo(ResumeTechnicalProfileCategory::class, 'profile_category_id');
    }
}
