<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Navigation extends Component
{
    public $config;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function link_label($link) {
        return $link['ariaLabel'] ?? $link['label'] . ": " . $link['hover'];
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.navigation');
    }

}
