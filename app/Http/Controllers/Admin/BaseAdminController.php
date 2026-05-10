<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNavigationService;
use Illuminate\Http\Request;

abstract class BaseAdminController extends Controller
{
    public function __construct(protected AdminNavigationService $navigationService) { }

    /**
     * Return permission-filtered navigation blocks for the given admin route.
     *
     * @return array<int, array{href: string, icon: string, label: string, description: string}>
     */
    protected function navBlocksFor(string $route, Request $request): array
    {
        return $this->navigationService->getNavBlocksForRoute($route, $request->user());
    }
}
