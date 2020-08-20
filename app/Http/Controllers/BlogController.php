<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Wink\WinkPost;

class BlogController extends Controller
{

  public function index()
  {
    $list = WinkPost::live()->published()->orderBy('publish_date','DESC')->get();
    return view('blog.list', [
      'list' => $list,
        'links' => [
          [ 'href' => '#', 'label' => 'Posts' ]
        ],
        'wink_authenticated' => request()->is_wink_authenticated
    ]);
  }

  public function post($slug)
  {
    $post = WinkPost::where('slug',$slug)->firstOrFail();
    return view('blog.single', [
      'post' => $post,
      'wink_authenticated' => request()->is_wink_authenticated
    ]);
  }
}
