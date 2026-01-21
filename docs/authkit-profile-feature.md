# AuthKit Profile Page Feature Specification

**Version:** 1.0
**Date:** January 2026
**Status:** Proposed

## Overview

This document specifies a new Profile Page feature for the AuthKit package that allows users to view their account information, manage authentication methods (password, TOTP 2FA, passkeys), and configure passwordless login options.

---

## Table of Contents

1. [Goals](#goals)
2. [User Stories](#user-stories)
3. [Feature Requirements](#feature-requirements)
4. [Database Schema](#database-schema)
5. [Configuration](#configuration)
6. [Routes](#routes)
7. [Controllers](#controllers)
8. [Views & Components](#views--components)
9. [JavaScript Requirements](#javascript-requirements)
10. [Security Considerations](#security-considerations)
11. [Integration Guide](#integration-guide)

---

## Goals

1. Provide a unified profile page where users can manage all authentication-related settings
2. Allow users to enable/disable passwordless authentication via passkeys or TOTP
3. Display user roles and permissions (when using Spatie Laravel Permission)
4. Maintain AuthKit's philosophy of optional, configurable features
5. Provide customizable Blade components that can be styled to match any application

---

## User Stories

### US-1: View Profile Information
> As a user, I want to view my account information (name, email) so I can verify my account details.

### US-2: View Roles & Permissions
> As a user, I want to see what roles and permissions I have so I understand what actions I'm authorized to perform.

### US-3: Change Password
> As a user, I want to change my password from my profile page so I can maintain account security.

### US-4: Enable Two-Factor Authentication
> As a user, I want to enable TOTP-based two-factor authentication so I can add extra security to my account.

### US-5: Manage Recovery Codes
> As a user, I want to view and regenerate my 2FA recovery codes so I can recover my account if I lose my authenticator.

### US-6: Register Passkeys
> As a user, I want to register passkeys (WebAuthn) so I can use biometric or hardware-based authentication.

### US-7: Delete Passkeys
> As a user, I want to delete passkeys I no longer use so I can manage my authentication methods.

### US-8: Enable Passwordless Login
> As a user, I want to allow passkey or TOTP-only login so I don't have to enter my password every time.

### US-9: Ensure Authentication Method
> As a user, I should not be able to disable all authentication methods, ensuring I can always access my account.

---

## Feature Requirements

### FR-1: Profile Display
- Display authenticated user's name and email
- Display user's assigned roles (badges/chips)
- Display user's permissions (direct and inherited via roles)
- Show 2FA enabled status
- Show number of registered passkeys

### FR-2: Password Management
- Form to change password (current password, new password, confirmation)
- Validation: minimum 8 characters, must differ from current
- Optional: Password strength indicator
- Uses existing Fortify password update action

### FR-3: Two-Factor Authentication Management
- **Enable Flow:**
  1. Generate TOTP secret
  2. Display QR code for authenticator app
  3. Require code confirmation before enabling
  4. Display recovery codes (8 codes)
  5. Prompt user to save recovery codes
- **Disable Flow:**
  1. Require password confirmation (configurable)
  2. Delete 2FA secret and recovery codes
- **Recovery Code Management:**
  1. View existing recovery codes
  2. Regenerate new recovery codes

### FR-4: Passkey Management
- List registered passkeys with:
  - Name (user-provided during registration)
  - Created date
  - Last used date
- Register new passkey:
  1. Enter passkey name
  2. Call WebAuthn registration API
  3. Handle browser credential creation
  4. Store passkey data
- Delete passkey:
  1. Confirm deletion
  2. Remove passkey record

### FR-5: Authentication Preferences
- Three boolean settings:
  - `require_password` (default: true) - Password is required for login
  - `allow_passkey_login` (default: false) - Passkey can substitute for password
  - `allow_totp_login` (default: false) - TOTP code can substitute for password
- Validation rules:
  - Cannot disable all methods (at least one must be enabled)
  - Cannot enable passkey login without registered passkeys
  - Cannot enable TOTP login without confirmed 2FA

### FR-6: Enhanced Login Flow
- Default: Password-based login (existing behavior)
- If user has `allow_passkey_login` and passkeys: Show passkey login option
- If user has `allow_totp_login` and 2FA: Show TOTP-only login option
- Login page tabs/toggles between authentication methods

---

## Database Schema

### New Migration: `add_auth_preferences_to_users_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Auth preference columns
            $table->boolean('allow_passkey_login')->default(false)
                ->after('two_factor_confirmed_at')
                ->comment('Allow passkey as primary authentication');

            $table->boolean('allow_totp_login')->default(false)
                ->after('allow_passkey_login')
                ->comment('Allow TOTP code as primary authentication');

            $table->boolean('require_password')->default(true)
                ->after('allow_totp_login')
                ->comment('Whether password is required for login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'allow_passkey_login',
                'allow_totp_login',
                'require_password',
            ]);
        });
    }
};
```

### Updated User Model Trait

Add to `HasAuthKit` trait:

```php
/**
 * Auth preference attributes that should be mass assignable.
 */
protected function getAuthKitFillable(): array
{
    return [
        'allow_passkey_login',
        'allow_totp_login',
        'require_password',
    ];
}

/**
 * Auth preference attributes that should be cast.
 */
protected function getAuthKitCasts(): array
{
    return [
        'allow_passkey_login' => 'boolean',
        'allow_totp_login' => 'boolean',
        'require_password' => 'boolean',
    ];
}

/**
 * Check if user can use passwordless login.
 */
public function canUsePasswordlessLogin(): bool
{
    return ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
           ($this->allow_totp_login && $this->hasTwoFactorEnabled());
}

/**
 * Get available authentication methods for this user.
 */
public function getAvailableAuthMethods(): array
{
    $methods = [];

    if ($this->require_password) {
        $methods[] = 'password';
    }

    if ($this->allow_passkey_login && $this->hasPasskeysRegistered()) {
        $methods[] = 'passkey';
    }

    if ($this->allow_totp_login && $this->hasTwoFactorEnabled()) {
        $methods[] = 'totp';
    }

    return $methods;
}

/**
 * Validate that at least one auth method is enabled.
 */
public function hasValidAuthConfiguration(): bool
{
    return $this->require_password ||
           ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
           ($this->allow_totp_login && $this->hasTwoFactorEnabled());
}
```

---

## Configuration

### Add to `config/authkit.php`:

```php
'features' => [
    // ... existing features ...

    /*
    |--------------------------------------------------------------------------
    | Profile Page
    |--------------------------------------------------------------------------
    |
    | Enable the built-in profile page for users to manage their account
    | settings, authentication methods, and security preferences.
    |
    */
    'profile' => true,

    /*
    |--------------------------------------------------------------------------
    | Passwordless Authentication
    |--------------------------------------------------------------------------
    |
    | Allow users to configure passwordless login options. When enabled,
    | users can choose to log in with passkeys or TOTP codes instead of
    | their password.
    |
    */
    'passwordless_login' => true,

    /*
    |--------------------------------------------------------------------------
    | Show Permissions on Profile
    |--------------------------------------------------------------------------
    |
    | When using Spatie Laravel Permission, display the user's roles and
    | permissions on their profile page.
    |
    */
    'show_permissions' => true,
],

'profile' => [
    /*
    |--------------------------------------------------------------------------
    | Profile Route Path
    |--------------------------------------------------------------------------
    |
    | The URI path where the profile page will be accessible.
    |
    */
    'path' => '/profile',

    /*
    |--------------------------------------------------------------------------
    | Profile Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to profile routes.
    |
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Require Password Confirmation
    |--------------------------------------------------------------------------
    |
    | Require password confirmation before sensitive operations like
    | enabling/disabling 2FA or modifying auth preferences.
    |
    */
    'require_password_confirm' => true,

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) before the user must re-confirm their password
    | for sensitive operations. Default: 3 hours.
    |
    */
    'password_timeout' => 10800,
],
```

---

## Routes

### Profile Routes (to be registered by AuthKit)

```php
<?php

use Illuminate\Support\Facades\Route;
use BSPDX\AuthKit\Http\Controllers\ProfileController;

Route::middleware(config('authkit.profile.middleware', ['web', 'auth']))->group(function () {
    // Main profile page
    Route::get(config('authkit.profile.path', '/profile'), [ProfileController::class, 'show'])
        ->name('authkit.profile.show');

    // Update auth preferences
    Route::put(config('authkit.profile.path', '/profile') . '/auth-preferences', [ProfileController::class, 'updateAuthPreferences'])
        ->name('authkit.profile.auth-preferences.update');
});
```

### Enhanced Login Routes

```php
<?php

// Add to existing guest routes
Route::middleware(['web', 'guest'])->group(function () {
    // Get available auth methods for an email
    Route::post('/login/methods', [LoginController::class, 'getAuthMethods'])
        ->name('authkit.login.methods');

    // TOTP-only login (when password not required)
    Route::post('/login/totp', [LoginController::class, 'authenticateWithTotp'])
        ->name('authkit.login.totp');
});
```

---

## Controllers

### ProfileController

```php
<?php

namespace BSPDX\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Display the user's profile page.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $data = [
            'user' => $user,
            'hasTwoFactor' => $user->hasTwoFactorEnabled(),
            'hasPasskeys' => $user->hasPasskeysRegistered(),
            'passkeys' => $user->passkeys ?? collect(),
        ];

        // Include roles/permissions if using Spatie and feature enabled
        if (config('authkit.features.show_permissions') && method_exists($user, 'getRoleNames')) {
            $data['roles'] = $user->getRoleNames();
            $data['permissions'] = $user->getAllPermissions()->pluck('name');
        }

        return view('authkit::profile.show', $data);
    }

    /**
     * Update the user's authentication preferences.
     */
    public function updateAuthPreferences(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'allow_passkey_login' => 'boolean',
            'allow_totp_login' => 'boolean',
            'require_password' => 'boolean',
        ]);

        // Convert checkbox values (present = true, absent = false)
        $preferences = [
            'allow_passkey_login' => $request->boolean('allow_passkey_login'),
            'allow_totp_login' => $request->boolean('allow_totp_login'),
            'require_password' => $request->boolean('require_password'),
        ];

        // Validate at least one method is enabled
        $hasPasskey = $user->hasPasskeysRegistered();
        $hasTwoFactor = $user->hasTwoFactorEnabled();

        $willHaveMethod = $preferences['require_password'] ||
            ($preferences['allow_passkey_login'] && $hasPasskey) ||
            ($preferences['allow_totp_login'] && $hasTwoFactor);

        if (!$willHaveMethod) {
            return back()->withErrors([
                'auth_preferences' => 'You must have at least one authentication method enabled.',
            ]);
        }

        // Validate passkey login requires passkeys
        if ($preferences['allow_passkey_login'] && !$hasPasskey) {
            return back()->withErrors([
                'allow_passkey_login' => 'You must register a passkey before enabling passkey login.',
            ]);
        }

        // Validate TOTP login requires 2FA
        if ($preferences['allow_totp_login'] && !$hasTwoFactor) {
            return back()->withErrors([
                'allow_totp_login' => 'You must enable two-factor authentication before enabling TOTP login.',
            ]);
        }

        $user->update($preferences);

        return back()->with('status', 'auth-preferences-updated');
    }
}
```

### Enhanced LoginController

```php
<?php

namespace BSPDX\AuthKit\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class LoginController extends Controller
{
    /**
     * Get available authentication methods for an email.
     */
    public function getAuthMethods(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Don't reveal if user exists
            return response()->json(['methods' => ['password']]);
        }

        return response()->json([
            'methods' => $user->getAvailableAuthMethods(),
        ]);
    }

    /**
     * Authenticate using TOTP code only (passwordless).
     */
    public function authenticateWithTotp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'totp_code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->allow_totp_login || !$user->hasTwoFactorEnabled()) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $request->totp_code
        );

        if (!$valid) {
            return back()->withErrors(['totp_code' => 'Invalid authentication code.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
```

---

## Views & Components

### Directory Structure

```
resources/views/vendor/authkit/
├── profile/
│   └── show.blade.php
├── components/
│   └── profile/
│       ├── account-info.blade.php
│       ├── roles-permissions.blade.php
│       ├── password-form.blade.php
│       ├── two-factor-management.blade.php
│       ├── two-factor-setup.blade.php
│       ├── recovery-codes.blade.php
│       ├── passkey-management.blade.php
│       └── auth-preferences.blade.php
└── auth/
    └── login.blade.php (enhanced)
```

### Main Profile View

`resources/views/vendor/authkit/profile/show.blade.php`

```blade
@extends(config('authkit.views.layout', 'layouts.app'))

@section('content')
<div class="authkit-profile">
    <h1 class="authkit-profile-title">My Profile</h1>

    @if (session('status'))
        <div class="authkit-alert authkit-alert-success">
            {{ session('status') }}
        </div>
    @endif

    {{-- Account Information --}}
    <section class="authkit-profile-section">
        <h2>Account Information</h2>
        <x-authkit::profile.account-info :user="$user" />
    </section>

    {{-- Roles & Permissions (if enabled) --}}
    @if(config('authkit.features.show_permissions') && isset($roles))
    <section class="authkit-profile-section">
        <h2>Roles & Permissions</h2>
        <x-authkit::profile.roles-permissions :roles="$roles" :permissions="$permissions" />
    </section>
    @endif

    {{-- Password Change --}}
    @if(config('authkit.features.update_passwords'))
    <section class="authkit-profile-section">
        <h2>Change Password</h2>
        <x-authkit::profile.password-form />
    </section>
    @endif

    {{-- Two-Factor Authentication --}}
    @if(config('authkit.features.two_factor'))
    <section class="authkit-profile-section">
        <h2>Two-Factor Authentication</h2>
        <x-authkit::profile.two-factor-management :enabled="$hasTwoFactor" />
    </section>
    @endif

    {{-- Passkeys --}}
    @if(config('authkit.features.passkeys'))
    <section class="authkit-profile-section">
        <h2>Passkeys</h2>
        <x-authkit::profile.passkey-management :passkeys="$passkeys" />
    </section>
    @endif

    {{-- Authentication Preferences --}}
    @if(config('authkit.features.passwordless_login'))
    <section class="authkit-profile-section">
        <h2>Login Preferences</h2>
        <x-authkit::profile.auth-preferences
            :user="$user"
            :has-two-factor="$hasTwoFactor"
            :has-passkeys="$hasPasskeys"
        />
    </section>
    @endif
</div>
@endsection
```

### Component: Account Info

`resources/views/vendor/authkit/components/profile/account-info.blade.php`

```blade
@props(['user'])

<div class="authkit-account-info">
    <div class="authkit-info-row">
        <span class="authkit-info-label">Name</span>
        <span class="authkit-info-value">{{ $user->name }}</span>
    </div>
    <div class="authkit-info-row">
        <span class="authkit-info-label">Email</span>
        <span class="authkit-info-value">{{ $user->email }}</span>
    </div>
    @if($user->email_verified_at)
    <div class="authkit-info-row">
        <span class="authkit-info-label">Email Verified</span>
        <span class="authkit-info-value">{{ $user->email_verified_at->format('M j, Y') }}</span>
    </div>
    @endif
</div>
```

### Component: Roles & Permissions

`resources/views/vendor/authkit/components/profile/roles-permissions.blade.php`

```blade
@props(['roles', 'permissions'])

<div class="authkit-roles-permissions">
    <div class="authkit-subsection">
        <h3>Roles</h3>
        <div class="authkit-badge-list">
            @forelse($roles as $role)
                <span class="authkit-badge authkit-badge-role">{{ ucfirst($role) }}</span>
            @empty
                <span class="authkit-text-muted">No roles assigned</span>
            @endforelse
        </div>
    </div>

    <div class="authkit-subsection">
        <h3>Permissions</h3>
        <div class="authkit-badge-list">
            @forelse($permissions as $permission)
                <span class="authkit-badge authkit-badge-permission">{{ $permission }}</span>
            @empty
                <span class="authkit-text-muted">No permissions assigned</span>
            @endforelse
        </div>
    </div>
</div>
```

### Component: Password Form

`resources/views/vendor/authkit/components/profile/password-form.blade.php`

```blade
<form method="POST" action="{{ route('user-password.update') }}" class="authkit-form">
    @csrf
    @method('PUT')

    <div class="authkit-form-group">
        <label for="current_password" class="authkit-label">Current Password</label>
        <input type="password" name="current_password" id="current_password"
            class="authkit-input @error('current_password', 'updatePassword') authkit-input-error @enderror"
            required>
        @error('current_password', 'updatePassword')
            <span class="authkit-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="authkit-form-group">
        <label for="password" class="authkit-label">New Password</label>
        <input type="password" name="password" id="password"
            class="authkit-input @error('password', 'updatePassword') authkit-input-error @enderror"
            required>
        @error('password', 'updatePassword')
            <span class="authkit-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="authkit-form-group">
        <label for="password_confirmation" class="authkit-label">Confirm New Password</label>
        <input type="password" name="password_confirmation" id="password_confirmation"
            class="authkit-input" required>
    </div>

    <button type="submit" class="authkit-btn authkit-btn-primary">
        Update Password
    </button>
</form>
```

### Component: Two-Factor Management

`resources/views/vendor/authkit/components/profile/two-factor-management.blade.php`

```blade
@props(['enabled' => false])

<div x-data="{ showSetup: false, showCodes: false }" class="authkit-two-factor">
    @if($enabled)
        <div class="authkit-status authkit-status-success">
            <svg class="authkit-icon" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>Two-factor authentication is enabled</span>
        </div>

        <div class="authkit-actions">
            <button @click="showCodes = !showCodes" type="button" class="authkit-btn authkit-btn-secondary">
                <span x-text="showCodes ? 'Hide Recovery Codes' : 'View Recovery Codes'"></span>
            </button>

            <form method="POST" action="{{ route('two-factor.destroy') }}" class="authkit-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="authkit-btn authkit-btn-danger"
                    onclick="return confirm('Are you sure you want to disable two-factor authentication?')">
                    Disable 2FA
                </button>
            </form>
        </div>

        <div x-show="showCodes" x-cloak class="authkit-recovery-codes">
            <x-authkit::profile.recovery-codes />
        </div>
    @else
        <p class="authkit-text">
            Add additional security to your account using two-factor authentication.
            When enabled, you'll be prompted for a secure, random code during login.
        </p>

        <button @click="showSetup = true" type="button" class="authkit-btn authkit-btn-primary">
            Enable Two-Factor Authentication
        </button>

        <div x-show="showSetup" x-cloak class="authkit-setup">
            <x-authkit::profile.two-factor-setup />
        </div>
    @endif
</div>
```

### Component: Two-Factor Setup

`resources/views/vendor/authkit/components/profile/two-factor-setup.blade.php`

```blade
<div x-data="authkitTwoFactorSetup()" x-init="init()" class="authkit-2fa-setup">
    {{-- Step 1: QR Code --}}
    <div x-show="step === 'qr'" class="authkit-setup-step">
        <p class="authkit-text">
            Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):
        </p>

        <div class="authkit-qr-container">
            <div x-html="qrCode" class="authkit-qr-code"></div>
        </div>

        <p class="authkit-text-small">
            Or enter this code manually: <code x-text="secret" class="authkit-code"></code>
        </p>

        <form @submit.prevent="confirmCode" class="authkit-form">
            <div class="authkit-form-group">
                <label class="authkit-label">Enter the 6-digit code from your app:</label>
                <input type="text" x-model="code" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                    class="authkit-input authkit-input-code" placeholder="000000" required>
            </div>

            <div x-show="error" class="authkit-error" x-text="error"></div>

            <button type="submit" class="authkit-btn authkit-btn-primary" :disabled="loading">
                <span x-show="!loading">Verify & Enable</span>
                <span x-show="loading">Verifying...</span>
            </button>
        </form>
    </div>

    {{-- Step 2: Recovery Codes --}}
    <div x-show="step === 'recovery'" class="authkit-setup-step">
        <div class="authkit-alert authkit-alert-warning">
            <strong>Important!</strong> Save these recovery codes in a secure location.
            They can be used to recover access to your account if you lose your authenticator device.
        </div>

        <div class="authkit-recovery-codes-grid">
            <template x-for="code in recoveryCodes" :key="code">
                <code class="authkit-recovery-code" x-text="code"></code>
            </template>
        </div>

        <button @click="downloadCodes()" type="button" class="authkit-btn authkit-btn-secondary">
            Download Codes
        </button>

        <button @click="finish()" type="button" class="authkit-btn authkit-btn-primary">
            I've Saved My Codes
        </button>
    </div>
</div>

<script>
function authkitTwoFactorSetup() {
    return {
        step: 'qr',
        qrCode: '',
        secret: '',
        code: '',
        recoveryCodes: [],
        loading: false,
        error: '',

        async init() {
            try {
                const response = await fetch('{{ route('two-factor.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                this.qrCode = data.qr_code;
                this.secret = data.secret;
                this.recoveryCodes = data.recovery_codes || [];
            } catch (e) {
                this.error = 'Failed to initialize 2FA setup. Please refresh and try again.';
            }
        },

        async confirmCode() {
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch('{{ route('two-factor.confirm') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ code: this.code }),
                });

                if (response.ok) {
                    this.step = 'recovery';
                } else {
                    const data = await response.json();
                    this.error = data.message || 'Invalid code. Please try again.';
                }
            } catch (e) {
                this.error = 'An error occurred. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        downloadCodes() {
            const text = this.recoveryCodes.join('\n');
            const blob = new Blob([text], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'recovery-codes.txt';
            a.click();
            URL.revokeObjectURL(url);
        },

        finish() {
            window.location.reload();
        }
    };
}
</script>
```

### Component: Passkey Management

`resources/views/vendor/authkit/components/profile/passkey-management.blade.php`

```blade
@props(['passkeys'])

<div x-data="authkitPasskeyManager()" class="authkit-passkeys">
    {{-- Existing Passkeys --}}
    @if($passkeys->count() > 0)
    <div class="authkit-passkey-list">
        <h3 class="authkit-subsection-title">Your Passkeys</h3>

        @foreach($passkeys as $passkey)
        <div class="authkit-passkey-item">
            <div class="authkit-passkey-info">
                <span class="authkit-passkey-name">{{ $passkey->name }}</span>
                <span class="authkit-passkey-meta">
                    Added {{ $passkey->created_at->format('M j, Y') }}
                    @if($passkey->last_used_at)
                        &middot; Last used {{ $passkey->last_used_at->diffForHumans() }}
                    @endif
                </span>
            </div>
            <form method="POST" action="{{ route('passkeys.destroy', $passkey) }}" class="authkit-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="authkit-btn authkit-btn-sm authkit-btn-danger"
                    onclick="return confirm('Delete this passkey?')">
                    Delete
                </button>
            </form>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Register New Passkey --}}
    <div class="authkit-passkey-register">
        <h3 class="authkit-subsection-title">Add a New Passkey</h3>

        <p class="authkit-text">
            Passkeys let you sign in using your fingerprint, face, or device PIN.
        </p>

        <div class="authkit-form-group">
            <label class="authkit-label">Passkey Name</label>
            <input type="text" x-model="passkeyName"
                placeholder="e.g., MacBook Pro, iPhone, YubiKey"
                class="authkit-input">
        </div>

        <button @click="registerPasskey()" type="button"
            class="authkit-btn authkit-btn-primary" :disabled="registering">
            <span x-show="!registering">Register Passkey</span>
            <span x-show="registering">Registering...</span>
        </button>

        <div x-show="status" :class="statusType === 'error' ? 'authkit-error' : 'authkit-success'"
            x-text="status" class="authkit-message"></div>
    </div>
</div>

<script>
function authkitPasskeyManager() {
    return {
        passkeyName: '',
        registering: false,
        status: '',
        statusType: '',

        async registerPasskey() {
            if (!this.passkeyName.trim()) {
                this.status = 'Please enter a name for your passkey.';
                this.statusType = 'error';
                return;
            }

            if (!window.PublicKeyCredential) {
                this.status = 'Passkeys are not supported in this browser.';
                this.statusType = 'error';
                return;
            }

            this.registering = true;
            this.status = '';

            try {
                // Get registration options from server
                const optionsResponse = await fetch('{{ route('passkeys.register.options') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (!optionsResponse.ok) {
                    throw new Error('Failed to get registration options');
                }

                const options = await optionsResponse.json();

                // Decode base64url values
                options.challenge = this.base64urlToBuffer(options.challenge);
                options.user.id = this.base64urlToBuffer(options.user.id);
                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(c => ({
                        ...c,
                        id: this.base64urlToBuffer(c.id)
                    }));
                }

                // Create credential
                const credential = await navigator.credentials.create({ publicKey: options });

                // Send to server
                const response = await fetch('{{ route('passkeys.register') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        name: this.passkeyName,
                        id: credential.id,
                        rawId: this.bufferToBase64url(credential.rawId),
                        response: {
                            clientDataJSON: this.bufferToBase64url(credential.response.clientDataJSON),
                            attestationObject: this.bufferToBase64url(credential.response.attestationObject),
                        },
                        type: credential.type,
                    }),
                });

                if (response.ok) {
                    this.status = 'Passkey registered successfully!';
                    this.statusType = 'success';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    throw new Error('Failed to register passkey');
                }
            } catch (error) {
                console.error('Passkey registration error:', error);
                this.status = error.name === 'NotAllowedError'
                    ? 'Registration was cancelled or timed out.'
                    : 'Failed to register passkey. Please try again.';
                this.statusType = 'error';
            } finally {
                this.registering = false;
            }
        },

        base64urlToBuffer(base64url) {
            const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
            const padding = '='.repeat((4 - base64.length % 4) % 4);
            const binary = atob(base64 + padding);
            return Uint8Array.from(binary, c => c.charCodeAt(0));
        },

        bufferToBase64url(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            bytes.forEach(b => binary += String.fromCharCode(b));
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }
    };
}
</script>
```

### Component: Auth Preferences

`resources/views/vendor/authkit/components/profile/auth-preferences.blade.php`

```blade
@props(['user', 'hasTwoFactor', 'hasPasskeys'])

<form method="POST" action="{{ route('authkit.profile.auth-preferences.update') }}" class="authkit-form">
    @csrf
    @method('PUT')

    <p class="authkit-text">
        Choose how you want to sign in to your account. You must have at least one authentication method enabled.
    </p>

    @error('auth_preferences')
        <div class="authkit-error">{{ $message }}</div>
    @enderror

    <div class="authkit-checkbox-group">
        <label class="authkit-checkbox-label">
            <input type="checkbox" name="require_password" value="1"
                {{ $user->require_password ? 'checked' : '' }}
                class="authkit-checkbox">
            <div class="authkit-checkbox-content">
                <span class="authkit-checkbox-title">Require password</span>
                <span class="authkit-checkbox-description">Use your password as the primary login method</span>
            </div>
        </label>

        <label class="authkit-checkbox-label {{ !$hasPasskeys ? 'authkit-disabled' : '' }}">
            <input type="checkbox" name="allow_passkey_login" value="1"
                {{ $user->allow_passkey_login ? 'checked' : '' }}
                {{ !$hasPasskeys ? 'disabled' : '' }}
                class="authkit-checkbox">
            <div class="authkit-checkbox-content">
                <span class="authkit-checkbox-title">Allow passkey login</span>
                <span class="authkit-checkbox-description">
                    Sign in using biometrics or a security key instead of your password
                    @if(!$hasPasskeys)
                        <span class="authkit-text-warning">(Register a passkey first)</span>
                    @endif
                </span>
            </div>
        </label>

        <label class="authkit-checkbox-label {{ !$hasTwoFactor ? 'authkit-disabled' : '' }}">
            <input type="checkbox" name="allow_totp_login" value="1"
                {{ $user->allow_totp_login ? 'checked' : '' }}
                {{ !$hasTwoFactor ? 'disabled' : '' }}
                class="authkit-checkbox">
            <div class="authkit-checkbox-content">
                <span class="authkit-checkbox-title">Allow authenticator code login</span>
                <span class="authkit-checkbox-description">
                    Sign in using your authenticator app code instead of your password
                    @if(!$hasTwoFactor)
                        <span class="authkit-text-warning">(Enable 2FA first)</span>
                    @endif
                </span>
            </div>
        </label>
    </div>

    <button type="submit" class="authkit-btn authkit-btn-primary">
        Save Preferences
    </button>
</form>
```

---

## JavaScript Requirements

### Dependencies

AuthKit profile features require **Alpine.js 3.x** for interactive components. This should be documented as a peer dependency.

```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

### WebAuthn Browser Support

Passkey functionality requires WebAuthn support. The JavaScript includes feature detection:

```javascript
if (!window.PublicKeyCredential) {
    // Passkeys not supported
}
```

### CSRF Token Meta Tag

All JavaScript AJAX calls require the CSRF token. Applications must include:

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

---

## Security Considerations

### 1. Password Confirmation

Sensitive operations should require recent password confirmation. Use Laravel's built-in middleware:

```php
Route::put('/profile/auth-preferences', [ProfileController::class, 'updateAuthPreferences'])
    ->middleware('password.confirm');
```

Configure timeout in `config/auth.php`:

```php
'password_timeout' => 10800, // 3 hours
```

### 2. Session Regeneration

After sensitive changes (enabling/disabling 2FA, changing auth preferences), regenerate the session:

```php
$request->session()->regenerate();
```

### 3. Rate Limiting

Apply rate limiting to sensitive operations:

```php
Route::middleware(['throttle:6,1'])->group(function () {
    Route::post('/user/two-factor-authentication', ...);
    Route::post('/user/passkeys', ...);
});
```

### 4. Validation Rules

- At least one authentication method must always be enabled
- Passkey login requires at least one registered passkey
- TOTP login requires confirmed 2FA
- Recovery codes should only be shown once, then require regeneration to view again

### 5. CSRF Protection

All forms include `@csrf`. JavaScript requests include the CSRF token header.

### 6. WebAuthn Security

- Use `userVerification: 'preferred'` or `'required'` for passkey operations
- Store only public key data, never private keys
- Validate attestation if high security is required

---

## Integration Guide

### Step 1: Publish Migration

```bash
php artisan vendor:publish --tag=authkit-profile-migrations
php artisan migrate
```

### Step 2: Update User Model

If not using the `HasAuthKit` trait's automatic fillable/casts, add manually:

```php
protected $fillable = [
    // ... existing ...
    'allow_passkey_login',
    'allow_totp_login',
    'require_password',
];

protected $casts = [
    // ... existing ...
    'allow_passkey_login' => 'boolean',
    'allow_totp_login' => 'boolean',
    'require_password' => 'boolean',
];
```

### Step 3: Enable Features in Config

```php
// config/authkit.php
'features' => [
    'profile' => true,
    'passwordless_login' => true,
    'show_permissions' => true, // If using Spatie
],
```

### Step 4: Publish Views (Optional)

```bash
php artisan vendor:publish --tag=authkit-profile-views
```

### Step 5: Add Alpine.js

Ensure your layout includes Alpine.js:

```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

### Step 6: Add CSRF Meta Tag

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### Step 7: Add Navigation Link

Add a link to the profile page in your navigation:

```blade
@auth
<a href="{{ route('authkit.profile.show') }}">Profile</a>
@endauth
```

---

## CSS Styling

AuthKit provides minimal, unstyled components using BEM-style class names (`authkit-*`). Applications should provide their own styles or use the optional Tailwind CSS preset.

### Tailwind Preset (Optional)

```bash
php artisan vendor:publish --tag=authkit-tailwind-preset
```

This publishes a Tailwind plugin with default styles for all AuthKit components.

---

## Testing

### Feature Tests

```php
public function test_user_can_view_profile()
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $response->assertSee($user->name);
    $response->assertSee($user->email);
}

public function test_user_can_update_auth_preferences()
{
    $user = User::factory()->create(['require_password' => true]);
    $user->passkeys()->create(['name' => 'Test', 'credential_id' => 'test', 'data' => []]);

    $response = $this->actingAs($user)->put('/profile/auth-preferences', [
        'require_password' => false,
        'allow_passkey_login' => true,
    ]);

    $response->assertRedirect();
    $this->assertFalse($user->fresh()->require_password);
    $this->assertTrue($user->fresh()->allow_passkey_login);
}

public function test_cannot_disable_all_auth_methods()
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/profile/auth-preferences', [
        'require_password' => false,
        'allow_passkey_login' => false,
        'allow_totp_login' => false,
    ]);

    $response->assertSessionHasErrors('auth_preferences');
}
```

---

## Changelog

### Version 1.0.0
- Initial profile page feature
- Password change functionality
- Two-factor authentication management
- Passkey registration and management
- Authentication preferences (passwordless login options)
- Roles & permissions display (Spatie integration)
