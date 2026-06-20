<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ResumeVersionServiceContract;
use App\Http\Controllers\Controller;
use App\Mail\ResumeShareCodeCreated;
use App\Models\ResumeShareCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ResumeShareCodeController extends Controller
{
    public function __construct(
        protected ResumeVersionServiceContract $versionService,
    ) {}

    /**
     * Check if mail is properly configured.
     */
    protected function isMailConfigured(): bool
    {
        $driver = config('mail.driver');

        if (in_array($driver, ['array', 'log'], true)) {
            return true;
        }

        $host = config('mail.host');
        $username = config('mail.username');

        return ! empty($host) && ! empty($username);
    }

    /**
     * Display a listing of all share codes with their usage.
     */
    public function index(): InertiaResponse
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
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $codes->each(function ($code) {
            $code->is_trashed = $code->trashed();
            $code->is_expired = ! $code->trashed() && $code->isExpired();
            $code->resume_url = url('/resume?code='.$code->id);
            $code->created_at_formatted = $code->created_at->format('M j, Y g:i A');
            $code->expires_at_formatted = $code->expires_at?->format('M j, Y');
            $code->views->each(function ($view) {
                $view->created_at_formatted = $view->created_at->format('M j, Y g:i A');
            });
            $code->downloads->each(function ($download) {
                $download->created_at_formatted = $download->created_at->format('M j, Y g:i A');
            });
        });

        return Inertia::render('resume/Codes', [
            'codes' => $codes,
            'mailConfigured' => $this->isMailConfigured(),
            'todayDate' => date('Y-m-d'),
        ]);
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
                $version = $this->versionService->getCurrentVersion();
                Mail::to($email)->queue(new ResumeShareCodeCreated($code, $version));
                $code->update(['email_sent' => true]);

                return redirect()
                    ->route('admin.resume.codes.index')
                    ->with('success', "Share code '{$code->id}' created successfully. Email notification sent to {$email}.");
            } catch (\Exception $e) {
                Log::error('Failed to queue share code email', [
                    'code' => $code->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return redirect()
                    ->route('admin.resume.codes.index')
                    ->with('warning', "Share code '{$code->id}' created, but email notification failed. Please try again or contact support.");
            }
        }

        return redirect()
            ->route('admin.resume.codes.index')
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
            ->route('admin.resume.codes.index')
            ->with('success', "Share code '{$code}' has been invalidated.");
    }
}
