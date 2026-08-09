<?php

namespace App\Services;

use App\Models\User;

class AdminNavigationService
{
    /** @var array<string, mixed>|null */
    private ?array $config = null;

    private function load(): array
    {
        if ($this->config === null) {
            $this->config = json_decode(
                file_get_contents(resource_path('js/admin/navigation.json')),
                true
            );
        }

        return $this->config;
    }

    private function canAccess(array $item, ?User $user): bool
    {
        return $item['can'] === null || $user?->can($item['can']);
    }

    /**
     * Whether the item points at a route outside the Inertia app and must be
     * rendered as a plain anchor rather than an Inertia link.
     */
    private function isExternal(array $item): bool
    {
        return $item['external'] ?? false;
    }

    /**
     * Return filtered navigation blocks for the given admin route.
     *
     * @return array<int, array{href: string, icon: string, label: string, description: string, external: bool}>
     */
    public function getNavBlocksForRoute(string $route, ?User $user): array
    {
        $config = $this->load();

        $entry = collect($config['navigation'])->firstWhere('route', $route);

        if ($entry === null) {
            return [];
        }

        return array_values(
            array_map(
                fn (array $item) => [
                    'href' => $item['href'],
                    'icon' => $item['icon'],
                    'label' => $item['label'],
                    'description' => $item['description'],
                    'external' => $this->isExternal($item),
                ],
                array_filter(
                    $entry['navigationItems'],
                    fn (array $item) => $this->canAccess($item, $user)
                )
            )
        );
    }

    /**
     * Return AppBar navigation entries (with filtered children) for the given user.
     *
     * @return array<int, array{href: string, label: string, external: bool, children: array<int, array{href: string, label: string, external: bool}>}>
     */
    public function getAppBarItems(?User $user): array
    {
        $config = $this->load();

        $entries = array_filter(
            $config['navigation'],
            fn (array $entry) => $this->canAccess($entry, $user)
        );

        return array_values(array_map(
            fn (array $entry) => [
                'href' => $entry['route'],
                'label' => $entry['name'],
                'external' => $this->isExternal($entry),
                'children' => array_values(array_map(
                    fn (array $item) => [
                        'href' => $item['href'],
                        'label' => $item['label'],
                        'external' => $this->isExternal($item),
                    ],
                    array_filter(
                        $entry['navigationItems'],
                        fn (array $item) => $this->canAccess($item, $user)
                    )
                )),
            ],
            $entries
        ));
    }
}
