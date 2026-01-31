@props(['enabled' => false])

<div class="authkit-two-factor">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-two-factor {
            /* Base styles */
        }

        .authkit-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: var(--authkit-radius, 0.5rem);
            margin-bottom: 1rem;
        }

        .authkit-status-success {
            background: #d1fae5;
            color: #065f46;
        }

        .authkit-icon {
            width: 1.25rem;
            height: 1.25rem;
        }

        .authkit-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .authkit-text {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
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

        .authkit-inline {
            display: inline;
        }

        .authkit-recovery-codes-container {
            margin-top: 1rem;
            display: none;
        }

        .authkit-recovery-codes-container.show {
            display: block;
        }

        .authkit-setup-container {
            margin-top: 1rem;
            display: none;
        }

        .authkit-setup-container.show {
            display: block;
        }
    </style>

    @if($enabled)
        <div class="authkit-status authkit-status-success">
            <svg class="authkit-icon" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>Two-factor authentication is enabled</span>
        </div>

        <div class="authkit-actions">
            <button type="button" class="authkit-btn authkit-btn-secondary" onclick="toggleRecoveryCodes()">
                <span id="toggle-codes-text">View Recovery Codes</span>
            </button>

            <form method="POST" action="{{ route('two-factor.destroy') }}" class="authkit-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="authkit-btn authkit-btn-danger"
                    onclick="return confirm('Are you sure you want to disable two-factor authentication?')">
                    Disable 2FA
                </button>
            </form>
        </div>

        <div id="recovery-codes-container" class="authkit-recovery-codes-container">
            @include('authkit::components.profile.recovery-codes')
        </div>
    @else
        <p class="authkit-text">
            Add additional security to your account using two-factor authentication.
            When enabled, you'll be prompted for a secure, random code during login.
        </p>

        <button type="button" class="authkit-btn authkit-btn-primary" onclick="toggleSetup()">
            Enable Two-Factor Authentication
        </button>

        <div id="setup-container" class="authkit-setup-container">
            @include('authkit::components.profile.two-factor-setup')
        </div>
    @endif
</div>

<script>
    function toggleRecoveryCodes() {
        const container = document.getElementById('recovery-codes-container');
        const toggleText = document.getElementById('toggle-codes-text');

        if (container.classList.contains('show')) {
            container.classList.remove('show');
            toggleText.textContent = 'View Recovery Codes';
        } else {
            container.classList.add('show');
            toggleText.textContent = 'Hide Recovery Codes';
        }
    }

    function toggleSetup() {
        const container = document.getElementById('setup-container');
        container.classList.toggle('show');
    }
</script>
