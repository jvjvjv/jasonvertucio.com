<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a local user to an identity at an external sign-in provider.
 *
 * `subject` is the provider's stable identifier for the person; later sign-ins
 * match on it, never on email. `email_at_link` records the email the identity
 * carried when the link was made, as history.
 */
class FederatedIdentity extends Model
{
    use HasFactory;

    public const PROVIDER_BSPDX = 'bspdx';

    protected $fillable = [
        'user_id',
        'provider',
        'subject',
        'email_at_link',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
