<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return $this->render('privacy-policy', 'Privacy Policy');
    }

    public function terms(): View
    {
        return $this->render('terms-of-service', 'Terms of Service');
    }

    /**
     * Render a Markdown document from resources/legal as an HTML page.
     */
    private function render(string $document, string $title): View
    {
        $path = resource_path("legal/{$document}.md");

        if (! File::exists($path)) {
            throw new NotFoundHttpException("Legal document [{$document}] is missing.");
        }

        $html = Str::markdown(File::get($path), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('legal', [
            'title' => $title,
            'content' => new HtmlString($this->wrapTables($html)),
        ]);
    }

    /**
     * Wrap rendered tables so wide ones scroll inside the page instead of
     * forcing the whole document to scroll horizontally on small screens.
     */
    private function wrapTables(string $html): string
    {
        return (string) preg_replace(
            '/<table>(.*?)<\/table>/s',
            '<div class="table-scroll"><table>$1</table></div>',
            $html
        );
    }
}
