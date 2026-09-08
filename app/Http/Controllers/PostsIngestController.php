<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PostsIngestController extends Controller
{
    public function store(Request $request): Response
    {
        Log::channel('posts')->info('POST /posts', $request->all());

        return response()->noContent();
    }
}
