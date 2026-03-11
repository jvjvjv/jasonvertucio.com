<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AiToolsController extends Controller
{
    /**
     * Display the AI Tools hub page.
     */
    public function index(): View
    {
        return view('admin.ai.index');
    }
}
