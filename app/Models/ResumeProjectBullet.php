<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeProjectBullet extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeProjectBulletFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'content',
        'sort_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ResumeProject::class, 'project_id');
    }
}
