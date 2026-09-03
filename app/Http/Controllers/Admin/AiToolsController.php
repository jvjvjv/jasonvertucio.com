<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProvidesAdminNavigation;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * code-talker 0.11.0 removed the package's admin `AiToolsController` (a
 * one-method landing-page controller). Reproduced here in full.
 */
class AiToolsController extends Controller
{
    use ProvidesAdminNavigation;

    public function index(): InertiaResponse
    {
        return Inertia::render('ai/Index', [
            'navBlocks' => $this->navBlocksFor('/admin/ai', request()),
        ]);
    }
}
