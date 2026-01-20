<div class="authkit-form-container">
    <style>
        :root {
            --authkit-primary: #4f46e5;
            --authkit-primary-hover: #4338ca;
            --authkit-danger: #dc2626;
            --authkit-text: #1f2937;
            --authkit-text-muted: #6b7280;
            --authkit-border: #d1d5db;
            --authkit-bg: #ffffff;
            --authkit-bg-secondary: #f9fafb;
            --authkit-radius: 0.5rem;
            --authkit-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }

        .authkit-form-container {
            max-width: 400px;
            margin: 0 auto;
        }

        .authkit-form {
            background: var(--authkit-bg);
            padding: 2rem;
            border-radius: var(--authkit-radius);
            box-shadow: var(--authkit-shadow);
        }

        .authkit-form-group {
            margin-bottom: 1.5rem;
        }

        .authkit-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--authkit-text);
        }

        .authkit-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--authkit-border);
            border-radius: var(--authkit-radius);
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .authkit-input:focus {
            outline: none;
            border-color: var(--authkit-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .authkit-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .authkit-button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: var(--authkit-primary);
            color: white;
            border: none;
            border-radius: var(--authkit-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .authkit-button:hover {
            background: var(--authkit-primary-hover);
        }

        .authkit-button-secondary {
            background: var(--authkit-bg-secondary);
            color: var(--authkit-text);
            margin-top: 0.75rem;
        }

        .authkit-button-secondary:hover {
            background: #e5e7eb;
        }

        .authkit-error {
            color: var(--authkit-danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .authkit-links {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }

        .authkit-link {
            color: var(--authkit-primary);
            text-decoration: none;
        }

        .authkit-link:hover {
            text-decoration: underline;
        }

        .authkit-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--authkit-text-muted);
        }

        .authkit-divider::before,
        .authkit-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--authkit-border);
        }

        .authkit-divider::before {
            margin-right: 0.5rem;
        }

        .authkit-divider::after {
            margin-left: 0.5rem;
        }
    </style>

    <form method="POST" action="{{ $action }}" class="authkit-form">
        @csrf

        @if ($errors->any())
            <div class="authkit-error" style="margin-bottom: 1rem;">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="authkit-form-group">
            <label for="email" class="authkit-label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="authkit-input"
            >
            @error('email')
                <span class="authkit-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="authkit-form-group">
            <label for="password" class="authkit-label">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                class="authkit-input"
            >
            @error('password')
                <span class="authkit-error">{{ $message }}</span>
            @enderror
        </div>

        @if ($showRememberMe)
            <div class="authkit-form-group">
                <label class="authkit-checkbox">
                    <input type="checkbox" name="remember" id="remember">
                    <span>Remember me</span>
                </label>
            </div>
        @endif

        <button type="submit" class="authkit-button">
            Log in
        </button>

        @if ($showPasskeyOption && config('authkit.features.passkeys'))
            <div class="authkit-divider">or</div>
            <button type="button" class="authkit-button authkit-button-secondary" onclick="loginWithPasskey()">
                Log in with Passkey
            </button>
        @endif

        <div class="authkit-links">
            @if ($showForgotPassword)
                <a href="{{ route('password.request') }}" class="authkit-link">Forgot password?</a>
            @endif
            @if ($showRegisterLink && config('authkit.features.registration'))
                <a href="{{ route('register') }}" class="authkit-link">Create account</a>
            @endif
        </div>
    </form>
</div>

@if ($showPasskeyOption && config('authkit.features.passkeys'))
<script>
    async function loginWithPasskey() {
        // Redirect to passkey login page or trigger passkey auth
        window.location.href = '{{ route('passkeys.login') }}';
    }
</script>
@endif
