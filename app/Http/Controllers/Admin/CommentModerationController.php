<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\IpBanSource;
use App\Models\Comment;
use App\Models\IpBan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CommentModerationController extends Controller
{
    /**
     * Display every comment, newest first, with its current state.
     */
    public function index(): InertiaResponse
    {
        $comments = Comment::query()
            ->with(['post:id,title,slug', 'user:id,name'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (Comment $comment): array => [
                'id' => $comment->id,
                'name' => $comment->name,
                'email' => $comment->email,
                'message' => $comment->message,
                'depth' => $comment->depth,
                'is_spam' => $comment->is_spam,
                'approved_at' => $comment->approved_at?->toIso8601String(),
                'created_at' => $comment->created_at?->toIso8601String(),
                'ip_address' => $comment->ip_address,
                'registered_user' => $comment->user?->name,
                'post' => $comment->post ? [
                    'title' => $comment->post->title,
                    'url' => route('post', $comment->post->slug).'#comment-'.$comment->id,
                ] : null,
            ]);

        return Inertia::render('comments/Index', [
            'comments' => $comments,
        ]);
    }

    /**
     * Hide a comment as spam.
     */
    public function markSpam(Comment $comment): RedirectResponse
    {
        $comment->markAsSpam();

        return back()->with('success', 'Comment marked as spam.');
    }

    /**
     * Restore a comment that was marked as spam.
     */
    public function markNotSpam(Comment $comment): RedirectResponse
    {
        $comment->markAsNotSpam();

        return back()->with('success', 'Comment restored.');
    }

    /**
     * Ban the address a comment was posted from.
     */
    public function banIp(Comment $comment): RedirectResponse
    {
        if (blank($comment->ip_address)) {
            return back()->with('error', 'This comment has no recorded IP address to ban.');
        }

        $ipBan = IpBan::firstOrCreate(
            ['ip' => $comment->ip_address],
            [
                'source' => IpBanSource::Admin,
                'banned_method' => 'ADMIN',
                'banned_url' => 'ADMIN',
                'banned_body' => "Banned by a moderator from comment #{$comment->id}",
            ],
        );

        return back()->with(
            'success',
            $ipBan->wasRecentlyCreated ? "Banned {$comment->ip_address}." : "{$comment->ip_address} is already banned.",
        );
    }

    /**
     * Soft delete a comment, whatever its approval or spam state.
     *
     * A spam comment is hidden on its post and so offers no controls there;
     * the queue is the only place it can be deleted from.
     */
    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}
