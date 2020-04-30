<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\IpBan;

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
        IpBan::firstOrCreate([
          'ip' => $ip,
          'banned_method' => $method,
          'banned_url' => $url,
          'banned_body' => $body,
        ]);

        abort(403);
    }
}
