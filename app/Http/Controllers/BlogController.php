<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use \Wink\WinkPost;

class BlogController extends Controller
{
  public function __construct(Auth $auth)
  {
    $this->auth = $auth;
  }

  public function index()
  {
    $list = WinkPost::live()->published()->orderBy('publish_date','DESC')->get();
    return view('blog.list', [
      'list' => $list,
        'links' => [
          [ 'href' => '#', 'label' => 'Posts' ]
        ],
        'wink_authenticated' => $this->auth->guard('wink')->check()
    ]);
  }

  public function post($slug)
  {
    $post = WinkPost::where('slug',$slug)->firstOrFail();
    return view('blog.single', [
      'post' => $post,
      'wink_authenticated' => $this->auth->guard('wink')->check()
    ]);
  }
}
