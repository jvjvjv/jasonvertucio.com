<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Canvas\Events\PostViewed;
use Canvas\Models\Post;
use Canvas\Models\Tag;
use Canvas\Models\Topic;

use Auth;

class BlogController extends Controller
{

  public function index()
  {
    $list = Post::published()->orderBy('published_at','DESC')->get();
    return view('blog.list', [
        'list' => $list,
        'links' => [
            [ 'href' => '#', 'label' => 'Posts' ]
        ],
    ]);
  }

  public function topicsOrTags($slug) {
    $topic = Topic::with('posts')->where('slug',$slug)->first();
    if ($topic) {
      return view('blog.list', [
          'list' => $topic->posts->whereNotNull('published_at'),
          'links' => [
            [ 'href' => '#', 'label' => 'Posts' ]
          ],
        ]);
    } else {
      $tag = Tag::with('posts')->where('slug',$slug)->first();
      if (!$tag) {
        abort(404);
      } else {
        return view('blog.list', [
          'list' => $tag->posts->whereNotNull('published_at'),
          'links' => [
            [ 'href' => '#', 'label' => 'Posts' ]
          ],
        ]);
      }
    }
  }

  public function post($slug)
  {
    $post = Post::with(['user','tags','topic'])->where('slug',$slug)->first();
    if (!$post) {
      return $this->topicsOrTags($slug);
    }
    $auth = Auth::guard('canvas');
    if ($auth->check() && $post->user->id === $auth->user()->id) {
      // Do nothing
    } else {
      // If it's not the author, or if there IS no user
      event(new PostViewed($post));
    }
    return view('blog.single', [
      'post' => $post,
    ]);
  }
}
