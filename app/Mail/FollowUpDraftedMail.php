<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the owner a follow-up draft is waiting for review. It names the
 * company and position and links to the Discussion page, and deliberately
 * carries neither the contacts nor the draft, so a forwarded notification
 * exposes nothing about third parties.
 */
class FollowUpDraftedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Follow-up ready to review: '.$this->label(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.follow-up-drafted',
            with: [
                'company' => $this->application->knownCompanyName() ?? 'an unknown company',
                'position' => $this->application->knownPosition() ?? 'an unknown position',
                'url' => route('admin.employment.applications.show', $this->application),
            ],
        );
    }

    private function label(): string
    {
        $position = $this->application->knownPosition() ?? 'Unknown position';
        $company = $this->application->knownCompanyName() ?? 'unknown company';

        return "{$position} at {$company}";
    }
}
