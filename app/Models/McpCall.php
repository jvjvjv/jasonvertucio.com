<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single request to the public MCP endpoint.
 *
 * Records what was asked and by whom, never the credential it was asked with:
 * a resolved `user_id` is the identity, and no part of a bearer token is
 * stored.
 */
class McpCall extends Model
{
    /** @use HasFactory<\Database\Factories\McpCallFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const OUTCOME_OK = 'ok';

    public const OUTCOME_ERROR = 'error';

    public const OUTCOME_UNAUTHENTICATED = 'unauthenticated';

    public const OUTCOME_THROTTLED = 'throttled';

    protected $fillable = [
        'session_id',
        'method',
        'tool_name',
        'user_id',
        'client_address',
        'user_agent',
        'client_name',
        'client_version',
        'outcome',
        'status_code',
        'duration_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this call was made without a resolved identity.
     */
    public function isAnonymous(): bool
    {
        return $this->user_id === null;
    }
}
