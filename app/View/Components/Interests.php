<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Closure;
use Illuminate\Contracts\View\View;

class Interests extends Component
{
    public $interests;
    public $btc;

    public function __construct($interests, $btc = null)
    {
        $this->interests = $interests;
        $this->btc = $btc;
    }

    public function render(): View|Closure|string
    {
        return view('components.interests');
    }
}
