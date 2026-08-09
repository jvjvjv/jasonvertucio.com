<?php

namespace App\Http\Controllers\Admin;

use App\Services\TargetedResumeMetricsService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ResumeMetricsController extends BaseAdminController
{
    public function index(TargetedResumeMetricsService $metrics): InertiaResponse
    {
        return Inertia::render('resume/metrics/Index', $metrics->build());
    }
}
