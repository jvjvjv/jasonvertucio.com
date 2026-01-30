<?php

namespace App\Http\Middleware;

use App\Models\ResumeShareCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResumeShareCodeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Authenticated users must use their permissions - they cannot use share codes.
     * Unauthenticated users must have a valid share code.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('code');

        // If user is authenticated, they CANNOT use share codes
        if (Auth::check()) {
            if ($code) {
                // Authenticated users with a code get 403 Forbidden
                abort(403, 'Authenticated users cannot use share codes.');
            }

            // Normal auth flow - check permission
            if (Auth::user()->can('read-resume')) {
                return $next($request);
            }

            abort(403, 'You do not have permission to view the resume.');
        }

        // Unauthenticated user - check for valid share code in query param
        if ($code) {
            $shareCode = ResumeShareCode::valid()->find($code);
            if ($shareCode) {
                // Log the view
                $shareCode->views()->create([
                    'ip_address' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 512),
                ]);

                // Set session flag for subsequent requests (e.g., DOCX download flow)
                session(['resume_share_code' => $code]);

                return $next($request);
            }
        }

        // Check if session already has valid share code from previous request
        if (session('resume_share_code')) {
            $shareCode = ResumeShareCode::valid()->find(session('resume_share_code'));
            if ($shareCode) {
                return $next($request);
            }

            // Invalid/expired code in session - clear it
            session()->forget('resume_share_code');
        }

        // No valid access - return appropriate response
        if ($request->expectsJson() || $request->ajax()) {
            abort(401, 'Unauthorized');
        }

        // If code was provided but invalid, redirect to entry page with error
        if ($code) {
            return redirect()
                ->route('resume.enter-code')
                ->with('error', 'Invalid or expired access code. Please try again.');
        }

        // No code provided, redirect to entry page
        return redirect()->route('resume.enter-code');
    }
}
