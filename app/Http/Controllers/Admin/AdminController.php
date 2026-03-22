<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request): InertiaResponse
    {
        $navBlocks = [
            [
                'can' => null,
                'href' => route('admin.resume.index'),
                'icon' => 'Description',
                'label' => 'Resume Management',
                'description' => 'Edit resume content, manage share codes, and generate documents',
            ],
            [
                'can' => null,
                'href' => route('admin.cover-letters.index'),
                'icon' => 'DriveFileRenameOutline',
                'label' => 'Cover Letter Management',
                'description' => 'Create and manage cover letters with automatic DOCX and PDF generation',
            ],
            [
                'can' => 'manage-ai-tools',
                'href' => route('admin.ai.index'),
                'icon' => 'SmartToy',
                'label' => 'AI Tools',
                'description' => 'Manage AI systems, targeted resume builder, and AI cover letter generation',
            ],
            [
                'can' => null,
                'href' => route('admin.site-settings.edit'),
                'icon' => 'PushPin',
                'label' => 'Site Navigation',
                'description' => 'Manage sidebar navigation links and their order',
            ],
            [
                'can' => null,
                'href' => route('admin.mail-preview.index'),
                'icon' => 'Inbox',
                'label' => 'Mail preview',
                'description' => 'See how emails might be rendered, right here in the browser!',
            ],
        ];

        $user = $request->user();
        $filteredBlocks = array_values(array_filter($navBlocks, function ($block) use ($user) {
            return is_null($block['can']) || $user?->can($block['can']);
        }));

        return Inertia::render('Dashboard', [
            'navBlocks' => $filteredBlocks,
        ]);
    }

    /**
     * Display the resume administration hub.
     */
    public function resumeHub(): View
    {
        return view('admin.resume.hub');
    }
}
