<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;
use Canvas\Models\Post;

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
        if (!$this->post) {
            return false;
        }

        return env('APP_DEBUG') || $this->post->published_at->diffInDays() < 90;
    }

    public function render(): View|Closure|string
    {
        return view('components.latest-blog');
    }
}
