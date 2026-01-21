@props(['user'])

<div class="authkit-account-info">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-account-info {
            /* Base styles */
        }

        .authkit-info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--authkit-border, #e5e7eb);
        }

        .authkit-info-row:last-child {
            border-bottom: none;
        }

        .authkit-info-label {
            font-weight: 500;
            color: var(--authkit-text-muted, #6b7280);
        }

        .authkit-info-value {
            color: var(--authkit-text, #1f2937);
        }
    </style>

    <div class="authkit-info-row">
        <span class="authkit-info-label">Name</span>
        <span class="authkit-info-value">{{ $user->name }}</span>
    </div>
    <div class="authkit-info-row">
        <span class="authkit-info-label">Email</span>
        <span class="authkit-info-value">{{ $user->email }}</span>
    </div>
    @if($user->email_verified_at)
    <div class="authkit-info-row">
        <span class="authkit-info-label">Email Verified</span>
        <span class="authkit-info-value">{{ $user->email_verified_at->format('M j, Y') }}</span>
    </div>
    @endif
</div>
