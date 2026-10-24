<?php

namespace App\Http\Controllers;

use App\Services\Mcp\PublicToolDocumentation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public page describing this site's AI work, which doubles as the
 * human-readable documentation for the `/mcp` endpoint.
 *
 * The page has two halves with different lifecycles, and they are sourced
 * differently on purpose:
 *
 * - The **narrative** is a Markdown document, so revising it is an edit to
 *   content rather than to code. Same shape as {@see LegalController}.
 * - The **endpoint documentation** is generated from the MCP server's own tool
 *   roster, so it cannot drift from what the endpoint actually serves.
 */
class AiPageController extends Controller
{
    public function __construct(private PublicToolDocumentation $toolDocumentation) {}

    public function show(): View
    {
        $path = resource_path('content/ai.md');

        if (! File::exists($path)) {
            throw new NotFoundHttpException('The AI page document is missing.');
        }

        $html = Str::markdown(File::get($path), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('ai', [
            'title' => 'AI Work',
            'content' => new HtmlString($html),
            'tools' => $this->toolDocumentation->forServer(),
            'endpointUrl' => url('/mcp'),
        ]);
    }
}
