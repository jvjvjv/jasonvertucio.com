<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ResumeShareCodeCreated;
use App\Models\ResumeShareCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ResumeShareCodeController extends Controller
{
    /**
     * Check if mail is properly configured.
     */
    protected function isMailConfigured(): bool
    {
        $host = config('mail.host');
        $username = config('mail.username');

        return !empty($host) && !empty($username);
    }
    /**
     * Display a listing of all share codes with their usage.
     */
    public function index(): View
    {
        $codes = ResumeShareCode::withTrashed()
            ->withCount('views')
            ->withCount('downloads')
            ->with([
                'views' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'downloads' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.resume.index', compact('codes'))
            ->with('mailConfigured', $this->isMailConfigured());
    }

    /**
     * Store a newly created share code.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'send_email' => 'boolean',
        ]);

        $mailConfigured = $this->isMailConfigured();
        $sendEmail = $validated['send_email'] ?? false;
        $email = $validated['email'] ?? null;
        $notifyOnUpdate = false;

        // Only send email if mail is configured, email is provided, and checkbox is checked
        if ($mailConfigured && $sendEmail && $email) {
            $notifyOnUpdate = true;
        }

        $code = ResumeShareCode::generate(
            $validated['expires_at'] ?? null,
            $validated['name'],
            $email,
            $notifyOnUpdate
        );

        // Send email if configured and requested
        if ($mailConfigured && $sendEmail && $email) {
            try {
                $version = json_decode(require_once(resource_path('/resume/version.json')));
                Mail::to($email)->queue(new ResumeShareCodeCreated($code, $version));
                $code->update(['email_sent' => true]);

                return redirect()
                    ->route('admin.resume.index')
                    ->with('success', "Share code '{$code->id}' created successfully. Email notification sent to {$email}.");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to queue share code email', [
                    'code' => $code->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return redirect()
                    ->route('admin.resume.index')
                    ->with('warning', "Share code '{$code->id}' created, but email notification failed. Please try again or contact support.");
            }
        }

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
