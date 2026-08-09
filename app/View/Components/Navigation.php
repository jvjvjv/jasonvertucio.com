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

    public function link_label($link)
    {
        return $link['ariaLabel'] ?? $link['label'].': '.$link['hover'];
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
