<?php

namespace App\Support;

use Canvas\Models\CanvasUser;
use Illuminate\Support\Facades\Auth;

class CanvasViewer
{
    /**
     * Whether the signed-in user is a blog author, i.e. has a Canvas account.
     * The single definition behind `@ifcanvasauthenticated` and the preview of
     * unpublished posts.
     */
    public static function isAuthor(): bool
    {
        return Auth::check() && CanvasUser::query()->where('user_id', Auth::id())->exists();
    }
}
