<?php

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Comment $comment;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }

    /**
     * Create a preview instance for mail testing.
     */
    public static function preview(): self
    {
        $comment = Comment::factory()->make();

        return new static($comment);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A New Comment has been made',
        );
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-comment',
            with: [
                'comment' => $this->comment,
            ],
        );
    }
}
