<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): View
    {
        return view('admin.index');
    }

    /**
     * Display the resume administration hub.
     */
    public function resumeHub(): View
    {
        return view('admin.resume.hub');
    }
}
