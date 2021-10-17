<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Canvas\Models\Post;

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

  public function post($slug)
  {
    $post = Post::where('slug',$slug)->firstOrFail();
    return view('blog.single', [
      'post' => $post,
    ]);
  }
}
