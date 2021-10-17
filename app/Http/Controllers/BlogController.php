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
              [ 'href' => '/blog', 'label' => 'Posts' ],
              [ 'href' => '/blog/topics', 'label' => 'Topics' ],
              [ 'href' => '/blog/tags', 'label' => 'Tags' ]
          ],
      ]);
  }

  public function topics()
  {
      return view('blog.topics', [
          'list' => Topic::with('user','posts')->get(),
          'links' => 
              [ 
                  [ 'href' => '/blog', 'label' => 'Posts' ],
                  [ 'href' => '/blog/topics', 'label' => 'Topics' ],
                  [ 'href' => '/blog/tags', 'label' => 'Tags' ]
              ]
      ]);
  }

  public function tags()
  {
      return view('blog.tags', [
        'list' => Tag::with('user','posts')->get(),
        'links' => 
              [ 
                  [ 'href' => '/blog', 'label' => 'Posts' ],
                  [ 'href' => '/blog/topics', 'label' => 'Topics' ],
                  [ 'href' => '/blog/tags', 'label' => 'Tags' ]
              ]
      ]);
  }

  public function topicList($slug)
  {
      $topic = Topic::with('posts')->where('slug',$slug)->firstOrFail();
      return view('blog.list', [
          'list' => $topic->posts->whereNotNull('published_at'),
          'links' => [
              [ 'href' => '/blog', 'label' => 'Posts' ],
              [ 'href' => '/blog/topics', 'label' => 'Topics' ],
              [ 'href' => '/blog/tags', 'label' => 'Tags' ]
          ],
      ]);
  }

  public function tagList($slug)
  {
      $tag = Tag::with('posts')->where('slug',$slug)->firstOrFail();
      return view('blog.list', [
          'list' => $tag->posts->whereNotNull('published_at'),
          'links' => [
              [ 'href' => '/blog', 'label' => 'Posts' ],
              [ 'href' => '/blog/topics', 'label' => 'Topics' ],
              [ 'href' => '/blog/tags', 'label' => 'Tags' ]
          ],
      ]);
  }

  public function topicsOrTags($slug) {
      try {
        return $this->topicList($slug);
      } catch (\Exception $e) {
        return $this->tagList($slug);
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
