<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class FacebookCallbackController extends Controller
{
    public function index(Request $request) {
        Log::info($request->method(), $request->input());
        return response([
            'method' => $request->method(),
            'data' => $request->input()
        ] ,200);
    }
}
