<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class FacebookCallbackController extends Controller
{
    public function index(Request $request) {
        Log::info($request->input());
        return response(null,204);
    }
}
