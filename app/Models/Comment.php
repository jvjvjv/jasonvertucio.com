<?php

namespace App\Models;

use App\Events\CommentMarkedAsSpam;
use Canvas\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The deepest a comment may sit in a thread.
     */
    public const MAX_DEPTH = 5;

    /**
     * The deepest visual indentation level the thread renders.
     */
    public const MAX_VISUAL_DEPTH = 2;

    protected $fillable = [
        'user_id',
        'post_id',
        'parent_id',
        'fb_user_id',
        'fb_comment_id',
        'fb_comment_parent_id',
        'name',
        'email',
        'message',
        'approved_at',
        'is_spam',
        'depth',
        'ip_address',
        'user_agent',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'edited_at' => 'datetime',
            'is_spam' => 'boolean',
            'depth' => 'integer',
        ];
    }

    /**
     * Get the post that this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the comment this one replies to, if any.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the direct replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Scope to comments the public may see.
     *
     * `approved_at` is authoritative here. `is_spam` records why something is
     * hidden and must never be the sole basis for a visibility decision.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at')->where('is_spam', false);
    }

    /**
     * Determine whether this comment is publicly visible.
     */
    public function isVisible(): bool
    {
        return $this->approved_at !== null && ! $this->is_spam && ! $this->trashed();
    }

    /**
     * Determine whether this comment's message has been changed since posting.
     */
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Determine whether a viewer may edit or delete this comment.
     *
     * An admin may manage any comment. Anyone else must be the registered
     * author of a comment that is still publicly visible: an anonymous comment
     * has no `user_id`, so it belongs to nobody, whatever email it records.
     */
    public function isManageableBy(?User $user, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        return $user !== null
            && $this->user_id !== null
            && $this->user_id === $user->id
            && $this->isVisible();
    }

    /**
     * Determine whether this comment belongs to the post with the given slug.
     */
    public function isOnPost(string $slug): bool
    {
        return $this->post?->slug === $slug;
    }

    /**
     * Like isOnPost(), but also false when the post is a draft or scheduled,
     * so the comment routes answer for an unpublished post exactly as they do
     * for an address with no post.
     */
    public function isOnPublishedPost(string $slug): bool
    {
        return $this->isOnPost($slug) && $this->post->published;
    }

    /**
     * Hide this comment as spam.
     *
     * `approved_at` is what the public display query trusts, so it is cleared
     * here; `is_spam` records why.
     */
    public function markAsSpam(): void
    {
        $this->update([
            'is_spam' => true,
            'approved_at' => null,
        ]);

        CommentMarkedAsSpam::dispatch($this);
    }

    /**
     * Restore this comment, approving it as of now rather than reinstating
     * whatever approval time it held before.
     */
    public function markAsNotSpam(): void
    {
        $this->update([
            'is_spam' => false,
            'approved_at' => now(),
        ]);
    }

    /**
     * Determine whether this comment may still be replied to.
     */
    public function acceptsReplies(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }
}
