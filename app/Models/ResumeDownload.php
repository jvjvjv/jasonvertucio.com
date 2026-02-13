<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'version_id',
        'ip_address',
        'user_agent',
        'share_code_id',
        'user_id',
    ];

    /**
     * Get the version associated with this download.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }

    /**
     * Get the user who downloaded (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the share code used (if any).
     */
    public function shareCode(): BelongsTo
    {
        return $this->belongsTo(ResumeShareCode::class, 'share_code_id', 'id');
    }

    /**
     * Record a download.
     */
    public static function record(
        string $version,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $shareCodeId = null,
        ?string $userId = null
    ): self {
        // Look up the version_id from the version string
        $versionModel = ResumeVersion::where('version', $version)->first();

        return self::create([
            'version_id' => $versionModel?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 512) : null,
            'share_code_id' => $shareCodeId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Scope to filter by version.
     */
    public function scopeForVersion($query, string $version)
    {
        return $query->whereHas('version', function ($q) use ($version) {
            $q->where('version', $version);
        });
    }

    /**
     * Get download count by version.
     */
    public static function countByVersion(): array
    {
        return self::join('resume_versions', 'resume_downloads.version_id', '=', 'resume_versions.id')
            ->selectRaw('resume_versions.version, COUNT(*) as count')
            ->groupBy('resume_versions.version')
            ->orderByDesc('resume_versions.version')
            ->pluck('count', 'resume_versions.version')
            ->toArray();
    }
}
