<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;

class Job extends Component
{
    public $job;

    public function __construct($job)
    {
        $this->job = $job;
    }

    public function render(): View|Closure|string
    {
        return view('components.job');
    }
}
