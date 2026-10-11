<?php

namespace App\View\Components;

use App\Models\Comment;
use App\Models\User;
use Canvas\Models\Post;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class CommentThread extends Component
{
    /**
     * The logged-in viewer, if any.
     */
    public ?User $viewer;

    /**
     * Whether the viewer may manage every comment.
     *
     * Resolved once here and handed to each node: a Gate check per comment
     * would run Keystone's permission lookup for every node in the thread.
     */
    public bool $viewerIsAdmin;

    public function __construct(public Post $post)
    {
        $this->viewer = auth()->user();
        $this->viewerIsAdmin = (bool) $this->viewer?->can('manage-blog');
    }

    /**
     * Build the post's comment tree from a single query.
     *
     * Every comment is loaded, soft-deleted ones included, not only the visible
     * ones: a hidden comment with visible descendants still has to occupy its
     * position in the tree as a tombstone, or its replies would be orphaned.
     *
     * @return Collection<int, array{comment: Comment, children: Collection}>
     */
    public function tree(): Collection
    {
        $comments = Comment::query()
            ->withTrashed()
            ->where('post_id', $this->post->id)
            ->orderBy('created_at')
            ->get();

        // Every comment in the thread is already in memory, so resolve the
        // parent relation from that collection. Left to itself, the backlink
        // on a deep comment would lazy-load its parent one query at a time.
        $byId = $comments->keyBy('id');

        $comments->each(function (Comment $comment) use ($byId): void {
            $comment->setRelation('parent', $comment->parent_id ? $byId->get($comment->parent_id) : null);
        });

        return $this->branch($comments, null);
    }

    /**
     * Assemble the children of one parent, pruning branches with nothing to show.
     *
     * @param  Collection<int, Comment>  $comments
     * @return Collection<int, array{comment: Comment, children: Collection}>
     */
    protected function branch(Collection $comments, ?int $parentId): Collection
    {
        return $comments
            ->where('parent_id', $parentId)
            ->map(fn (Comment $comment): array => [
                'comment' => $comment,
                'children' => $this->branch($comments, $comment->id),
            ])
            ->reject(fn (array $node): bool => ! $node['comment']->isVisible() && $node['children']->isEmpty())
            ->values();
    }

    /**
     * Whether the viewer may moderate comments.
     *
     * Resolved once for the whole thread and handed to each node: every Gate
     * check runs Keystone's permission lookup, so asking per comment would add
     * a query per comment.
     */
    public function canModerate(): bool
    {
        return auth()->user()?->can('moderate-comments') ?? false;
    }

    /**
     * Count the comments a visitor can actually read.
     */
    public function visibleCount(): int
    {
        return Comment::query()
            ->where('post_id', $this->post->id)
            ->visible()
            ->count();
    }

    public function render(): View|Closure|string
    {
        return view('components.comment-thread');
    }
}
