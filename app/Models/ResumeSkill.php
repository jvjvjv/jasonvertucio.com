<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeSkill extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeSkillFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'sort_order',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResumeSkillCategory::class, 'category_id');
    }
}
