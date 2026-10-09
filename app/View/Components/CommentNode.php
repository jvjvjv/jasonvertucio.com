<?php

namespace App\View\Components;

use App\Models\Comment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class CommentNode extends Component
{
    /**
     * @param  Collection<int, array{comment: Comment, children: Collection}>  $children
     */
    public function __construct(
        public Comment $comment,
        public Collection $children,
        public string $slug,
        public ?User $viewer = null,
        public bool $viewerIsAdmin = false,
    ) {
    }

    /**
     * Whether the viewer may edit and delete this comment.
     */
    public function canManage(): bool
    {
        return $this->comment->isManageableBy($this->viewer, $this->viewerIsAdmin);
    }

    /**
     * Whether a rejected edit is being returned to this comment's edit form.
     */
    public function isEditTarget(): bool
    {
        return (string) old('editing') === (string) $this->comment->id;
    }

    /**
     * The indentation level this comment renders at.
     *
     * True depth still governs reply eligibility; only the visual offset is
     * capped, so a deep thread stays readable on a narrow screen.
     */
    public function visualDepth(): int
    {
        return min($this->comment->depth, Comment::MAX_VISUAL_DEPTH);
    }

    /**
     * Whether this comment sits deeper than the indentation can express.
     */
    public function needsParentBacklink(): bool
    {
        return $this->comment->depth > Comment::MAX_VISUAL_DEPTH
            && $this->comment->parent !== null;
    }

    public function render(): View|Closure|string
    {
        return view('components.comment-node');
    }
}
