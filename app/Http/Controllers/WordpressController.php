<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\IpBan;
use Log;

class WordpressController extends Controller
{
    public function index(Request $request)
    {
        $action = $request->input('action');
        return view('wp-login', ['action' => $action]);
    }

    public function ban(Request $request)
    {
        $ip = $request->ip();
        $method = $request->method();
        $url = $request->url();
        $body = json_encode($request->input());
        $log = $request->input('log');
        $pwd = $request->input('pwd');
        IpBan::firstOrCreate([
          'ip' => $ip,
          'banned_method' => $method,
          'banned_url' => $url,
          'banned_body' => $body,
        ]);

        Log::channel('slack_debug')->info("{$ip} just got banned! for trying to log in with username \"{$log}\" and password \"{$pwd}\"!");
        Log::notice("{$ip}just banned! for trying to log in with username \"{$log}\" and password \"{$pwd}\"!");
        $restricted_ips = IpBan::all()->map(function ($item) {
          return $item->ip;
        })->toArray();
        Cache::put('banned_ip_list', $restricted_ips, 300);

        abort(403);
    }
}
