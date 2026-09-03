<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    /**
     * Store a comment against a published post.
     */
    public function store(StoreCommentRequest $request): RedirectResponse
    {
        if ($request->looksAutomated()) {
            return $this->accepted($request);
        }

        $user = $request->user();
        $parent = $request->parentComment();

        $comment = Comment::create([
            'post_id' => $request->targetPost()->id,
            'parent_id' => $parent?->id,
            'user_id' => $user?->id,
            'name' => $user?->name ?? $request->string('name')->toString(),
            'email' => $user?->email ?? $request->string('email')->toString(),
            'message' => $request->string('message')->toString(),
            'depth' => $parent === null ? 0 : $parent->depth + 1,
            'approved_at' => now(),
            'is_spam' => false,
            'ip_address' => $this->clientAddress($request),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->accepted($request, $comment);
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

    /**
     * Resolve the originating client address, preferring Cloudflare's header.
     *
     * Mirrors the resolution order in App\Http\Middleware\IpMiddleware.
     */
    protected function clientAddress(StoreCommentRequest $request): ?string
    {
        return $request->header('CF-Connecting-IP') ?? $request->ip();
    }
}
