@props(['identity', 'accountUrl'])

<div class="keystone-bspdx-account">
    @include('components.auth.keystone-styles')
    <style>
        .keystone-bspdx-account .keystone-info-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--authkit-border, #e5e7eb);
        }

        .keystone-bspdx-account .keystone-info-label {
            font-weight: 500;
            color: var(--authkit-text-muted, #6b7280);
        }

        .keystone-bspdx-account .keystone-info-value {
            color: var(--authkit-text, #1f2937);
            text-align: right;
            overflow-wrap: anywhere;
        }

        .keystone-bspdx-account .keystone-bspdx-note {
            margin-top: 1rem;
            color: var(--authkit-text, #1f2937);
        }

        .keystone-bspdx-account .keystone-bspdx-manage {
            display: inline-block;
            margin-top: 1rem;
            color: var(--color-link, #4351a0);
            font-weight: 500;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .keystone-bspdx-account .keystone-bspdx-manage:hover,
        .keystone-bspdx-account .keystone-bspdx-manage:focus-visible {
            color: var(--color-primary, #1b587c);
        }
    </style>

    @if ($identity)
        <div class="keystone-info-row">
            <span class="keystone-info-label">Signs in with</span>
            <span class="keystone-info-value">bspdx.com</span>
        </div>
        <div class="keystone-info-row">
            <span class="keystone-info-label">Connected</span>
            <span class="keystone-info-value">{{ $identity->created_at?->format('M j, Y') }}</span>
        </div>

        <p class="keystone-bspdx-note">
            Your password, two-factor authentication and other security settings are managed on bspdx.com.
        </p>
        <a class="keystone-bspdx-manage" href="{{ $accountUrl }}" rel="noopener">Manage your bspdx.com account</a>
    @else
        <p class="keystone-bspdx-note">
            This account is not connected to bspdx.com yet, so you will not be able to sign back in after you sign out.
            Ask the site owner to connect it.
        </p>
    @endif
</div>
