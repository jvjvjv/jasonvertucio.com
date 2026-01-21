@props(['passkeys'])

<div class="authkit-passkeys">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-passkeys {
            /* Base styles */
        }

        .authkit-passkey-list {
            margin-bottom: 1.5rem;
        }

        .authkit-subsection-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--authkit-text-muted, #6b7280);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .authkit-passkey-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--authkit-bg-secondary, #f9fafb);
            border-radius: var(--authkit-radius, 0.5rem);
            margin-bottom: 0.5rem;
        }

        .authkit-passkey-info {
            display: flex;
            flex-direction: column;
        }

        .authkit-passkey-name {
            font-weight: 500;
            color: var(--authkit-text, #1f2937);
        }

        .authkit-passkey-meta {
            font-size: 0.75rem;
            color: var(--authkit-text-muted, #6b7280);
        }

        .authkit-passkey-register {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--authkit-border, #e5e7eb);
        }

        .authkit-text {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
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

        .authkit-btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .authkit-btn-danger {
            background: var(--authkit-danger, #dc2626);
            color: white;
        }

        .authkit-btn-danger:hover {
            background: #b91c1c;
        }

        .authkit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .authkit-message {
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }

        .authkit-success {
            color: #059669;
        }

        .authkit-error {
            color: var(--authkit-danger, #dc2626);
        }

        .authkit-inline {
            display: inline;
        }

        .authkit-no-passkeys {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>

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
            <form method="POST" action="{{ route('passkeys.destroy', $passkey->id) }}" class="authkit-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="authkit-btn authkit-btn-sm authkit-btn-danger"
                    onclick="return confirm('Are you sure you want to delete this passkey?')">
                    Delete
                </button>
            </form>
        </div>
        @endforeach
    </div>
    @else
    <p class="authkit-no-passkeys">You haven't registered any passkeys yet.</p>
    @endif

    {{-- Register New Passkey --}}
    <div class="authkit-passkey-register">
        <h3 class="authkit-subsection-title">Add a New Passkey</h3>

        <p class="authkit-text">
            Passkeys let you sign in using your fingerprint, face, or device PIN.
        </p>

        <div class="authkit-form-group">
            <label class="authkit-label" for="passkey-name">Passkey Name</label>
            <input type="text" id="passkey-name"
                placeholder="e.g., MacBook Pro, iPhone, YubiKey"
                class="authkit-input">
        </div>

        <button type="button" onclick="registerPasskeyFromProfile()" id="register-passkey-btn"
            class="authkit-btn authkit-btn-primary">
            Register Passkey
        </button>

        <div id="passkey-status" class="authkit-message"></div>
    </div>
</div>

<script>
    async function registerPasskeyFromProfile() {
        const nameInput = document.getElementById('passkey-name');
        const statusEl = document.getElementById('passkey-status');
        const btn = document.getElementById('register-passkey-btn');
        const name = nameInput.value.trim() || 'My Passkey';

        if (!name) {
            statusEl.innerHTML = '<span class="authkit-error">Please enter a name for your passkey.</span>';
            return;
        }

        if (!window.PublicKeyCredential) {
            statusEl.innerHTML = '<span class="authkit-error">Passkeys are not supported in this browser.</span>';
            return;
        }

        btn.disabled = true;
        statusEl.innerHTML = '<span>Preparing passkey registration...</span>';

        try {
            // Get registration options from server
            const optionsResponse = await fetch('{{ route("passkeys.register.options") }}', {
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

            statusEl.innerHTML = '<span>Follow your browser prompt...</span>';

            // Create credential
            const credential = await navigator.credentials.create({ publicKey: options });

            if (!credential) {
                throw new Error('Passkey registration was cancelled');
            }

            statusEl.innerHTML = '<span>Saving passkey...</span>';

            // Send credential to server
            const response = await fetch('{{ route("passkeys.register") }}', {
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

            statusEl.innerHTML = '<span class="authkit-success">Passkey registered successfully!</span>';

            // Reload page after a short delay
            setTimeout(() => window.location.reload(), 1500);

        } catch (error) {
            console.error('Passkey registration error:', error);
            if (error.name === 'NotAllowedError') {
                statusEl.innerHTML = '<span class="authkit-error">Registration was cancelled or timed out.</span>';
            } else {
                statusEl.innerHTML = `<span class="authkit-error">Error: ${error.message}</span>`;
            }
            btn.disabled = false;
        }
    }
</script>
