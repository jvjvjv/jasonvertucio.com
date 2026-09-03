<?php

namespace App\View\Components;

use App\Models\Comment;
use Canvas\Models\Post;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class CommentThread extends Component
{
    public function __construct(public Post $post)
    {
    }

    /**
     * Build the post's comment tree from a single query.
     *
     * Every non-deleted comment is loaded, not only the visible ones: a hidden
     * comment with visible descendants still has to occupy its position in the
     * tree as a tombstone, or its replies would be orphaned.
     *
     * @return Collection<int, array{comment: Comment, children: Collection}>
     */
    public function tree(): Collection
    {
        $comments = Comment::query()
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
