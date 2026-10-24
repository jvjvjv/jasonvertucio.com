<?php

namespace App\Models;

use App\Enums\RoleIntelStatus;
use Database\Factories\RoleIntelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;

/**
 * What was learned about the role behind one application, and the company it
 * is for. Gathered by an intel AI session through its tools.
 *
 * @property RoleIntelStatus $status
 * @property list<array{name: string, domain: string|null}>|null $rejected_companies
 */
class RoleIntel extends Model
{
    /** @use HasFactory<RoleIntelFactory> */
    use HasFactory;

    /**
     * The role findings the intel agent may set, in display order.
     */
    public const array FINDING_FIELDS = [
        'summary',
        'responsibilities',
        'requirements',
        'team',
        'compensation',
        'interview_process',
        'concerns',
    ];

    protected $fillable = [
        'application_id',
        'company_id',
        'ai_conversation_id',
        'status',
        'failure_reason',
        'summary',
        'responsibilities',
        'requirements',
        'team',
        'compensation',
        'interview_process',
        'concerns',
        'rejected_companies',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => RoleIntelStatus::class,
            'rejected_companies' => 'array',
        ];
    }

    /**
     * The Role intel an AI session gathers. Looked up by key because tools
     * are handed the package's conversation model.
     */
    public static function forConversation(BaseAiConversation $conversation): ?self
    {
        if ($conversation->getKey() === null) {
            return null;
        }

        return self::query()->where('ai_conversation_id', $conversation->getKey())->first();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /**
     * Whether the admin has rejected a company with this name or website for
     * this role.
     */
    public function hasRejected(string $name, ?string $website): bool
    {
        $name = mb_strtolower(trim($name));
        $domain = Company::normalizeDomain($website);

        foreach ($this->rejected_companies ?? [] as $rejected) {
            if (mb_strtolower($rejected['name']) === $name) {
                return true;
            }

            if ($domain !== null && ($rejected['domain'] ?? null) === $domain) {
                return true;
            }
        }

        return false;
    }

    public function rememberRejected(Company $company): void
    {
        $rejected = $this->rejected_companies ?? [];
        $rejected[] = ['name' => $company->name, 'domain' => $company->website_domain];

        $this->rejected_companies = $rejected;
    }
}
