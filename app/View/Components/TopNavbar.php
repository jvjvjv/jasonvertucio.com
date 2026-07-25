<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TopNavbar extends Component
{
    public array $navLinks;

    public function __construct()
    {
        $config = json_decode(file_get_contents(resource_path('config/config.json')), true);
        $this->navLinks = $config['links'] ?? [];
    }

    public function render(): View|Closure|string
    {
        return view('components.top-navbar');
    }
}
