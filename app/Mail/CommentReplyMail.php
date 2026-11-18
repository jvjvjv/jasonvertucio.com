<?php

namespace App\Mail;

use App\Models\Comment;
use App\Support\CommentReplyEmails;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Tells a subscribed commenter that someone replied to their comment.
 *
 * Not queued itself: NotifyCommentReplyRecipient is the queued step, and it
 * decides at send time whether this goes out at all. The reply is escaped
 * into an HTML view rather than Markdown mail, so Markdown or HTML links in a
 * reply are never rendered as links by the site. A mail client may still
 * auto-link a bare URL; the send delay, spam re-check and hourly cap are the
 * mitigation for that.
 */
class CommentReplyMail extends Mailable
{
    public function __construct(public Comment $reply, public Comment $parent) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reply->name.' replied to your comment on '.$this->postTitle(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.comment-reply.html',
            text: 'mail.comment-reply.text',
            with: [
                'postTitle' => $this->postTitle(),
                'replierName' => $this->reply->name,
                'replyMessage' => $this->reply->message,
                'replyUrl' => route('post', $this->parent->post->slug).'#comment-'.$this->reply->getKey(),
                'unsubscribeUrl' => CommentReplyEmails::unsubscribeUrl($this->parent),
            ],
        );
    }

    /**
     * One-click unsubscribe (RFC 8058): the mail client POSTs to the signed
     * unsubscribe URL, which stops reply emails for this one comment.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.CommentReplyEmails::unsubscribeUrl($this->parent).'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    /**
     * The title of the post the conversation is on.
     */
    protected function postTitle(): string
    {
        return $this->parent->post?->title ?? 'Jason Vertucio\'s blog';
    }
}
