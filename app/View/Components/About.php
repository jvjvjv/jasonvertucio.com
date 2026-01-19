<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;

class About extends Component
{
    public $aboutMe;

    public function __construct($aboutMe)
    {
        $this->aboutMe = $aboutMe;
    }

    public function render(): View|Closure|string
    {
        return view('components.about');
    }
}
