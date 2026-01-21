<div class="authkit-password-form">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-password-form {
            /* Base styles */
        }

        .authkit-form-group {
            margin-bottom: 1rem;
        }

        .authkit-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--authkit-text, #1f2937);
            font-size: 0.875rem;
        }

        .authkit-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--authkit-border, #d1d5db);
            border-radius: var(--authkit-radius, 0.5rem);
            font-size: 0.875rem;
        }

        .authkit-input:focus {
            outline: none;
            border-color: var(--authkit-primary, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .authkit-input-error {
            border-color: var(--authkit-danger, #dc2626);
        }

        .authkit-error {
            color: var(--authkit-danger, #dc2626);
            font-size: 0.75rem;
            margin-top: 0.25rem;
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

    <form method="POST" action="{{ route('user-password.update') }}">
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
</div>
