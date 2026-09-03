@props(['user'])

{{--
    .keystone-btn / .keystone-btn-primary / .keystone-checkbox come from the
    shared keystone-styles partial; the checkbox-label group below does not, so
    it is defined here (scoped) rather than relying on auth-preferences.blade.php's
    inline block happening to render first on the same page.
--}}
<div class="keystone-tool-visibility">
    @include('components.auth.keystone-styles')
    <style>
        .keystone-tool-visibility .keystone-text {
            color: var(--authkit-text-muted, #6b7280);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .keystone-tool-visibility .keystone-checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .keystone-tool-visibility .keystone-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--authkit-bg-secondary, #f9fafb);
            border-radius: var(--authkit-radius, 0.5rem);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .keystone-tool-visibility .keystone-checkbox-label:hover {
            background: #f3f4f6;
        }

        .keystone-tool-visibility .keystone-checkbox {
            margin-top: 0.25rem;
            width: 1rem;
            height: 1rem;
            cursor: pointer;
        }

        .keystone-tool-visibility .keystone-checkbox-content {
            display: flex;
            flex-direction: column;
        }

        .keystone-tool-visibility .keystone-checkbox-title {
            font-weight: 500;
            color: var(--authkit-text, #1f2937);
        }

        .keystone-tool-visibility .keystone-checkbox-description {
            font-size: 0.875rem;
            color: var(--authkit-text-muted, #6b7280);
        }
    </style>

    <form method="POST" action="{{ route('profile.tool-visibility.update') }}">
        @csrf
        @method('PUT')

        <p class="keystone-text">
            Tool call arguments and results can contain whatever a model or a fetched page put in them,
            including credentials handled on a visitor's behalf. Only you will see them, and only while
            you hold the AI tools permission.
        </p>

        <div class="keystone-checkbox-group">
            <label class="keystone-checkbox-label">
                <input type="checkbox" name="show_tool_payloads" value="1"
                    {{ $user->show_tool_payloads ? 'checked' : '' }}
                    class="keystone-checkbox">
                <div class="keystone-checkbox-content">
                    <span class="keystone-checkbox-title">Show tool call details in chat</span>
                    <span class="keystone-checkbox-description">
                        Display the arguments sent to each tool and the results it returned, both live
                        and in past conversations
                    </span>
                </div>
            </label>
        </div>

        <button type="submit" class="keystone-btn keystone-btn-primary">
            Save Preference
        </button>
    </form>
</div>
