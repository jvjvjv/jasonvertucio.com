<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeDownload extends Model
{
    protected $fillable = [
        'version',
        'ip_address',
        'user_agent',
        'share_code_id',
        'user_id',
    ];

    /**
     * Get the user who downloaded (if authenticated)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the share code used (if any)
     */
    public function shareCode(): BelongsTo
    {
        return $this->belongsTo(ResumeShareCode::class, 'share_code_id', 'id');
    }

    /**
     * Record a download
     */
    public static function record(
        string $version,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $shareCodeId = null,
        ?string $userId = null
    ): self {
        return self::create([
            'version' => $version,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 512) : null,
            'share_code_id' => $shareCodeId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Scope to filter by version
     */
    public function scopeForVersion($query, string $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Get download count by version
     */
    public static function countByVersion(): array
    {
        return self::selectRaw('version, COUNT(*) as count')
            ->groupBy('version')
            ->orderByDesc('version')
            ->pluck('count', 'version')
            ->toArray();
    }
}
