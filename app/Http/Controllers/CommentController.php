<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Support\ClientAddress;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    /**
     * Store a comment against a published post.
     *
     * A request for reply emails stands at once, and lifts any earlier
     * opt-out of the address, only for a signed-in user whose account
     * address this site holds as verified. Signing in is not proof of the
     * address by itself, so everyone else waits for an emailed confirmation.
     */
    public function store(StoreCommentRequest $request): RedirectResponse
    {
        if ($request->looksAutomated()) {
            return $this->accepted($request);
        }

        $user = $request->user();
        $parent = $request->parentComment();
        $wantsReplyEmails = $request->boolean('notify_replies')
            && config('comments.reply_notifications.enabled');

        $comment = new Comment([
            'post_id' => $request->targetPost()->id,
            'parent_id' => $parent?->id,
            'user_id' => $user?->id,
            'name' => $user?->name ?? $request->string('name')->toString(),
            'email' => $user?->email ?? $request->string('email')->toString(),
            'message' => $request->string('message')->toString(),
            'depth' => $parent === null ? 0 : $parent->depth + 1,
            'approved_at' => now(),
            'is_spam' => false,
            'ip_address' => ClientAddress::from($request),
            'user_agent' => $request->userAgent(),
            'notify_replies' => $wantsReplyEmails,
        ]);

        if ($wantsReplyEmails && $user?->hasVerifiedEmail()) {
            $comment->notify_replies_confirmed_at = now();
            CommentEmailOptOut::lift($user->email);
        }

        $comment->save();

        return $this->accepted($request, $comment);
    }

    /**
     * Change a comment's message, and nothing else about it.
     *
     * `edited_at` moves only when the message really changes, so resubmitting
     * the same text does not mark the comment as edited.
     */
    public function update(UpdateCommentRequest $request, string $slug, Comment $comment): RedirectResponse
    {
        $comment->message = $request->string('message')->toString();

        if ($comment->isDirty('message')) {
            $comment->edited_at = now();
            $comment->save();
        }

        return redirect()
            ->to(route('post', $slug).'#comment-'.$comment->id)
            ->with('comment_updated', true);
    }

    /**
     * Soft delete a comment, leaving its replies where they are.
     */
    public function destroy(string $slug, Comment $comment): RedirectResponse
    {
        abort_unless($comment->isOnPublishedPost($slug), 404);

        $this->authorize('delete', $comment);

        $comment->delete();

        return redirect()
            ->to(route('post', $slug).'#comments')
            ->with('comment_deleted', true);
    }

    /**
     * Hide a comment as spam from the post it is displayed on.
     *
     * Lands on the thread rather than the comment's own anchor, which a spam
     * leaf no longer has.
     */
    public function markSpam(string $slug, Comment $comment): RedirectResponse
    {
        abort_unless($comment->isOnPublishedPost($slug), 404);

        $comment->markAsSpam();

        return redirect()
            ->to(route('post', $slug).'#comments')
            ->with('comment_marked_spam', true);
    }

    /**
     * Redirect back to the comment on the post it belongs to.
     *
     * A discarded honeypot submission takes this same path with no comment, so
     * that an automated post is indistinguishable from an accepted one.
     */
    protected function accepted(StoreCommentRequest $request, ?Comment $comment = null): RedirectResponse
    {
        $anchor = $comment ? '#comment-'.$comment->id : '#comments';

        return redirect()
            ->to(route('post', $request->route('slug')).$anchor)
            ->with('comment_posted', true);
    }
}
