<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AiToolsController extends Controller
{
    /**
     * Display the AI Tools hub page.
     */
    public function index(Request $request): InertiaResponse
    {
        $navBlocks = [
            [
                'can' => 'edit-resume',
                'href' => route('admin.resume.targeted.index'),
                'icon' => 'TrackChanges',
                'label' => 'Targeted Resume Builder',
                'description' => 'Use AI to tailor your resume for specific job postings',
            ],
            [
                'can' => null,
                'href' => route('admin.ai.systems.index'),
                'icon' => 'Memory',
                'label' => 'AI Systems',
                'description' => 'Manage AI providers, API keys, and feature defaults',
            ],
            [
                'can' => null,
                'href' => route('admin.ai.bots.index'),
                'icon' => 'SmartToy',
                'label' => 'AI Chat Bots',
                'description' => 'Create and manage reusable AI chat bots and their access rules',
            ],
            [
                'can' => null,
                'href' => route('admin.ai.conversations.index'),
                'icon' => 'Forum',
                'label' => 'AI Conversations',
                'description' => 'Browse AI conversations, review message history, and delete old threads',
            ],
            [
                'can' => null,
                'href' => route('admin.ai.memories.index'),
                'icon' => 'Psychology',
                'label' => 'AI Memory',
                'description' => 'View and manage learned insights from AI conversations',
            ],
            [
                'can' => null,
                'href' => route('admin.ai.job-url-parsers.index'),
                'icon' => 'Rule',
                'label' => 'Job URL Parsers',
                'description' => 'Review, approve, and edit saved parsers generated from job posting URLs',
            ],
        ];

        $user = $request->user();
        $filteredBlocks = array_values(array_filter($navBlocks, function ($block) use ($user) {
            return is_null($block['can']) || $user?->can($block['can']);
        }));

        return Inertia::render('ai/Index', [
            'navBlocks' => $filteredBlocks,
        ]);
    }
}
