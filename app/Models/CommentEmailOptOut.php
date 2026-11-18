<?php

namespace App\Models;

use Database\Factories\CommentEmailOptOutFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An address that has asked for no more comment reply emails, whatever
 * comment they concern.
 *
 * Not permanent: it does not block confirmation emails, so the address can
 * opt in again, and it is lifted when that confirmation is completed or a
 * verified account opts in.
 *
 * The email is stored lower-cased, so the unique index holds ignoring case.
 */
class CommentEmailOptOut extends Model
{
    /** @use HasFactory<CommentEmailOptOutFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'email',
    ];

    /**
     * Determine whether the given address has opted out, ignoring case.
     */
    public static function covers(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        return static::query()->where('email', self::normalize($email))->exists();
    }

    /**
     * Opt an address out, doing nothing if it already has. createOrFirst
     * relies on the unique index, so a double submit cannot fail on a race.
     */
    public static function optOut(string $email): self
    {
        return static::query()->createOrFirst(['email' => self::normalize($email)]);
    }

    /**
     * Take an address off the list. Called only where the address owner has
     * shown they want comment emails again: a signed-in opt-in, or a
     * completed confirmation.
     */
    public static function lift(?string $email): void
    {
        if (blank($email)) {
            return;
        }

        static::query()->where('email', self::normalize($email))->delete();
    }

    /**
     * The form every opted-out address is stored and compared in.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Store the address lower-cased.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalize($value),
        );
    }
}
