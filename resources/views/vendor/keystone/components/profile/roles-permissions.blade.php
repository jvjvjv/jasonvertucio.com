@props(['roles', 'permissions'])

<div class="authkit-roles-permissions">
    @include('authkit::components.authkit-styles')
    <style>
        .authkit-roles-permissions {
            /* Base styles */
        }

        .authkit-subsection {
            margin-bottom: 1.5rem;
        }

        .authkit-subsection:last-child {
            margin-bottom: 0;
        }

        .authkit-subsection h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--authkit-text-muted, #6b7280);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .authkit-badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .authkit-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .authkit-badge-role {
            background: #dbeafe;
            color: #1e40af;
        }

        .authkit-badge-permission {
            background: #dcfce7;
            color: #166534;
        }

        .authkit-text-muted {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>

    <div class="authkit-subsection">
        <h3>Roles</h3>
        <div class="authkit-badge-list">
            @forelse($roles as $role)
                <span class="authkit-badge authkit-badge-role">{{ ucfirst($role) }}</span>
            @empty
                <span class="authkit-text-muted">No roles assigned</span>
            @endforelse
        </div>
    </div>

    <div class="authkit-subsection">
        <h3>Permissions</h3>
        <div class="authkit-badge-list">
            @forelse($permissions as $permission)
                <span class="authkit-badge authkit-badge-permission">{{ $permission }}</span>
            @empty
                <span class="authkit-text-muted">No permissions assigned</span>
            @endforelse
        </div>
    </div>
</div>
