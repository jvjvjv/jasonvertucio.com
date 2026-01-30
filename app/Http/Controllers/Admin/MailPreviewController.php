<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use Illuminate\Mail\Mailable;

class MailPreviewController {
    public function index() {
        $mailables = $this->discoverMailables();

        return view('admin.mail-preview.index', [
            'mailables' => $mailables,
        ]);
    }

    public function show($mailable) {
        $mailables = $this->discoverMailables();
        $class = collect($mailables)->firstWhere('class', $mailable);

        if (!$class) {
            abort(404, 'Mailable not found');
        }

        try {
            $instance = $this->instantiateMailable($class['class']);
            $isMarkdown = $this->isMarkdownMailable($instance);

            // For markdown emails, get the raw view content
            // For HTML emails, use the full render
            if ($isMarkdown) {
                $content = $instance->content();
                $preview = view($content->markdown, $content->with ?? [])->render();
            } else {
                $preview = $instance->render();
            }

            return view('admin.mail-preview.show', [
                'mailable' => $class,
                'preview' => $preview,
                'subject' => $instance->envelope()->subject ?? 'No Subject',
                'isMarkdown' => $isMarkdown,
            ]);
        } catch (\Exception $e) {
            return view('admin.mail-preview.show', [
                'mailable' => $class,
                'error' => $e->getMessage(),
            ]);
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

    private function isMarkdownMailable($instance): bool {
        $content = $instance->content();
        return property_exists($content, 'markdown') && $content->markdown !== null;
    }
}
