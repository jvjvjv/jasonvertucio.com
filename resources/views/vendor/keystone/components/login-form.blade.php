<div class="keystone-form-container">
    <style>
        :root {
            --keystone-primary: #4f46e5;
            --keystone-primary-hover: #4338ca;
            --keystone-danger: #dc2626;
            --keystone-text: #1f2937;
            --keystone-text-muted: #6b7280;
            --keystone-border: #d1d5db;
            --keystone-bg: #ffffff;
            --keystone-bg-secondary: #f9fafb;
            --keystone-radius: 0.5rem;
            --keystone-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }

        .keystone-form-container {
            max-width: 400px;
            margin: 0 auto;
        }

        .keystone-form {
            background: var(--keystone-bg);
            padding: 2rem;
            border-radius: var(--keystone-radius);
            box-shadow: var(--keystone-shadow);
        }

        .keystone-form-group {
            margin-bottom: 1.5rem;
        }

        .keystone-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--keystone-text);
        }

        .keystone-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--keystone-border);
            border-radius: var(--keystone-radius);
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .keystone-input:focus {
            outline: none;
            border-color: var(--keystone-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .keystone-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .keystone-button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: var(--keystone-primary);
            color: white;
            border: none;
            border-radius: var(--keystone-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .keystone-button:hover {
            background: var(--keystone-primary-hover);
        }

        .keystone-button-secondary {
            background: var(--keystone-bg-secondary);
            color: var(--keystone-text);
            margin-top: 0.75rem;
        }

        .keystone-button-secondary:hover {
            background: #e5e7eb;
        }

        .keystone-error {
            color: var(--keystone-danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .keystone-links {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }

        .keystone-link {
            color: var(--keystone-primary);
            text-decoration: none;
        }

        .keystone-link:hover {
            text-decoration: underline;
        }

        .keystone-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--keystone-text-muted);
        }

        .keystone-divider::before,
        .keystone-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--keystone-border);
        }

        .keystone-divider::before {
            margin-right: 0.5rem;
        }

        .keystone-divider::after {
            margin-left: 0.5rem;
        }
    </style>

    <form method="POST" action="{{ $action }}" class="keystone-form">
        @csrf

        @if ($errors->any())
            <div class="keystone-error" style="margin-bottom: 1rem;">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="keystone-form-group">
            <label for="email" class="keystone-label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="keystone-input"
            >
            @error('email')
                <span class="keystone-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="keystone-form-group">
            <label for="password" class="keystone-label">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                class="keystone-input"
            >
            @error('password')
                <span class="keystone-error">{{ $message }}</span>
            @enderror
        </div>

        @if ($showRememberMe)
            <div class="keystone-form-group">
                <label class="keystone-checkbox">
                    <input type="checkbox" name="remember" id="remember">
                    <span>Remember me</span>
                </label>
            </div>
        @endif

        <button type="submit" class="keystone-button">
            Log in
        </button>

        @if ($showPasskeyOption && config('keystone.features.passkeys'))
            <div class="keystone-divider">or</div>
            <button type="button" class="keystone-button keystone-button-secondary" onclick="loginWithPasskey()">
                Log in with Passkey
            </button>
        @endif

        <div class="keystone-links">
            @if ($showForgotPassword)
                <a href="{{ route('password.request') }}" class="keystone-link">Forgot password?</a>
            @endif
            @if ($showRegisterLink && config('keystone.features.registration'))
                <a href="{{ route('register') }}" class="keystone-link">Create account</a>
            @endif
        </div>
    </form>
</div>

@if ($showPasskeyOption && config('keystone.features.passkeys'))
<script>
    async function loginWithPasskey() {
        // Redirect to passkey login page or trigger passkey auth
        window.location.href = '{{ route('passkeys.login') }}';
    }
</script>
@endif
