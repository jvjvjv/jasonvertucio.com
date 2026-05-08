<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobUrl extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'job_url_parser_id',
        'url',
        'contents',
    ];

    public function parser(): BelongsTo
    {
        return $this->belongsTo(JobUrlParser::class, 'job_url_parser_id');
    }

    public function targetedResumes(): HasMany
    {
        return $this->hasMany(TargetedResume::class);
    }
}
