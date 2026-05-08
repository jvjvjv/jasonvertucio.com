<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use Illuminate\Mail\Mailable;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MailPreviewController {
    public function index(): InertiaResponse {
        $mailables = $this->discoverMailables();

        return Inertia::render('mail-preview/Index', [
            'mailables' => $mailables,
        ]);
    }

    public function show($mailable): InertiaResponse {
        $mailables = $this->discoverMailables();
        $class = collect($mailables)->firstWhere('class', $mailable);

        if (!$class) {
            abort(404, 'Mailable not found');
        }

        try {
            $instance = $this->instantiateMailable($class['class']);

            return Inertia::render('mail-preview/Show', [
                'mailable' => $class,
                'subject' => $instance->envelope()->subject ?? 'No Subject',
                'previewUrl' => route('admin.mail-preview.preview', $mailable),
            ]);
        } catch (\Exception $e) {
            return Inertia::render('mail-preview/Show', [
                'mailable' => $class,
                'error' => $e->getMessage(),
                'previewUrl' => null,
            ]);
        }
    }

    public function preview($mailable) {
        $mailables = $this->discoverMailables();
        $class = collect($mailables)->firstWhere('class', $mailable);

        if (!$class) {
            abort(404, 'Mailable not found');
        }

        try {
            $instance = $this->instantiateMailable($class['class']);
            $preview = $instance->render();

            return response($preview, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 500);
        }
    }

    private function discoverMailables(): array {
        $mailPath = app_path('Mail');
        $mailables = [];

        if (!File::exists($mailPath)) {
            return $mailables;
        }

        $files = File::allFiles($mailPath);

        foreach ($files as $file) {
            $class = 'App\\Mail\\' . $file->getFilenameWithoutExtension();

            if (class_exists($class) && is_subclass_of($class, Mailable::class)) {
                $reflection = new ReflectionClass($class);

                if (!$reflection->isAbstract()) {
                    $mailables[] = [
                        'name' => $file->getFilenameWithoutExtension(),
                        'class' => $class,
                        'file' => $file->getRelativePathname(),
                    ];
                }
            }
        }

        return collect($mailables)->sortBy('name')->values()->all();
    }

    private function instantiateMailable($class) {
        // Check for preview() factory method first
        if (method_exists($class, 'preview')) {
            return $class::preview();
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor || $constructor->getNumberOfRequiredParameters() === 0) {
            return new $class();
        }

        throw new \Exception("Mailable '{$class}' requires constructor parameters. Add a static preview() method to provide sample data.");
    }
}
