<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PostsIngestController extends Controller
{
    public function store(Request $request): Response
    {
        $maxBody = (int) config('posts-ingest.max_body_bytes');

        if ((int) $request->header('Content-Length', 0) > $maxBody || strlen($request->getContent()) > $maxBody) {
            return response('', 413);
        }

        Log::channel('posts')->info('POST /posts', $this->entry($request->all()));

        return response()->noContent();
    }

    /**
     * The payload as logged: as received when it fits, otherwise its truncated
     * JSON with a marker so a reader knows it is incomplete.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function entry(array $payload): array
    {
        $maxEntry = (int) config('posts-ingest.max_entry_bytes');
        $encoded = (string) json_encode($payload);

        if (strlen($encoded) <= $maxEntry) {
            return $payload;
        }

        return [
            'payload' => mb_strcut($encoded, 0, $maxEntry),
            'truncated' => true,
        ];
    }
}
