<div class="authkit-form-container">
    <div class="authkit-form">
        <h2 style="margin-bottom: 1.5rem; text-align: center; color: var(--authkit-text);">
            Sign in with Passkey
        </h2>

        <button type="button" class="authkit-button" onclick="loginWithPasskey()">
            Sign in with Passkey
        </button>

        <div id="passkey-status" style="margin-top: 1rem; font-size: 0.875rem; text-align: center;"></div>

        <div class="authkit-links" style="justify-content: center;">
            <a href="{{ route('login') }}" class="authkit-link">Use password instead</a>
        </div>
    </div>
</div>

<script>
    async function loginWithPasskey() {
        const statusDiv = document.getElementById('passkey-status');

        try {
            statusDiv.innerHTML = '<p style="color: var(--authkit-primary);">Preparing passkey authentication...</p>';

            // Get authentication options from server
            const optionsResponse = await fetch('{{ $loginOptionsUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!optionsResponse.ok) {
                throw new Error('Failed to get authentication options');
            }

            const options = await optionsResponse.json();

            // Prepare options for WebAuthn
            options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map(cred => ({
                    ...cred,
                    id: Uint8Array.from(atob(cred.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                }));
            }

            statusDiv.innerHTML = '<p style="color: var(--authkit-primary);">Follow your browser prompt...</p>';

            // Get credential
            const credential = await navigator.credentials.get({ publicKey: options });

            if (!credential) {
                throw new Error('Passkey authentication was cancelled');
            }

            statusDiv.innerHTML = '<p style="color: var(--authkit-primary);">Verifying...</p>';

            // Send credential to server
            const response = await fetch('{{ $authenticateUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    credential: {
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                            authenticatorData: btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData))),
                            signature: btoa(String.fromCharCode(...new Uint8Array(credential.response.signature))),
                            userHandle: credential.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(credential.response.userHandle))) : null,
                        },
                        type: credential.type,
                    },
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Authentication failed');
            }

            statusDiv.innerHTML = '<p style="color: #10b981;">✓ Authentication successful! Redirecting...</p>';

            // Redirect to dashboard
            window.location.href = result.redirect || '{{ config('authkit.redirects.login', '/dashboard') }}';

        } catch (error) {
            console.error('Passkey authentication error:', error);
            statusDiv.innerHTML = `<p style="color: var(--authkit-danger);">Error: ${error.message}</p>`;
        }
    }

    // Auto-trigger on page load
    document.addEventListener('DOMContentLoaded', () => {
        // Optionally auto-trigger passkey login
        // loginWithPasskey();
    });
</script>
