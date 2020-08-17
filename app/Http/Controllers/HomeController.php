<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Wink\WinkPost;

class HomeController extends Controller
{
  public function index()
  {
    $path = public_path() . "/config.json"; // ie: /var/www/laravel/public/filename.json
    $config = json_decode(file_get_contents($path), true);
    $latest_post = WinkPost::published()->live()->orderBy('publish_date','DESC')->get()[0];
    return view('home', [
      'blog' => $latest_post,
      'config' => $config,
    ]);
  }
}
