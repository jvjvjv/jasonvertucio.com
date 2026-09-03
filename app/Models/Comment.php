<?php

namespace App\Models;

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
        return $this->approved_at !== null && ! $this->is_spam;
    }

    /**
     * Determine whether this comment may still be replied to.
     */
    public function acceptsReplies(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }
}
