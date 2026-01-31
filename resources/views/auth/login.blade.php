@extends('layout')

@section('main')
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-heading text-secondary mb-2">Welcome Back</h1>
                <p class="text-dark/70">Sign in to manage your blog</p>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-8">
                <div id="error-container" class="hidden mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded" role="alert">
                    <ul class="list-disc list-inside text-sm" id="error-list"></ul>
                </div>

                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded" role="alert">
                        <ul class="list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Step 1: Email Entry -->
                <div id="step-email">
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-dark mb-2">
                            Email Address
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-link focus:border-transparent transition"
                            onkeydown="if(event.key === 'Enter') { event.preventDefault(); checkEmail(); }"
                        >
                    </div>

                    <button
                        type="button"
                        onclick="checkEmail()"
                        id="continue-btn"
                        class="w-full bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary/90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                    >
                        Continue
                    </button>
                </div>

                <!-- Step 2: Authentication -->
                <div id="step-auth" class="hidden">
                    <!-- Email display with change option -->
                    <div class="mb-6 flex items-center justify-between bg-gray-50 px-4 py-3 rounded-lg">
                        <div>
                            <span class="text-sm text-gray-500">Signing in as</span>
                            <p id="display-email" class="font-medium text-dark"></p>
                        </div>
                        <button
                            type="button"
                            onclick="changeEmail()"
                            class="text-sm text-link hover:underline"
                        >
                            Change
                        </button>
                    </div>

                    <!-- Password Login Form -->
                    <form method="POST" action="{{ route('login') }}" id="password-form" class="hidden">
                        @csrf
                        <input type="hidden" name="email" id="password-form-email">

                        <div class="mb-6">
                            <label for="password" class="block text-sm font-medium text-dark mb-2">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-link focus:border-transparent transition"
                            >
                        </div>

                        <div class="mb-6 flex items-center justify-between">
                            <label class="flex items-center">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    class="rounded border-gray-300 text-primary focus:ring-link"
                                >
                                <span class="ml-2 text-sm text-dark">Remember me</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm text-link hover:underline">
                                    Forgot password?
                                </a>
                            @endif
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary/90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                        >
                            Sign In
                        </button>

                        <p id="two-fa-notice" class="hidden mt-3 text-sm text-gray-500 text-center">
                            You'll be asked for a second factor after signing in.
                        </p>
                    </form>

                    <!-- Passkey Login -->
                    <div id="passkey-section" class="hidden">
                        <button
                            type="button"
                            onclick="loginWithPasskey()"
                            id="passkey-btn"
                            class="w-full bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary/90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center justify-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                            Sign in with Passkey
                        </button>
                        <p id="passkey-status" class="mt-2 text-sm text-center text-gray-500"></p>
                    </div>

                    <!-- TOTP Section (shown as alternative or primary) -->
                    <div id="totp-section" class="hidden">
                        <div id="totp-toggle" class="hidden text-center mt-4">
                            <button
                                type="button"
                                onclick="showTotpForm()"
                                class="text-sm text-link hover:underline"
                            >
                                Or sign in with authenticator code
                            </button>
                        </div>

                        <form id="totp-form" class="hidden mt-4">
                            <input type="hidden" id="totp-form-email">

                            <div class="mb-6">
                                <label for="totp-code" class="block text-sm font-medium text-dark mb-2">
                                    Authenticator Code
                                </label>
                                <input
                                    id="totp-code"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="6"
                                    required
                                    autocomplete="one-time-code"
                                    placeholder="000000"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-link focus:border-transparent transition text-center text-2xl tracking-widest"
                                >
                            </div>

                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="totp-remember"
                                        class="rounded border-gray-300 text-primary focus:ring-link"
                                    >
                                    <span class="ml-2 text-sm text-dark">Remember me</span>
                                </label>
                            </div>

                            <button
                                type="button"
                                onclick="loginWithTotp()"
                                id="totp-submit-btn"
                                class="w-full bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary/90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                            >
                                Sign In
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Blocked Screen -->
    <div id="blocked-screen" class="hidden fixed inset-0 bg-red-600 flex items-center justify-center z-50">
        <div class="text-center">
            <h1 class="text-white text-5xl font-bold mb-4">YOU MAY NOT LOG IN</h1>
            <button
                type="button"
                onclick="hideBlockedScreen()"
                class="mt-8 text-white/80 hover:text-white underline"
            >
                Go back
            </button>
        </div>
    </div>

    <script>
        let currentEmail = '';
        let authMethods = [];
            let csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            async function checkEmail() {
                const emailInput = document.getElementById('email');
                const email = emailInput.value.trim();
                const btn = document.getElementById('continue-btn');

                if (!email) {
                    showError('Please enter your email address.');
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Checking...';
                hideError();

                try {
                    const response = await fetch('{{ route("login.check-email") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ email }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data.errors && data.errors.email) {
                            showError(data.errors.email[0]);
                        } else {
                            showError('Something went wrong. Please try again.');
                        }
                        btn.disabled = false;
                        btn.textContent = 'Continue';
                        return;
                    }

                    currentEmail = email;
                    authMethods = data.methods;

                    // Update CSRF token if provided
                    if (data.csrf_token) {
                        csrfToken = data.csrf_token;
                        document.querySelector('meta[name="csrf-token"]').content = csrfToken;
                    }

                    if (data.blocked) {
                        showBlockedScreen();
                    } else {
                        showAuthStep(data);
                    }

                } catch (error) {
                    console.error('Check email error:', error);
                    showError('Something went wrong. Please try again.');
                }

                btn.disabled = false;
                btn.textContent = 'Continue';
            }

            function showAuthStep(data) {
                document.getElementById('step-email').classList.add('hidden');
                document.getElementById('step-auth').classList.remove('hidden');
                document.getElementById('display-email').textContent = currentEmail;

                // Hide all auth sections first
                document.getElementById('password-form').classList.add('hidden');
                document.getElementById('passkey-section').classList.add('hidden');
                document.getElementById('totp-section').classList.add('hidden');
                document.getElementById('totp-toggle').classList.add('hidden');
                document.getElementById('totp-form').classList.add('hidden');

                const methods = data.methods;
                const hasPassword = methods.includes('password');
                const hasPasskey = methods.includes('passkey');
                const hasTotp = methods.includes('totp');

                if (hasPassword || !data.user_exists) {
                    // Show password form
                    document.getElementById('password-form').classList.remove('hidden');
                    document.getElementById('password-form-email').value = currentEmail;
                    document.getElementById('password').focus();

                    // Show 2FA notice if required
                    if (data.require_2fa) {
                        document.getElementById('two-fa-notice').classList.remove('hidden');
                    }
                } else if (hasPasskey || hasTotp) {
                    // Passwordless login options
                    if (hasPasskey) {
                        document.getElementById('passkey-section').classList.remove('hidden');

                        if (hasTotp) {
                            // Show TOTP as alternative
                            document.getElementById('totp-section').classList.remove('hidden');
                            document.getElementById('totp-toggle').classList.remove('hidden');
                            document.getElementById('totp-form-email').value = currentEmail;
                        }
                    } else if (hasTotp) {
                        // TOTP only
                        document.getElementById('totp-section').classList.remove('hidden');
                        document.getElementById('totp-form').classList.remove('hidden');
                        document.getElementById('totp-form-email').value = currentEmail;
                        document.getElementById('totp-code').focus();
                    }
                }
            }

            function changeEmail() {
                document.getElementById('step-auth').classList.add('hidden');
                document.getElementById('step-email').classList.remove('hidden');
                document.getElementById('email').focus();
                hideError();
            }

            function showBlockedScreen() {
                document.getElementById('blocked-screen').classList.remove('hidden');
            }

            function hideBlockedScreen() {
                document.getElementById('blocked-screen').classList.add('hidden');
                changeEmail();
            }

            function showTotpForm() {
                document.getElementById('totp-toggle').classList.add('hidden');
                document.getElementById('totp-form').classList.remove('hidden');
                document.getElementById('totp-code').focus();
            }

            async function loginWithPasskey() {
                const statusEl = document.getElementById('passkey-status');
                const btn = document.getElementById('passkey-btn');

                btn.disabled = true;
                statusEl.textContent = 'Preparing...';

                try {
                    // Get authentication options
                    const optionsResponse = await fetch('{{ route("passkeys.login.options") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ email: currentEmail }),
                    });

                    if (!optionsResponse.ok) {
                        throw new Error('Failed to get authentication options');
                    }

                    const options = await optionsResponse.json();

                    // Store original options to send back to server for validation
                    const originalOptions = JSON.parse(JSON.stringify(options));

                    // Prepare options for WebAuthn
                    options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

                    if (options.allowCredentials) {
                        options.allowCredentials = options.allowCredentials.map(cred => ({
                            ...cred,
                            id: Uint8Array.from(atob(cred.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                        }));
                    }

                    statusEl.textContent = 'Follow your browser prompt...';

                    // Get credential
                    const credential = await navigator.credentials.get({ publicKey: options });

                    if (!credential) {
                        throw new Error('Authentication was cancelled');
                    }

                    statusEl.textContent = 'Verifying...';

                    // Send credential to server
                    const response = await fetch('{{ route("passkeys.authenticate") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            credential: {
                                id: credential.id,
                                rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                                response: {
                                    clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                                    authenticatorData: btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData))),
                                    signature: btoa(String.fromCharCode(...new Uint8Array(credential.response.signature))),
                                    userHandle: credential.response.userHandle
                                        ? btoa(String.fromCharCode(...new Uint8Array(credential.response.userHandle)))
                                        : null,
                                },
                                type: credential.type,
                            },
                            options: originalOptions,
                        }),
                    });

                    if (!response.ok) {
                        console.warn("response is not OK")
                        const error = await response.json();
                        throw new Error(error.message || 'Authentication failed');
                    }

                    try {
                        const result = await response.json();
                        window.location.href = result.redirect || '/';
                    } catch (e) {
                        console.error(e);
                        window.location.href = '/';
                    }

                } catch (error) {
                    console.error('Passkey login error:', error);
                    if (error.name === 'NotAllowedError') {
                        statusEl.textContent = 'Authentication was cancelled or timed out.';
                    } else {
                        statusEl.textContent = error.message || 'Authentication failed. Please try again.';
                    }
                    btn.disabled = false;
                }
            }

            async function loginWithTotp() {
                const code = document.getElementById('totp-code').value.trim();
                const remember = document.getElementById('totp-remember').checked;
                const btn = document.getElementById('totp-submit-btn');

                if (!code || code.length !== 6) {
                    showError('Please enter a 6-digit code.');
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Signing in...';
                hideError();

                try {
                    const response = await fetch('{{ route(name: "keystone.login.totp") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        email: currentEmail,
                        totp_code: code,
                        remember: remember,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    showError(data.message || 'Invalid authentication code.');
                    btn.disabled = false;
                    btn.textContent = 'Sign In';
                    return;
                }

                window.location.href = data.redirect || '/';

            } catch (error) {
                console.error('TOTP login error:', error);
                showError('Something went wrong. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Sign In';
            }
        }

        function showError(message) {
            const container = document.getElementById('error-container');
            const list = document.getElementById('error-list');
            list.innerHTML = '<li>' + message + '</li>';
            container.classList.remove('hidden');
        }

        function hideError() {
            document.getElementById('error-container').classList.add('hidden');
        }

        // Auto-focus email on load if not already filled
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (!emailInput.value) {
                emailInput.focus();
            }
        });
    </script>
@endsection
