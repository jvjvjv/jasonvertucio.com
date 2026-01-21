@extends('layout')

@section('title', 'My Profile')

@section('main')
<div class="authkit-profile">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-profile {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .authkit-profile-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--authkit-text, #1f2937);
            margin-bottom: 2rem;
        }

        .authkit-profile-section {
            background: var(--authkit-bg, #ffffff);
            border-radius: var(--authkit-radius, 0.5rem);
            box-shadow: var(--authkit-shadow, 0 1px 3px 0 rgb(0 0 0 / 0.1));
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .authkit-profile-section h2 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--authkit-text, #1f2937);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--authkit-border, #e5e7eb);
        }

        .authkit-alert {
            padding: 1rem;
            border-radius: var(--authkit-radius, 0.5rem);
            margin-bottom: 1.5rem;
        }

        .authkit-alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .authkit-alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <h1 class="authkit-profile-title">My Profile</h1>

    @if (session('status'))
        <div class="authkit-alert authkit-alert-success">
            @switch(session('status'))
                @case('auth-preferences-updated')
                    Your login preferences have been updated.
                    @break
                @case('passkey-registered')
                    Your passkey has been registered successfully.
                    @break
                @case('passkey-deleted')
                    Your passkey has been deleted.
                    @break
                @case('two-factor-enabled')
                    Two-factor authentication has been enabled.
                    @break
                @case('two-factor-disabled')
                    Two-factor authentication has been disabled.
                    @break
                @case('recovery-codes-regenerated')
                    Your recovery codes have been regenerated.
                    @break
                @default
                    {{ session('status') }}
            @endswitch
        </div>
    @endif

    @if ($errors->any())
        <div class="authkit-alert authkit-alert-error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Account Information --}}
    <section class="authkit-profile-section">
        <h2>Account Information</h2>
        @include('authkit::components.profile.account-info', ['user' => $user])
    </section>

    {{-- Roles & Permissions (if enabled) --}}
    @if(config('authkit.features.show_permissions') && isset($roles))
    <section class="authkit-profile-section">
        <h2>Roles & Permissions</h2>
        @include('authkit::components.profile.roles-permissions', ['roles' => $roles, 'permissions' => $permissions])
    </section>
    @endif

    {{-- Password Change --}}
    @if(config('authkit.features.update_passwords'))
    <section class="authkit-profile-section">
        <h2>Change Password</h2>
        @include('authkit::components.profile.password-form')
    </section>
    @endif

    {{-- Two-Factor Authentication --}}
    @if(config('authkit.features.two_factor'))
    <section class="authkit-profile-section">
        <h2>Two-Factor Authentication</h2>
        @include('authkit::components.profile.two-factor-management', ['enabled' => $hasTwoFactor])
    </section>
    @endif

    {{-- Passkeys --}}
    @if(config('authkit.features.passkeys'))
    <section class="authkit-profile-section">
        <h2>Passkeys</h2>
        @include('authkit::components.profile.passkey-management', ['passkeys' => $passkeys])
    </section>
    @endif

    {{-- Authentication Preferences --}}
    @if(config('authkit.features.passwordless_login'))
    <section class="authkit-profile-section">
        <h2>Login Preferences</h2>
        @include('authkit::components.profile.auth-preferences', [
            'user' => $user,
            'hasTwoFactor' => $hasTwoFactor,
            'hasPasskeys' => $hasPasskeys
        ])
    </section>
    @endif
</div>
@endsection
