<?php

namespace App\Models;

use App\Enums\CompanyInsightCategory;
use Database\Factories\CompanyInsightFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One finding about a company that bears on applying or interviewing there.
 *
 * @property CompanyInsightCategory $category
 */
class CompanyInsight extends Model
{
    /** @use HasFactory<CompanyInsightFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'category',
        'title',
        'content',
        'source_url',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'category' => CompanyInsightCategory::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
