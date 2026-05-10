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

    /**
     * Return filtered navigation blocks for the given admin route.
     *
     * @return array<int, array{href: string, icon: string, label: string, description: string}>
     */
    public function getNavBlocksForRoute(string $route, ?User $user): array
    {
        $config = $this->load();

        $entry = collect($config['navigation'])
            ->firstWhere('route', $route);

        if ($entry === null) {
            return [];
        }

        return array_values(
            array_map(
                fn (array $item) => [
                    'href'        => $item['href'],
                    'icon'        => $item['icon'],
                    'label'       => $item['label'],
                    'description' => $item['description'],
                ],
                array_filter(
                    $entry['navigationItems'],
                    fn (array $item) => $item['can'] === null || $user?->can($item['can'])
                )
            )
        );
    }

    /**
     * Return AppBar navigation entries (with filtered children) for the given user.
     *
     * @return array<int, array{href: string, label: string, children: array<int, array{href: string, label: string}>}>
     */
    public function getAppBarItems(?User $user): array
    {
        $config = $this->load();

        $entries = array_filter(
            $config['navigation'],
            fn (array $entry) => $entry['can'] === null || $user?->can($entry['can'])
        );

        return array_values(array_map(
            fn (array $entry) => [
                'href'     => $entry['route'],
                'label'    => $entry['name'],
                'children' => array_values(array_map(
                    fn (array $item) => [
                        'href'  => $item['href'],
                        'label' => $item['label'],
                    ],
                    array_filter(
                        $entry['navigationItems'],
                        fn (array $item) => $item['can'] === null || $user?->can($item['can'])
                    )
                )),
            ],
            $entries
        ));
    }
}
