<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminController extends BaseAdminController
{
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Dashboard', [
            'navBlocks' => $this->navBlocksFor('/admin', $request),
        ]);
    }

    public function employmentHub(Request $request): InertiaResponse
    {
        return Inertia::render('employment/Hub', [
            'navBlocks' => $this->navBlocksFor('/admin/employment', $request),
        ]);
    }

    public function resumeHub(Request $request): InertiaResponse
    {
        return Inertia::render('resume/Hub', [
            'navBlocks' => $this->navBlocksFor('/admin/resume', $request),
        ]);
    }

    public function settingsHub(Request $request): InertiaResponse
    {
        return Inertia::render('site-settings/Hub', [
            'navBlocks' => $this->navBlocksFor('/admin/settings', $request),
        ]);
    }

    public function blogHub(Request $request): InertiaResponse
    {
        return Inertia::render('blog/Hub', [
            'navBlocks' => $this->navBlocksFor('/admin/blog', $request),
        ]);
    }
}
