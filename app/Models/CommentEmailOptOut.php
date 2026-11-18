<?php

namespace App\Models;

use Database\Factories\CommentEmailOptOutFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A mailbox that has asked for no more comment reply emails, whatever
 * comment they concern. Stored as its mailbox key (see normalize()), so one
 * row covers every spelling of the address.
 *
 * Not permanent: it does not block confirmation emails, so the address can
 * opt in again, and it is lifted when that confirmation is completed or a
 * verified account opts in.
 */
class CommentEmailOptOut extends Model
{
    /** @use HasFactory<CommentEmailOptOutFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Domains that ignore dots in the mailbox name and share one inbox.
     */
    private const GMAIL_DOMAINS = ['gmail.com', 'googlemail.com'];

    protected $fillable = [
        'email',
    ];

    /**
     * Determine whether the given address's mailbox has opted out.
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
     * The mailbox key every comment-email comparison uses: the form opted-out
     * addresses are stored in, and what the per-address caps and the
     * self-reply and owner checks compare.
     *
     * Mailboxes accept many spellings of one address, so a key that only
     * ignored case would let `name+1@…`, `name+2@…` (or Gmail's dotted
     * variants) each get their own cap and slip past an opt-out. The key
     * lower-cases, converts the domain to its ASCII (IDNA/UTS #46) form, so
     * a fullwidth or ideographic-dot spelling of a domain is the domain mail
     * is delivered to, drops a `+tag`, and for Gmail drops dots and folds
     * googlemail.com into gmail.com. A local part that is nothing but a tag
     * is kept whole. It is never used as a destination: mail goes to the
     * address exactly as entered.
     */
    public static function normalize(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        $local = mb_substr($email, 0, $at);
        $domain = self::asciiDomain(mb_substr($email, $at + 1));
        $mailbox = $local;

        $plus = mb_strpos($mailbox, '+');

        if ($plus !== false) {
            $mailbox = mb_substr($mailbox, 0, $plus);
        }

        if (in_array($domain, self::GMAIL_DOMAINS, true)) {
            $mailbox = str_replace('.', '', $mailbox);
            $domain = 'gmail.com';
        }

        if ($mailbox === '') {
            $mailbox = $local;
        }

        return $mailbox.'@'.$domain;
    }

    /**
     * A domain in the ASCII form its mail is delivered to: `ｇmail.com` and
     * `gmail。com` become `gmail.com`, and `bücher.de` its punycode. A domain
     * IDNA cannot convert is kept as given. A trailing dot (the root, which
     * `gmail.com．` also converts to) is dropped, since `gmail.com.` is the
     * same domain.
     */
    private static function asciiDomain(string $domain): string
    {
        $ascii = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return rtrim($ascii === false ? $domain : mb_strtolower($ascii), '.');
    }

    /**
     * Store the address as its mailbox key.
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
