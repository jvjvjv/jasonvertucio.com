<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateJobUrlParserRequest;
use App\Models\JobUrlParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\DomCrawler\Crawler;

class JobUrlParserController extends Controller
{
    /**
     * Display a paginated list of saved job URL parsers.
     */
    public function index(Request $request): InertiaResponse
    {
        $query = JobUrlParser::query()->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('domain')) {
            $query->where('domain', $request->string('domain'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder->where('domain', 'like', '%' . $search . '%')
                    ->orWhere('job_title_selector', 'like', '%' . $search . '%')
                    ->orWhere('company_name_selector', 'like', '%' . $search . '%')
                    ->orWhere('job_location_selector', 'like', '%' . $search . '%')
                    ->orWhere('job_description_selector', 'like', '%' . $search . '%')
                    ->orWhere('ai_reasoning', 'like', '%' . $search . '%');
            });
        }

        $parsers = $query
            ->paginate(50)
            ->withQueryString()
            ->through(fn (JobUrlParser $parser) => [
                'id' => $parser->id,
                'domain' => $parser->domain,
                'status' => $parser->status,
                'company_name_selector' => $parser->company_name_selector,
                'job_title_selector' => $parser->job_title_selector,
                'job_location_selector' => $parser->job_location_selector,
                'job_description_selector' => $parser->job_description_selector,
                'reasoning_preview' => filled($parser->ai_reasoning)
                    ? str($parser->ai_reasoning)->limit(120)->toString()
                    : null,
                'updated_at' => $parser->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('ai/job-url-parsers/Index', [
            'parsers' => $parsers,
            'filters' => $request->only(['status', 'domain', 'search']),
            'domains' => JobUrlParser::query()->distinct()->orderBy('domain')->pluck('domain')->values(),
        ]);
    }

    /**
     * Show the parser edit form.
     */
    public function edit(JobUrlParser $jobUrlParser): InertiaResponse
    {
        return Inertia::render('ai/job-url-parsers/Edit', [
            'parser' => [
                'id' => $jobUrlParser->id,
                'domain' => $jobUrlParser->domain,
                'status' => $jobUrlParser->status,
                'company_name_selector' => $jobUrlParser->company_name_selector,
                'job_title_selector' => $jobUrlParser->job_title_selector,
                'job_location_selector' => $jobUrlParser->job_location_selector,
                'job_description_selector' => $jobUrlParser->job_description_selector,
                'ai_reasoning' => $jobUrlParser->ai_reasoning,
                'html' => $jobUrlParser->html,
            ],
        ]);
    }

    /**
     * Update a saved parser.
     */
    public function update(UpdateJobUrlParserRequest $request, JobUrlParser $jobUrlParser): RedirectResponse
    {
        $jobUrlParser->update($request->validated());

        return redirect()->route('admin.ai.job-url-parsers.edit', $jobUrlParser)
            ->with('success', 'Job URL parser updated successfully.');
    }

    /**
     * Preview extraction results for current selectors and HTML.
     */
    public function preview(Request $request, JobUrlParser $jobUrlParser): JsonResponse
    {
        $validated = $request->validate([
            'html' => ['nullable', 'string'],
            'company_name_selector' => ['nullable', 'string', 'max:255'],
            'job_title_selector' => ['nullable', 'string', 'max:255'],
            'job_location_selector' => ['nullable', 'string', 'max:255'],
            'job_description_selector' => ['nullable', 'string', 'max:255'],
        ]);

        $html = (string) ($validated['html'] ?? $jobUrlParser->html ?? '');

        if (blank($html)) {
            return response()->json([
                'message' => 'No HTML is available to test selectors against.',
            ], 422);
        }

        $crawler = new Crawler($html);
        $mapping = [
            'job_title' => (string) ($validated['job_title_selector'] ?? ''),
            'company_name' => (string) ($validated['company_name_selector'] ?? ''),
            'job_location' => (string) ($validated['job_location_selector'] ?? ''),
            'job_description' => (string) ($validated['job_description_selector'] ?? ''),
        ];

        $results = [];
        $errors = [];

        foreach ($mapping as $field => $selector) {
            if ($selector === '') {
                $results[$field] = '';
                continue;
            }

            try {
                $results[$field] = trim($crawler->filter($selector)->first()->text(''));
            } catch (\Throwable $exception) {
                $results[$field] = '';
                $errors[$field] = $exception->getMessage();
            }
        }

        return response()->json([
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Mark one parser as active and deactivate all others.
     */
    public function approve(JobUrlParser $jobUrlParser): RedirectResponse
    {
        JobUrlParser::query()
            ->where('id', '!=', $jobUrlParser->id)
            ->update(['status' => 'inactive']);

        $jobUrlParser->update(['status' => 'active']);

        return redirect()->route('admin.ai.job-url-parsers.index')
            ->with('success', "Parser #{$jobUrlParser->id} approved. All other parsers were set to inactive.");
    }

    /**
     * Keep a parser inactive.
     */
    public function reject(JobUrlParser $jobUrlParser): RedirectResponse
    {
        $jobUrlParser->update(['status' => 'inactive']);

        return redirect()->route('admin.ai.job-url-parsers.index')
            ->with('success', "Parser #{$jobUrlParser->id} marked as inactive.");
    }

    /**
     * Delete an inactive parser.
     */
    public function destroy(JobUrlParser $jobUrlParser): RedirectResponse
    {
        abort_if($jobUrlParser->status === 'active', 403, 'Active parsers cannot be deleted.');

        $jobUrlParser->delete();

        return redirect()->route('admin.ai.job-url-parsers.index')
            ->with('success', "Parser #{$jobUrlParser->id} deleted.");
    }
}
