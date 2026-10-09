<?php

namespace App\Models;

use App\Enums\TargetedResumeApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetedResumeStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'targeted_resume_id',
        'status',
        'notes',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TargetedResumeApplicationStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function targetedResume(): BelongsTo
    {
        return $this->belongsTo(TargetedResume::class);
    }
}
