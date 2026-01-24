<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResumeShareCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResumeShareCodeController extends Controller
{
    /**
     * Display a listing of all share codes with their usage.
     */
    public function index(): View
    {
        $codes = ResumeShareCode::withTrashed()
            ->withCount('views')
            ->with(['views' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.resume.index', compact('codes'));
    }

    /**
     * Store a newly created share code.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        $code = ResumeShareCode::generate($validated['expires_at'] ?? null);

        return redirect()
            ->route('admin.resume.index')
            ->with('success', "Share code '{$code->id}' created successfully.");
    }

    /**
     * Invalidate (soft delete) the specified share code.
     */
    public function destroy(string $code): RedirectResponse
    {
        $shareCode = ResumeShareCode::findOrFail($code);
        $shareCode->delete();

        return redirect()
            ->route('admin.resume.index')
            ->with('success', "Share code '{$code}' has been invalidated.");
    }
}
