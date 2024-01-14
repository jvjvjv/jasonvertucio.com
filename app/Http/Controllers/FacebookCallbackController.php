<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookCallbackController extends Controller
{
    public function index(Request $request)
    {
        Log::info($request->method(), $request->input());

        if ($request->hub_verify_token && $request->hub_verify_token !== 'jasonvertucioisamotherfuckingdog') {
            return response(null, 401);
        }

        if ($request->hub_mode === "subscribe") {
            return response($request->hub_challenge, 200);
        }

        $input = $request->input();
        $field = $input['field'];
        $value = $input['value'];

        switch ($field) {
            case 'plugin_comment':
                Comment::create([
                    'fb_comment_id' => $value['id'],
                    'name' => $value['from']['name'],
                    'email' => $value['from']['id'],
                    'message' => $value['message'],
                    'created_at' => $value['created_time']
                ]);
                break;
            case 'plugin_comment_reply':
                $old_comment = Comment::firstWhere('fb_comment_id', $value['parent']['id']);
                $old_comment_id = $old_comment->id ?? null;
                Comment::create([
                    'parent_id' => $old_comment_id,
                    'fb_comment_id' => $value['id'],
                    'fb_comment_parent_id' => $value['parent']['id'],
                    'name' => $value['from']['name'],
                    'email' => $value['from']['id'],
                    'message' => $value['message'],
                    'created_at' => $value['created_time']
                ]);
                break;
            default:
                break;
        }

        return response([
            'method' => $request->method(),
            'data' => $request->input()
        ], 200);
    }
}
