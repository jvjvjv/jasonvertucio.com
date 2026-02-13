<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeProject extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'version_id',
        'project_name',
        'description',
        'sort_order',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }

    public function bullets(): HasMany
    {
        return $this->hasMany(ResumeProjectBullet::class, 'project_id')->orderBy('sort_order');
    }
}
