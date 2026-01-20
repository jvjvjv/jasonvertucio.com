<div class="authkit-form-container">
    <div class="authkit-form">
        <div class="authkit-form-group">
            <label for="passkey-name" class="authkit-label">Passkey Name</label>
            <input
                id="passkey-name"
                type="text"
                class="authkit-input"
                placeholder="e.g., My iPhone, Work Laptop"
                value="My Passkey"
            >
            <p style="font-size: 0.875rem; color: var(--authkit-text-muted); margin-top: 0.5rem;">
                Give this passkey a recognizable name.
            </p>
        </div>

        <button type="button" class="authkit-button" onclick="registerPasskey()">
            Register Passkey
        </button>

        <div id="passkey-status" style="margin-top: 1rem; font-size: 0.875rem;"></div>
    </div>
</div>

<script>
    async function registerPasskey() {
        const nameInput = document.getElementById('passkey-name');
        const statusDiv = document.getElementById('passkey-status');
        const name = nameInput.value.trim() || 'My Passkey';

        try {
            statusDiv.innerHTML = '<p style="color: var(--authkit-primary);">Preparing passkey registration...</p>';

            // Get registration options from server
            const optionsResponse = await fetch('{{ $registerOptionsUrl }}', {
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

            // Prepare options for WebAuthn
            options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
            options.user.id = Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

            statusDiv.innerHTML = '<p style="color: var(--authkit-primary);">Follow your browser prompt...</p>';

            // Create credential
            const credential = await navigator.credentials.create({ publicKey: options });

            if (!credential) {
                throw new Error('Passkey registration was cancelled');
            }

            statusDiv.innerHTML = '<p style="color: var(--authkit-primary);">Saving passkey...</p>';

            // Send credential to server
            const response = await fetch('{{ $registerUrl }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    name: name,
                    credential: {
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                            attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                        },
                        type: credential.type,
                    },
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to register passkey');
            }

            statusDiv.innerHTML = '<p style="color: #10b981;">✓ Passkey registered successfully!</p>';

            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 1500);

        } catch (error) {
            console.error('Passkey registration error:', error);
            statusDiv.innerHTML = `<p style="color: var(--authkit-danger);">Error: ${error.message}</p>`;
        }
    }
</script>
