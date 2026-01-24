<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResumeView extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'share_code_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the share code that this view belongs to.
     */
    public function shareCode(): BelongsTo
    {
        return $this->belongsTo(ResumeShareCode::class, 'share_code_id');
    }
}
