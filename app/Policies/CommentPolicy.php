<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can edit the comment's message.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->isManageableBy($user, $user->can('manage-blog'));
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->isManageableBy($user, $user->can('manage-blog'));
    }
}
