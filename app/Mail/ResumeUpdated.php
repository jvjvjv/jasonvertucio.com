<?php

namespace App\Mail;

use App\Models\ResumeShareCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResumeUpdated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public ResumeShareCode $code,
        public string $version
    ) {}

    /**
     * Create a preview instance for mail testing.
     */
    public static function preview(): self
    {
        $code = ResumeShareCode::factory()->make();
        $version = json_decode(file_get_contents(resource_path('resume/version.json')));

        return new static($code, $version);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resume Updated',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.resume-updated',
            with: [
                'code' => $this->code,
                'version' => $this->version,
                'shareUrl' => url('/resume?code='.$this->code->id),
            ],
        );
    }
}
