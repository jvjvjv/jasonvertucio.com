<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Navigation extends Component
{
    public $links;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($links)
    {
        $this->links = $links;
    }

    /**
     * The link's accessible name, used as each anchor's `title`.
     *
     * An explicit `ariaLabel` wins; otherwise the label is qualified by its
     * hover text. Both `ariaLabel` and `hover` are optional in
     * `resources/config/config.json` — "Log out" carries neither — so a link
     * missing them degrades to its bare label instead of raising an undefined
     * key, which Laravel escalates to an exception and which took down every
     * authenticated render of the page.
     *
     * @param array<string, mixed> $link
     */
    public function link_label(array $link): string
    {
        if (isset($link['ariaLabel'])) {
            return (string) $link['ariaLabel'];
        }

        $label = (string) ($link['label'] ?? '');

        if (! isset($link['hover'])) {
            return $label;
        }

        return $label.': '.$link['hover'];
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|\Closure|string
     */
    public function render()
    {
        return view('components.navigation');
    }
}
