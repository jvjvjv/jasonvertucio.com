<?php

namespace App\Models;

use Database\Factories\ApplicationContactFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person involved in an application's hiring process. The email is stored
 * lower-cased, so it is unique within the application ignoring case.
 */
class ApplicationContact extends Model
{
    /** @use HasFactory<ApplicationContactFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'email',
        'name',
        'role',
        'notes',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : mb_strtolower(trim($value)),
        );
    }
}
