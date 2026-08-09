<?php

namespace App\View\Components;

use Canvas\Models\Post;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LatestBlog extends Component
{
    public $post;

    public function __construct($post = null)
    {
        if ($post === null) {
            $this->post = Post::published()
                ->orderBy('published_at', 'DESC')
                ->first();
        } else {
            $this->post = $post;
        }
    }

    public function shouldDisplay(): bool
    {
        if (! $this->post) {
            return false;
        }

        return env('APP_DEBUG') || $this->post->published_at->diffInDays() < 90;
    }

    public function render(): View|Closure|string
    {
        return view('components.latest-blog');
    }
}
