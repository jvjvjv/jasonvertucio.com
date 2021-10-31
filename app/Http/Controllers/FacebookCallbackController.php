<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class FacebookCallbackController extends Controller
{
    public function index(Request $request) {
        if ($request->hub_verify_token && $request->hub_verify_token !== 'jasonvertucioisamotherfuckingdog') {
            return response(null,401);
        }
        Log::info($request->method(), $request->input());

        if ($request->hub_mode === "subscribe") {
            return response($request->hub_challenge, 200);
        }

        return response([
            'method' => $request->method(),
            'data' => $request->input()
        ] ,200);
    }
}
