<?php

namespace App\Mail;

use App\Enums\SecurityMethodKind;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityMethodRemovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public SecurityMethodKind $kind,
    ) {}

    /**
     * Create a preview instance for mail testing.
     */
    public static function preview(): self
    {
        return new static('Preview User', SecurityMethodKind::Passkey);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A security method was removed from your account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.security-method-removed',
            with: [
                'userName' => $this->userName,
                'methodLabel' => $this->kind->label(),
            ],
        );
    }
}
