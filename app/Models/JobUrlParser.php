<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobUrlParser extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'company_name_selector',
        'job_title_selector',
        'job_description_selector',
        'html',
        'ai_reasoning',
        'status',
    ];

    /**
     * Scope to only active parsers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by domain.
     */
    public function scopeForDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain);
    }

    /**
     * Find the active parser for a given domain.
     */
    public static function findActiveForDomain(string $domain): ?self
    {
        return static::query()->active()->forDomain($domain)->first();
    }
}
