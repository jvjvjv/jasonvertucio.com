<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Wink\WinkPost;
use Artisan;
use Cache;

class HomeController extends Controller
{

  public function index(Request $request)
  {
    $harvested = Cache::get('paper_harvested');
    if (!$harvested) {
      Artisan::call('paper:harvest',[
        "--limit" => 2
      ]);
      Cache::add('paper_harvested',true, 43200);
    } else {
      // Already harvested
    }


    $path = public_path() . "/config.json"; // ie: /var/www/laravel/public/filename.json
    $config = json_decode(file_get_contents($path), true);
    $latest_post = WinkPost::published()->live()->orderBy('publish_date','DESC')->get()[0];
    return view('home', [
      'blog' => $latest_post,
      'config' => $config,
      'wink_authenticated' => $request->is_wink_authenticated
    ]);
  }
}
