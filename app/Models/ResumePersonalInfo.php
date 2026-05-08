<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumePersonalInfo extends Model
{
    /** @use HasFactory<\Database\Factories\ResumePersonalInfoFactory> */
    use HasFactory;

    protected $table = 'resume_personal_info';

    protected $fillable = [
        'version_id',
        'name',
        'title',
        'email',
        'phone',
        'linkedin',
        'url',
        'summary',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }
}
