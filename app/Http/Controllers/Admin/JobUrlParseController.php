<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParseJobUrlRequest;
use App\Http\Requests\ReparseJobUrlRequest;
use Jvjvjv\CodeTalker\Models\AiSystem;
use App\Models\JobUrlParser;
use App\Services\JobUrlParseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobUrlParseController extends Controller
{
    public function __construct(
        private JobUrlParseService $parseService,
    ) {
    }

    /**
     * Parse a job URL and extract job information.
     */
    public function parse(ParseJobUrlRequest $request): JsonResponse
    {
        $aiSystem = AiSystem::findOrFail($request->validated('ai_system_id'));

        try {
            $result = $this->parseService->parseUrl(
                $request->validated('url'),
                $aiSystem,
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Confirm a parser as accurate (mark active).
     */
    public function confirmParser(JobUrlParser $parser): JsonResponse
    {
        $this->parseService->confirmParser($parser);

        return response()->json(['message' => 'Parser confirmed and activated.']);
    }

    /**
     * Reject a parser as inaccurate (keep inactive).
     */
    public function rejectParser(Request $request, JobUrlParser $parser): JsonResponse
    {
        $this->parseService->rejectParser(
            $parser,
            $request->input('feedback'),
        );

        return response()->json(['message' => 'Parser rejected.']);
    }

    /**
     * Re-parse using stored HTML with user feedback.
     */
    public function reparse(ReparseJobUrlRequest $request, JobUrlParser $parser): JsonResponse
    {
        $aiSystem = AiSystem::findOrFail($request->validated('ai_system_id'));

        try {
            $result = $this->parseService->reparseWithFeedback(
                $parser,
                $request->validated('feedback'),
                $aiSystem,
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
