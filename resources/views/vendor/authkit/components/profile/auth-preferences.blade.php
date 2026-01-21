@props(['user', 'hasTwoFactor', 'hasPasskeys'])

<div class="authkit-auth-preferences">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-auth-preferences {
            /* Base styles */
        }

        .authkit-text {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .authkit-error {
            color: var(--authkit-danger, #dc2626);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #fee2e2;
            border-radius: var(--authkit-radius, 0.5rem);
        }

        .authkit-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .authkit-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--authkit-bg-secondary, #f9fafb);
            border-radius: var(--authkit-radius, 0.5rem);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .authkit-checkbox-label:hover {
            background: #f3f4f6;
        }

        .authkit-checkbox-label.authkit-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .authkit-checkbox-label.authkit-disabled:hover {
            background: var(--authkit-bg-secondary, #f9fafb);
        }

        .authkit-checkbox {
            margin-top: 0.25rem;
            width: 1rem;
            height: 1rem;
            cursor: pointer;
        }

        .authkit-checkbox:disabled {
            cursor: not-allowed;
        }

        .authkit-checkbox-content {
            display: flex;
            flex-direction: column;
        }

        .authkit-checkbox-title {
            font-weight: 500;
            color: var(--authkit-text, #1f2937);
        }

        .authkit-checkbox-description {
            font-size: 0.875rem;
            color: var(--authkit-text-muted, #6b7280);
        }

        .authkit-text-warning {
            color: #d97706;
            font-weight: 500;
        }

        .authkit-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--authkit-radius, 0.5rem);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s;
        }

        .authkit-btn-primary {
            background: var(--authkit-primary, #4f46e5);
            color: white;
        }

        .authkit-btn-primary:hover {
            background: var(--authkit-primary-hover, #4338ca);
        }
    </style>

    <form method="POST" action="{{ route('authkit.profile.auth-preferences.update') }}">
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
                            <br><span class="authkit-text-warning">(Register a passkey first)</span>
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
                            <br><span class="authkit-text-warning">(Enable 2FA first)</span>
                        @endif
                    </span>
                </div>
            </label>
        </div>

        <button type="submit" class="authkit-btn authkit-btn-primary">
            Save Preferences
        </button>
    </form>
</div>
