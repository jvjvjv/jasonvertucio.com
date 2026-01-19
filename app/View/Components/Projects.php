<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;

class Projects extends Component
{
    public $projects;

    public function __construct($projects)
    {
        $this->projects = $projects;
    }

    public function render(): View|Closure|string
    {
        return view('components.projects');
    }
}
