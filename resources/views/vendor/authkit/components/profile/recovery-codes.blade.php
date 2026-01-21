<div class="authkit-recovery-codes" id="recovery-codes-container">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-recovery-codes {
            margin-top: 1rem;
        }

        .authkit-recovery-codes-alert {
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
            background: var(--authkit-bg-secondary, #f9fafb);
            padding: 0.5rem 0.75rem;
            border-radius: var(--authkit-radius, 0.5rem);
            font-size: 0.875rem;
            text-align: center;
        }

        .authkit-recovery-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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

        .authkit-btn-secondary {
            background: var(--authkit-bg-secondary, #f3f4f6);
            color: var(--authkit-text, #1f2937);
        }

        .authkit-btn-secondary:hover {
            background: #e5e7eb;
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

        .authkit-recovery-status {
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }

        .authkit-text-success {
            color: #059669;
        }

        .authkit-text-error {
            color: var(--authkit-danger, #dc2626);
        }
    </style>

    <div class="authkit-recovery-codes-alert">
        <strong>Important!</strong> Store these recovery codes in a secure location.
        They can be used to recover access to your account if you lose your authenticator device.
        Each code can only be used once.
    </div>

    <div class="authkit-recovery-codes-grid" id="recovery-codes-list">
        <span class="authkit-recovery-code">Loading...</span>
    </div>

    <div class="authkit-recovery-actions">
        <button type="button" class="authkit-btn authkit-btn-secondary" onclick="downloadRecoveryCodes()">
            Download Codes
        </button>
        <button type="button" class="authkit-btn authkit-btn-danger" onclick="regenerateRecoveryCodes()" id="regenerate-btn">
            Regenerate Codes
        </button>
    </div>

    <div id="recovery-status" class="authkit-recovery-status"></div>
</div>

<script>
    let recoveryCodes = [];

    // Load recovery codes on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadRecoveryCodes();
    });

    async function loadRecoveryCodes() {
        const listEl = document.getElementById('recovery-codes-list');
        const statusEl = document.getElementById('recovery-status');

        try {
            const response = await fetch('{{ route("two-factor.recovery-codes") }}', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!response.ok) {
                throw new Error('Failed to load recovery codes');
            }

            const data = await response.json();
            recoveryCodes = data.codes || [];

            renderCodes(listEl);
        } catch (error) {
            console.error('Error loading recovery codes:', error);
            listEl.innerHTML = '<span class="authkit-recovery-code">Failed to load codes</span>';
            statusEl.innerHTML = '<span class="authkit-text-error">Error loading recovery codes. Please try refreshing the page.</span>';
        }
    }

    function renderCodes(listEl) {
        if (recoveryCodes.length === 0) {
            listEl.innerHTML = '<span class="authkit-recovery-code">No recovery codes available</span>';
            return;
        }

        listEl.innerHTML = recoveryCodes.map(code =>
            `<code class="authkit-recovery-code">${code}</code>`
        ).join('');
    }

    function downloadRecoveryCodes() {
        if (recoveryCodes.length === 0) {
            alert('No recovery codes to download');
            return;
        }

        const text = 'Recovery Codes for ' + window.location.hostname + '\n' +
                     'Generated: ' + new Date().toISOString() + '\n\n' +
                     recoveryCodes.join('\n') + '\n\n' +
                     'Store these codes in a secure location. Each code can only be used once.';

        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'recovery-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    }

    async function regenerateRecoveryCodes() {
        if (!confirm('Are you sure you want to regenerate your recovery codes? Your old codes will no longer work.')) {
            return;
        }

        const btn = document.getElementById('regenerate-btn');
        const statusEl = document.getElementById('recovery-status');
        const listEl = document.getElementById('recovery-codes-list');

        btn.disabled = true;
        statusEl.innerHTML = '<span>Regenerating codes...</span>';

        try {
            const response = await fetch('{{ route("two-factor.recovery-codes.regenerate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!response.ok) {
                throw new Error('Failed to regenerate recovery codes');
            }

            const data = await response.json();
            recoveryCodes = data.codes || [];

            renderCodes(listEl);
            statusEl.innerHTML = '<span class="authkit-text-success">Recovery codes regenerated successfully!</span>';
        } catch (error) {
            console.error('Error regenerating recovery codes:', error);
            statusEl.innerHTML = '<span class="authkit-text-error">Failed to regenerate codes. Please try again.</span>';
        } finally {
            btn.disabled = false;
        }
    }
</script>
