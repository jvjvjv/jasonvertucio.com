<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;

class FlushBlogFeedCache
{
    public function handle(): void
    {
        Cache::forget('blog.feed');
    }
}
