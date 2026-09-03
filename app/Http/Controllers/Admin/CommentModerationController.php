<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
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
     *
     * `approved_at` is what the public display query trusts, so it is cleared
     * here; `is_spam` records why.
     */
    public function markSpam(Comment $comment): RedirectResponse
    {
        $comment->update([
            'is_spam' => true,
            'approved_at' => null,
        ]);

        return back()->with('success', 'Comment marked as spam.');
    }

    /**
     * Restore a comment that was marked as spam.
     */
    public function markNotSpam(Comment $comment): RedirectResponse
    {
        $comment->update([
            'is_spam' => false,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Comment restored.');
    }
}
