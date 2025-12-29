<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Canvas\Models\Post;
use GuzzleHttp\Client as Guzzle;
use Artisan;
use Cache;

class HomeController extends Controller {

    public function index(Request $request) {

        $path = resource_path() . "/config/config.json"; // ie: /var/www/laravel/public/filename.json
        $config = json_decode(file_get_contents($path), true);
        $latest_post = Post::published()->orderBy('published_at', 'DESC')->first();
        return view('home', [
            'blog' => $latest_post,
            'config' => $config,
            'btc' => null,
        ]);
    }
}
