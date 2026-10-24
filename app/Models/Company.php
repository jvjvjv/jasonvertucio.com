<?php

namespace App\Models;

use App\Enums\CompanyInsightCategory;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hiring company, shared by the Role intel of every application there.
 * Identified by its website domain when known, otherwise by name.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * The metadata the intel agent may set.
     */
    public const array PROFILE_FIELDS = [
        'name',
        'website_url',
        'headquarters',
        'employee_count',
        'revenue',
        'industry',
        'founded_year',
        'ownership',
        'ticker',
        'summary',
    ];

    public const array OWNERSHIP_TYPES = ['public', 'private', 'nonprofit', 'government'];

    protected $fillable = [
        'name',
        'website_domain',
        'website_url',
        'headquarters',
        'employee_count',
        'revenue',
        'industry',
        'founded_year',
        'ownership',
        'ticker',
        'summary',
        'researched_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
            'researched_at' => 'datetime',
        ];
    }

    public function insights(): HasMany
    {
        return $this->hasMany(CompanyInsight::class)->orderBy('id');
    }

    public function roleIntels(): HasMany
    {
        return $this->hasMany(RoleIntel::class);
    }

    /**
     * The bare host of a website, lowercased and without `www.`, used to tell
     * companies apart. Accepts a full URL or a bare domain.
     */
    public static function normalizeDomain(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $website)) {
            $website = "https://{$website}";
        }

        $host = parse_url($website, PHP_URL_HOST);

        if (! is_string($host) || $host === '' || ! str_contains($host, '.')) {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    /**
     * The existing company with this website's domain, or else, when the
     * domain is unknown, one with this name (ignoring case).
     */
    public static function findMatching(string $name, ?string $website): ?self
    {
        $domain = self::normalizeDomain($website);

        if ($domain !== null) {
            $byDomain = self::query()->where('website_domain', $domain)->first();

            if ($byDomain !== null) {
                return $byDomain;
            }
        }

        return self::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($domain !== null, fn ($query) => $query->whereNull('website_domain'))
            ->orderBy('id')
            ->first();
    }

    /**
     * Insights grouped by category, in category order.
     *
     * @return array<string, list<CompanyInsight>>
     */
    public function insightsByCategory(): array
    {
        $grouped = [];

        foreach (CompanyInsightCategory::cases() as $category) {
            $matching = $this->insights->filter(fn (CompanyInsight $insight): bool => $insight->category === $category)->values()->all();

            if ($matching !== []) {
                $grouped[$category->value] = $matching;
            }
        }

        return $grouped;
    }
}
