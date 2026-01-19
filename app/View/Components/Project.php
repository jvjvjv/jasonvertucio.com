<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;

class Project extends Component
{
    public $project;

    public function __construct($project)
    {
        $this->project = $project;
    }

    public function render(): View|Closure|string
    {
        return view('components.project');
    }
}
