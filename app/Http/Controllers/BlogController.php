<?php

namespace App\Http\Controllers;

use Canvas\Events\PostViewed;
use Canvas\Models\Post;
use Canvas\Models\Tag;
use Canvas\Models\Topic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    public function index()
    {
        $list = Post::published()->orderBy('published_at', 'DESC')->get();

        return view('blog.list', [
            'list' => $list,
            'links' => [
                ['href' => '/blog', 'label' => 'Posts'],
                ['href' => '/blog/topics', 'label' => 'Topics'],
                ['href' => '/blog/tags', 'label' => 'Tags'],
            ],
        ]);
    }

    public function topics()
    {
        return view('blog.topics', [
            'list' => Topic::with('user', 'posts')->get(),
            'links' => [
                ['href' => '/blog', 'label' => 'Posts'],
                ['href' => '/blog/topics', 'label' => 'Topics'],
                ['href' => '/blog/tags', 'label' => 'Tags'],
            ],
        ]);
    }

    public function tags()
    {
        return view('blog.tags', [
            'list' => Tag::with('user', 'posts')->get(),
            'links' => [
                ['href' => '/blog', 'label' => 'Posts'],
                ['href' => '/blog/topics', 'label' => 'Topics'],
                ['href' => '/blog/tags', 'label' => 'Tags'],
            ],
        ]);
    }

    public function topicList(string $slug)
    {
        $topic = Topic::with('posts')->where('slug', $slug)->firstOrFail();

        return view('blog.list', [
            'list' => $topic->posts->whereNotNull('published_at'),
            'links' => [
                ['href' => '/blog', 'label' => 'Posts'],
                ['href' => '/blog/topics', 'label' => 'Topics'],
                ['href' => '/blog/tags', 'label' => 'Tags'],
            ],
        ]);
    }

    public function tagList(string $slug)
    {
        $tag = Tag::with('posts')->where('slug', $slug)->firstOrFail();

        return view('blog.list', [
            'list' => $tag->posts->whereNotNull('published_at'),
            'links' => [
                ['href' => '/blog', 'label' => 'Posts'],
                ['href' => '/blog/topics', 'label' => 'Topics'],
                ['href' => '/blog/tags', 'label' => 'Tags'],
            ],
        ]);
    }

    public function topicsOrTags($slug)
    {
        try {
            return $this->topicList($slug);
        } catch (\Exception $e) {
            return $this->tagList($slug);
        }
    }

    public function feed()
    {
        $posts = Cache::remember('blog.feed', now()->addDay(), function () {
            return Post::published()->with('user')->orderBy('published_at', 'DESC')->limit(20)->get();
        });

        return response()
            ->view('blog.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function post($slug)
    {
        $post = Post::with(['user', 'tags', 'topic'])->where('slug', $slug)->first();
        if (! $post) {
            return $this->topicsOrTags($slug);
        }
        $authorId = $post->getRawOriginal('user_id');
        $viewerIsAuthor = Auth::check() && $authorId !== null && Auth::id() === $authorId;

        if (! $viewerIsAuthor) {
            event(new PostViewed(
                post: $post,
                ip: request()->ip(),
                agent: request()->userAgent(),
                referer: request()->header('referer'),
            ));
        }

        return view('blog.single', [
            'post' => $post,
        ]);
    }
}
