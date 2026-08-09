<?php

namespace App\Concerns;

use App\Services\AdminNavigationService;
use Illuminate\Http\Request;

/**
 * Supplies the admin nav blocks Inertia pages expect.
 *
 * This lives in a trait rather than a base controller so controllers that must
 * extend a package controller (to inherit its behaviour) can still render the
 * host's admin navigation.
 */
trait ProvidesAdminNavigation
{
    /**
     * Return permission-filtered navigation blocks for the given admin route.
     *
     * @return array<int, array{href: string, icon: string, label: string, description: string}>
     */
    protected function navBlocksFor(string $route, Request $request): array
    {
        return app(AdminNavigationService::class)
            ->getNavBlocksForRoute($route, $request->user());
    }
}
