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
 * Each row keeps two keys. `email` is the address that opted out, as its
 * address key (see addressKey()): only that address can lift the opt-out.
 * `mailbox` is its broader mailbox key (see normalize()): the opt-out blocks
 * every spelling with that key. Blocking broadly only ever sends less, but
 * lifting broadly would let `bob+x@…` lift the opt-out of a different person
 * at `bob@…` on a mail server where `+` is part of the name.
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

    /**
     * Providers known to deliver `name+tag@` to `name@`, so a `+tag` names
     * the same person there and the address key may drop it. Elsewhere `+`
     * may be part of someone else's name.
     */
    private const SUBADDRESSING_DOMAINS = [
        'gmail.com',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'fastmail.com',
        'fastmail.fm',
        'proton.me',
        'protonmail.com',
        'pm.me',
    ];

    protected $fillable = [
        'email',
    ];

    /**
     * Determine whether the given address's mailbox has opted out, from this
     * spelling or any other.
     */
    public static function covers(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        return static::query()->where('mailbox', self::normalize($email))->exists();
    }

    /**
     * Determine whether the address's mailbox is opted out from a spelling
     * that this address cannot lift (for example `bob+x@corp.example` when
     * asking about `bob@corp.example`).
     */
    public static function coveredByAnotherAddress(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        return static::query()
            ->where('mailbox', self::normalize($email))
            ->where('email', '!=', self::addressKey($email))
            ->exists();
    }

    /**
     * Opt an address out, doing nothing if it already has. createOrFirst
     * relies on the unique index, so a double submit cannot fail on a race.
     */
    public static function optOut(string $email): self
    {
        return static::query()->createOrFirst(['email' => self::addressKey($email)]);
    }

    /**
     * Take an address's own opt-out off the list. Called only where the
     * address owner has shown they want comment emails again: a signed-in
     * opt-in, or a completed confirmation. An opt-out made from another
     * spelling of the mailbox stays, unless the provider is known to deliver
     * both spellings to one inbox (see addressKey()).
     */
    public static function lift(?string $email): void
    {
        if (blank($email)) {
            return;
        }

        static::query()->where('email', self::addressKey($email))->delete();
    }

    /**
     * The mailbox key the per-address caps, the self-reply and owner checks,
     * and opt-out blocking compare.
     *
     * Mailboxes accept many spellings of one address, so a key that only
     * ignored case would let `name+1@…`, `name+2@…` (or Gmail's dotted
     * variants) each get their own cap and slip past an opt-out. The key
     * lower-cases, converts the domain to its ASCII (IDNA/UTS #46) form, so
     * a fullwidth or ideographic-dot spelling of a domain is the domain mail
     * is delivered to, drops a `+tag` on every domain, and for Gmail drops
     * dots and folds googlemail.com into gmail.com. A local part that is
     * nothing but a tag is kept whole. It is never used as a destination:
     * mail goes to the address exactly as entered.
     *
     * It is deliberately broad, so it only ever decides to send less. It
     * never decides who may lift an opt-out; addressKey() does.
     */
    public static function normalize(string $email): string
    {
        return self::key($email, dropTagEverywhere: true);
    }

    /**
     * The key of an address as one person: the mailbox key, except that a
     * `+tag` is dropped only on providers known to deliver it to the same
     * inbox (see SUBADDRESSING_DOMAINS). On any other domain
     * `bob+x@corp.example` and `bob@corp.example` stay different people.
     */
    public static function addressKey(string $email): string
    {
        return self::key($email, dropTagEverywhere: false);
    }

    private static function key(string $email, bool $dropTagEverywhere): string
    {
        $email = mb_strtolower(trim($email));
        $at = mb_strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        $local = mb_substr($email, 0, $at);
        $domain = self::asciiDomain(mb_substr($email, $at + 1));

        if (in_array($domain, self::GMAIL_DOMAINS, true)) {
            $domain = 'gmail.com';
        }

        $mailbox = $local;
        $plus = mb_strpos($mailbox, '+');

        if ($plus !== false && ($dropTagEverywhere || in_array($domain, self::SUBADDRESSING_DOMAINS, true))) {
            $mailbox = mb_substr($mailbox, 0, $plus);
        }

        if ($domain === 'gmail.com') {
            $mailbox = str_replace('.', '', $mailbox);
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
     * Store the address as its address key, with its mailbox key alongside.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'email' => self::addressKey($value),
                'mailbox' => self::normalize($value),
            ],
        );
    }
}
