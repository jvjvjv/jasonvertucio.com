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
                'subject' => $instance->envelope()->subject ?? 'No Subject',
                'isMarkdown' => $isMarkdown,
                'mailableClass' => $mailable,
            ]);
        } catch (\Exception $e) {
            return view('admin.mail-preview.show', [
                'mailable' => $class,
                'error' => $e->getMessage(),
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
            $isMarkdown = $this->isMarkdownMailable($instance);

            // For markdown emails, get the raw view content
            // For HTML emails, use the full render
            if ($isMarkdown) {
                $content = $instance->content();
                $preview = view($content->markdown, $content->with ?? [])->render();
            } else {
                $preview = $instance->render();
            }

            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #333; } h1, h2, h3, h4, h5, h6 { margin-top: 1.5em; margin-bottom: 0.5em; } h1 { font-size: 2em; font-weight: bold; } h2 { font-size: 1.75em; font-weight: bold; } h3 { font-size: 1.5em; font-weight: bold; } h4 { font-size: 1.25em; font-weight: bold; } h5 { font-size: 1.1em; font-weight: bold; } h6 { font-size: 1em; font-weight: bold; } p { margin-bottom: 1em; } a { color: #0066cc; text-decoration: underline; } a:hover { color: #0052a3; } strong, b { font-weight: 600; } em, i { font-style: italic; } ul, ol { margin-left: 2em; margin-bottom: 1em; } li { margin-bottom: 0.5em; } table { width: 100%; border-collapse: collapse; margin-bottom: 1em; } th, td { padding: 0.75em; text-align: left; border: 1px solid #ddd; } th { background-color: #f5f5f5; font-weight: 600; } code { background-color: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: "Monaco", "Courier New", monospace; } pre { background-color: #f5f5f5; padding: 1em; border-radius: 4px; overflow-x: auto; margin-bottom: 1em; } pre code { background: none; padding: 0; } blockquote { border-left: 4px solid #ddd; padding-left: 1em; margin-left: 0; margin-bottom: 1em; color: #666; } hr { border: none; border-top: 1px solid #ddd; margin: 2em 0; }</style></head><body>';

            if ($isMarkdown) {
                $html .= \Str::markdown($preview);
            } else {
                $html .= $preview;
            }

            $html .= '</body></html>';

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
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

    private function isMarkdownMailable($instance): bool {
        $content = $instance->content();
        return property_exists($content, 'markdown') && $content->markdown !== null;
    }
}
