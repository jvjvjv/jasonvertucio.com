@once
    <style>
        :root {
            --authkit-primary: var(--primary, #113199);
            --authkit-primary-hover: var(--primary-strong, #0067a6);
            --authkit-secondary: var(--secondary, #10b981);
            --authkit-danger: var(--danger, #ef4444);
            --authkit-text: var(--text-primary, #e2e8f0);
            --authkit-text-muted: var(--text-muted, #94a3b8);
            --authkit-border: var(--border, rgba(15, 23, 42, 0.12));
            --authkit-bg: var(--surface, #0b1021);
            --authkit-bg-secondary: var(--panel, rgba(15, 23, 42, 0.55));
            --authkit-radius: 0.85rem;
            --authkit-shadow: 0 24px 40px rgba(5, 6, 21, 0.35);
        }

        .authkit-form-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .authkit-form {
            background: var(--panel);
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 1rem;
            padding: 2rem;
            color: var(--authkit-text-primary);
        }

        .authkit-form-group {
            margin-bottom: 1.25rem;
        }

        .authkit-label {
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .authkit-input {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid rgba(15, 23, 42, 0.15);
            border-radius: 0.75rem;
            background: rgba(5, 6, 21, 0.28);
            color: var(--panel);
            font-size: 1rem;
            transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
        }

        .authkit-input:focus {
            outline: none;
            background-color: var(--panel);
            color: var(--text-primary);
            border-color: var(--primary);
        }

        .authkit-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;

            color: var(--authkit-text);
        }

        .authkit-button {
            width: 100%;
            padding: 0.9rem 1rem;
            border-radius: 0.85rem;
            border: none;
            background: var(--primary);
            color: var(--panel);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .authkit-button:hover {
            transform: translateY(-1px);
        }

        .authkit-button-secondary {
            background: var(--panel);
            color: var(--text-primary);
            border: 1px solid rgba(248, 250, 252, 0.3);
            margin-top: 0.75rem;
        }

        .authkit-error {
            color: var(--authkit-danger);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .authkit-links {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            font-size: 0.875rem;
        }

        .authkit-link {
            color: var(--primary);
            text-decoration: none;
        }

        .authkit-link:hover {
            text-decoration: underline;
        }

        .authkit-divider {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 1.5rem 0;
        }

        .authkit-divider::before,
        .authkit-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(15, 23, 42, 0.12);
        }

        .authkit-divider::before {
            margin-right: 0.65rem;
        }

        .authkit-divider::after {
            margin-left: 0.65rem;
        }

        /* Utility buttons + badges */
        .authkit-btn {
            padding: 0.65rem 1rem;
            border-radius: var(--authkit-radius);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
        }

        .authkit-btn:hover {
            transform: translateY(-1px);
        }

        .authkit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .authkit-btn-primary {
            background: var(--authkit-primary);
            color: var(--surface, #fff);
        }

        .authkit-btn-primary:hover {
            background: var(--authkit-primary-hover);
        }

        .authkit-btn-secondary {
            background: var(--authkit-bg-secondary);
            color: var(--authkit-text);
            border-color: var(--authkit-border);
        }

        .authkit-btn-danger {
            background: var(--authkit-danger);
            color: #fff;
        }

        .authkit-btn-sm {
            padding: 0.4rem 0.65rem;
            font-size: 0.8rem;
        }

        .authkit-text-muted {
            color: var(--authkit-text-muted);
        }

        .authkit-text-success {
            color: #059669;
        }

        .authkit-text-error {
            color: var(--authkit-danger);
        }

        .authkit-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .authkit-badge-role {
            background: #dbeafe;
            color: #1e40af;
        }

        .authkit-badge-permission {
            background: #dcfce7;
            color: #166534;
        }
    </style>
@endonce
