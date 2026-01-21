<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginMethodsController extends Controller
{
    /**
     * Check email and return available authentication methods.
     *
     * This endpoint intentionally reveals whether a user exists
     * as part of the two-step login flow design.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'user_exists' => false,
                'methods' => ['password'],
                'require_2fa' => false,
                'blocked' => false,
            ]);
        }

        $methods = $user->getAvailableAuthMethods();

        // Check if blocked (no methods and no password required)
        if (empty($methods) && !$user->require_password) {
            return response()->json([
                'user_exists' => true,
                'methods' => [],
                'require_2fa' => false,
                'blocked' => true,
            ]);
        }

        // Check if 2FA required (password + any allow flag)
        $require2fa = $user->require_password && (
            $user->allow_passkey_login ||
            $user->allow_totp_login
        );

        return response()->json([
            'user_exists' => true,
            'methods' => $methods,
            'require_2fa' => $require2fa,
            'blocked' => false,
        ]);
    }
}
