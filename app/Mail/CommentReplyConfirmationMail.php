<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Support\CommentReplyEmails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Asks an anonymous commenter to confirm they want reply emails.
 *
 * Nothing the commenter wrote is reproduced, so the email cannot be used to
 * deliver someone else's words to the address it is sent to.
 */
class CommentReplyConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * A comment removed for good before the worker runs needs no confirming.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Comment $comment) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm reply emails for your comment on '.$this->postTitle(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.comment-reply-confirmation.html',
            text: 'mail.comment-reply-confirmation.text',
            with: [
                'postTitle' => $this->postTitle(),
                'confirmUrl' => CommentReplyEmails::confirmUrl($this->comment),
                'unsubscribeUrl' => CommentReplyEmails::unsubscribeUrl($this->comment),
                'expiresInDays' => (int) config('comments.reply_notifications.confirmation_expires_days'),
                'wasOptedOut' => CommentEmailOptOut::covers($this->comment->email)
                    && ! CommentEmailOptOut::coveredByAnotherAddress($this->comment->email),
            ],
        );
    }

    /**
     * The title of the post the comment was left on.
     */
    protected function postTitle(): string
    {
        return $this->comment->post?->title ?? 'Jason Vertucio\'s blog';
    }
}
