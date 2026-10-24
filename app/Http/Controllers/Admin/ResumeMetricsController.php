<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ApplicationMetricsRequest;
use App\Services\ApplicationMetricsService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ResumeMetricsController extends BaseAdminController
{
    /**
     * Show the application metrics for the requested period (all time by
     * default), along with the period itself so the page can show what is
     * selected.
     */
    public function index(ApplicationMetricsRequest $request, ApplicationMetricsService $metrics): InertiaResponse
    {
        $period = $request->period();

        return Inertia::render('resume/metrics/Index', [
            ...$metrics->build($period['from'], $period['to']),
            'filter' => $request->filter(),
        ]);
    }
}
