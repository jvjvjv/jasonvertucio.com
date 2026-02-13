<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeEducation extends Model
{
    /** @use HasFactory<\Database\Factories\ResumeEducationFactory> */
    use HasFactory;

    protected $table = 'resume_educations';

    protected $fillable = [
        'version_id',
        'institution',
        'degree',
        'date_start',
        'date_end',
        'description',
        'sort_order',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'version_id');
    }
}
