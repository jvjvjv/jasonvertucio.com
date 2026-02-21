<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteSettingsController extends Controller
{
    protected string $configPath;

    public function __construct()
    {
        $this->configPath = resource_path('config/config.json');
    }

    protected function readConfig(): array
    {
        return json_decode(file_get_contents($this->configPath), true);
    }

    protected function writeConfig(array $config): void
    {
        file_put_contents(
            $this->configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * GET /admin/site-settings
     */
    public function edit(): View
    {
        $config = $this->readConfig();

        return view('admin.site-settings.editor', [
            'links' => $config['links'] ?? [],
        ]);
    }

    /**
     * POST /admin/site-settings
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $permissionNames = \Spatie\Permission\Models\Permission::pluck('name')->all();
        $allowedCan = array_merge(['authenticated'], $permissionNames);

        $validated = $request->validate([
            'links'              => ['required', 'array'],
            'links.*.divider'    => ['nullable', 'string'],
            'links.*.href'       => ['nullable', 'string', 'max:500'],
            'links.*.label'      => ['nullable', 'string', 'max:100'],
            'links.*.ariaLabel'  => ['nullable', 'string', 'max:200'],
            'links.*.hover'      => ['nullable', 'string', 'max:500'],
            'links.*.target'     => ['nullable', 'string', 'in:_blank,_self'],
            'links.*.can'        => ['nullable', 'string', 'in:' . implode(',', $allowedCan)],
        ]);

        try {
            $config = $this->readConfig();

            $config['links'] = collect($validated['links'])->map(function (array $link): array {
                // Divider items are stored as { "divider": true }
                if (!empty($link['divider'])) {
                    return ['divider' => true];
                }

                $clean = [
                    'href'  => $link['href'],
                    'label' => $link['label'],
                ];

                if (!empty($link['ariaLabel'])) {
                    $clean['ariaLabel'] = $link['ariaLabel'];
                }
                if (!empty($link['hover'])) {
                    $clean['hover'] = $link['hover'];
                }
                if (!empty($link['target'])) {
                    $clean['target'] = $link['target'];
                }
                if (!empty($link['can'])) {
                    $clean['can'] = $link['can'];
                }

                return $clean;
            })->values()->all();

            $this->writeConfig($config);

            if ($request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Navigation saved.']);
            }

            return redirect()->route('admin.site-settings.edit')->with('success', 'Navigation links saved.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('admin.site-settings.edit')->with('error', $e->getMessage());
        }
    }
}
