<?php

namespace App\Models;

use App\Enums\SecurityMethodKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLogEntry extends Model
{
    use HasFactory;

    protected $table = 'security_audit_log';

    protected $fillable = [
        'user_id',
        'kind',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SecurityMethodKind::class,
            'removed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
