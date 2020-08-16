<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Wink\WinkPost;

class HomeController extends Controller
{
  public function index()
  {
    $latest_post = WinkPost::published()->live()->orderBy('publish_date','DESC')->get()[0];
    return view('home', [
      'blog' => $latest_post
    ]);
  }
}
