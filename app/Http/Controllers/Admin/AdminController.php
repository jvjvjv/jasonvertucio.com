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

    public function resumeHub(Request $request): InertiaResponse
    {
        return Inertia::render('resume/Hub', [
            'navBlocks' => $this->navBlocksFor('/admin/resume', $request),
        ]);
    }
}
