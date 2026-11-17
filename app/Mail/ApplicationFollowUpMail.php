<?php

namespace App\Mail;

use App\Models\ApplicationFollowUp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An approved follow-up, as a plain personal email: from the site's mail
 * address, to the recipients recorded at approval, openly copied to
 * `mail.cc.address` when set (so a reply-all reaches a real inbox), and
 * blind-copied to the owner unless the owner is already that copy. No site
 * layout or branding. Not queued itself:
 * {@see \App\Jobs\SendApplicationFollowUpJob} is the queue boundary.
 */
class ApplicationFollowUpMail extends Mailable
{
    public function __construct(public ApplicationFollowUp $followUp) {}

    public function envelope(): Envelope
    {
        $cc = $this->address('mail.cc.address');
        $owner = $this->address('resume.follow_ups.notification_email');

        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            to: array_values($this->followUp->recipients ?? []),
            cc: $cc === null ? [] : [$cc],
            bcc: $owner === null || $owner === $cc ? [] : [$owner],
            subject: $this->followUp->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.follow-up.html',
            text: 'mail.follow-up.text',
            with: ['body' => $this->followUp->body],
        );
    }

    /**
     * A configured address, lower-cased and trimmed, or null when unset.
     */
    private function address(string $key): ?string
    {
        $address = strtolower(trim((string) config($key)));

        return $address === '' ? null : $address;
    }
}
