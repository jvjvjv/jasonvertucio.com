<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ProvidesAdminNavigation;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Http\Controllers\Admin\AiToolsController as BaseAiToolsController;

class AiToolsController extends BaseAiToolsController
{
    use ProvidesAdminNavigation;

    /**
     * The package's page, plus the host's admin nav blocks.
     *
     * The parent declares index() with no parameters, so the request comes from
     * the helper rather than method injection to keep the signature compatible.
     */
    public function index(): InertiaResponse
    {
        return Inertia::render('ai/Index', [
            'navBlocks' => $this->navBlocksFor('/admin/ai', request()),
        ]);
    }
}
