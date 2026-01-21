<div class="authkit-2fa-setup" id="2fa-setup">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-2fa-setup {
            padding: 1rem;
            background: var(--authkit-bg-secondary, #f9fafb);
            border-radius: var(--authkit-radius, 0.5rem);
        }

        .authkit-setup-step {
            display: none;
        }

        .authkit-setup-step.active {
            display: block;
        }

        .authkit-text {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .authkit-qr-container {
            display: flex;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .authkit-qr-code {
            background: white;
            padding: 1rem;
            border-radius: var(--authkit-radius, 0.5rem);
        }

        .authkit-text-small {
            font-size: 0.75rem;
            color: var(--authkit-text-muted, #6b7280);
            margin-bottom: 1rem;
        }

        .authkit-code {
            font-family: monospace;
            background: #e5e7eb;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
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

        .authkit-input-code {
            width: 8rem;
            padding: 0.75rem;
            border: 1px solid var(--authkit-border, #d1d5db);
            border-radius: var(--authkit-radius, 0.5rem);
            font-size: 1.25rem;
            font-family: monospace;
            text-align: center;
            letter-spacing: 0.5rem;
        }

        .authkit-input-code:focus {
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

        .authkit-btn-secondary {
            background: var(--authkit-bg-secondary, #f3f4f6);
            color: var(--authkit-text, #1f2937);
            border: 1px solid var(--authkit-border, #d1d5db);
        }

        .authkit-btn-secondary:hover {
            background: #e5e7eb;
        }

        .authkit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .authkit-error {
            color: var(--authkit-danger, #dc2626);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .authkit-alert-warning {
            background: #fef3c7;
            color: #92400e;
            padding: 1rem;
            border-radius: var(--authkit-radius, 0.5rem);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .authkit-recovery-codes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .authkit-recovery-code {
            font-family: monospace;
            background: white;
            padding: 0.5rem 0.75rem;
            border-radius: var(--authkit-radius, 0.5rem);
            font-size: 0.875rem;
            text-align: center;
        }

        .authkit-setup-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
    </style>

    {{-- Step 1: QR Code --}}
    <div id="step-qr" class="authkit-setup-step active">
        <p class="authkit-text">
            Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):
        </p>

        <div class="authkit-qr-container">
            <div id="qr-code" class="authkit-qr-code">Loading...</div>
        </div>

        <p class="authkit-text-small">
            Or enter this code manually: <code id="secret-key" class="authkit-code">Loading...</code>
        </p>

        <div class="authkit-form-group">
            <label class="authkit-label">Enter the 6-digit code from your app:</label>
            <input type="text" id="totp-code" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                class="authkit-input-code" placeholder="000000">
        </div>

        <div id="setup-error" class="authkit-error" style="display: none;"></div>

        <button type="button" onclick="confirmTwoFactor()" id="verify-btn" class="authkit-btn authkit-btn-primary">
            Verify & Enable
        </button>
    </div>

    {{-- Step 2: Recovery Codes --}}
    <div id="step-recovery" class="authkit-setup-step">
        <div class="authkit-alert-warning">
            <strong>Important!</strong> Save these recovery codes in a secure location.
            They can be used to recover access to your account if you lose your authenticator device.
        </div>

        <div id="setup-recovery-codes" class="authkit-recovery-codes-grid">
            <!-- Recovery codes will be populated here -->
        </div>

        <div class="authkit-setup-actions">
            <button type="button" onclick="downloadSetupCodes()" class="authkit-btn authkit-btn-secondary">
                Download Codes
            </button>

            <button type="button" onclick="finishSetup()" class="authkit-btn authkit-btn-primary">
                I've Saved My Codes
            </button>
        </div>
    </div>
</div>

<script>
    let setupQrCode = '';
    let setupSecret = '';
    let setupRecoveryCodes = [];
    let setupInitialized = false;

    // Initialize 2FA setup when the setup container is shown
    document.addEventListener('DOMContentLoaded', function() {
        // Watch for setup container becoming visible
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.target.id === 'setup-container' && mutation.target.classList.contains('show')) {
                    if (!setupInitialized) {
                        initTwoFactorSetup();
                    }
                }
            });
        });

        const setupContainer = document.getElementById('setup-container');
        if (setupContainer) {
            observer.observe(setupContainer, { attributes: true, attributeFilter: ['class'] });
        }
    });

    async function initTwoFactorSetup() {
        const qrCodeEl = document.getElementById('qr-code');
        const secretEl = document.getElementById('secret-key');
        const errorEl = document.getElementById('setup-error');

        try {
            const response = await fetch('{{ route("two-factor.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to initialize 2FA setup');
            }

            const data = await response.json();

            if (data.qr_code) {
                qrCodeEl.innerHTML = data.qr_code;
            } else if (data.svg) {
                qrCodeEl.innerHTML = data.svg;
            }

            setupSecret = data.secret || data.secretKey || '';
            secretEl.textContent = setupSecret;

            if (data.recovery_codes) {
                setupRecoveryCodes = data.recovery_codes;
            }

            setupInitialized = true;
        } catch (error) {
            console.error('2FA setup error:', error);
            errorEl.textContent = 'Failed to initialize 2FA setup. Please refresh and try again.';
            errorEl.style.display = 'block';
        }
    }

    async function confirmTwoFactor() {
        const codeInput = document.getElementById('totp-code');
        const errorEl = document.getElementById('setup-error');
        const btn = document.getElementById('verify-btn');
        const code = codeInput.value.trim();

        if (code.length !== 6) {
            errorEl.textContent = 'Please enter a 6-digit code.';
            errorEl.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Verifying...';
        errorEl.style.display = 'none';

        try {
            const response = await fetch('{{ route("two-factor.confirm") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code: code }),
            });

            if (response.ok) {
                // Show recovery codes
                const codesContainer = document.getElementById('setup-recovery-codes');
                if (setupRecoveryCodes.length > 0) {
                    codesContainer.innerHTML = setupRecoveryCodes.map(c =>
                        `<code class="authkit-recovery-code">${c}</code>`
                    ).join('');
                } else {
                    // Fetch recovery codes if not already available
                    const codesResponse = await fetch('{{ route("two-factor.recovery-codes") }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    if (codesResponse.ok) {
                        const codesData = await codesResponse.json();
                        setupRecoveryCodes = codesData.codes || [];
                        codesContainer.innerHTML = setupRecoveryCodes.map(c =>
                            `<code class="authkit-recovery-code">${c}</code>`
                        ).join('');
                    }
                }

                // Switch to recovery codes step
                document.getElementById('step-qr').classList.remove('active');
                document.getElementById('step-recovery').classList.add('active');
            } else {
                const data = await response.json();
                errorEl.textContent = data.message || 'Invalid code. Please try again.';
                errorEl.style.display = 'block';
            }
        } catch (error) {
            console.error('2FA confirm error:', error);
            errorEl.textContent = 'An error occurred. Please try again.';
            errorEl.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Verify & Enable';
        }
    }

    function downloadSetupCodes() {
        if (setupRecoveryCodes.length === 0) {
            alert('No recovery codes to download');
            return;
        }

        const text = 'Recovery Codes for ' + window.location.hostname + '\n' +
                     'Generated: ' + new Date().toISOString() + '\n\n' +
                     setupRecoveryCodes.join('\n') + '\n\n' +
                     'Store these codes in a secure location. Each code can only be used once.';

        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'recovery-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    }

    function finishSetup() {
        window.location.reload();
    }
</script>
