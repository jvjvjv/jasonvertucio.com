<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;

class Experience extends Component
{
    public $experience;

    public function __construct($experience)
    {
        $this->experience = $experience;
    }

    public function render(): View|Closure|string
    {
        return view('components.experience');
    }
}
