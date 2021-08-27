<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Wink\WinkPost;
use GuzzleHttp\Client as Guzzle;
use Artisan;
use Cache;

class HomeController extends Controller
{

  public function index(Request $request)
  {
    $btc = Cache::get('btc');
    if (!$btc) {
      $client = new Guzzle;
      $response = $client->get("https://api.coindesk.com/v1/bpi/currentprice.json");
      try {
        $btc = json_decode((string)$response->getBody());
        Cache::add('btc', $btc, 60);
      } catch (\Exception $e) {
        //
      }
    }
    $harvested = Cache::get('paper_harvested');
    if (!$harvested) {
      Artisan::call('paper:harvest',[
        "--limit" => 2
      ]);
      Cache::add('paper_harvested',true, 43200);
    } else {
      // Already harvested
    }

    $path =resource_path() . "/config/config.json"; // ie: /var/www/laravel/public/filename.json
    $config = json_decode(file_get_contents($path), true);
    $latest_post = WinkPost::published()->live()->orderBy('publish_date','DESC')->get()[0];
    return view('home', [
      'blog' => $latest_post,
      'config' => $config,
      'btc' => $btc,
    ]);
  }
}
