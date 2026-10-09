<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationStatusUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ApplicationStatus $status
 */
class ApplicationStatusUpdate extends Model
{
    /** @use HasFactory<ApplicationStatusUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'status',
        'notes',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
