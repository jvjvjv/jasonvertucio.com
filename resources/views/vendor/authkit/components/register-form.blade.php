<div class="authkit-form-container">
    <form method="POST" action="{{ $action }}" class="authkit-form">
        @csrf

        @if ($errors->any())
            <div class="authkit-error" style="margin-bottom: 1rem;">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (in_array('name', $requiredFields))
            <div class="authkit-form-group">
                <label for="name" class="authkit-label">Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autofocus
                    class="authkit-input"
                >
                @error('name')
                    <span class="authkit-error">{{ $message }}</span>
                @enderror
            </div>
        @endif

        @if (in_array('email', $requiredFields))
            <div class="authkit-form-group">
                <label for="email" class="authkit-label">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    class="authkit-input"
                >
                @error('email')
                    <span class="authkit-error">{{ $message }}</span>
                @enderror
            </div>
        @endif

        @if (in_array('password', $requiredFields))
            <div class="authkit-form-group">
                <label for="password" class="authkit-label">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    class="authkit-input"
                >
                @error('password')
                    <span class="authkit-error">{{ $message }}</span>
                @enderror
            </div>
        @endif

        @if (in_array('password_confirmation', $requiredFields))
            <div class="authkit-form-group">
                <label for="password_confirmation" class="authkit-label">Confirm Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    class="authkit-input"
                >
            </div>
        @endif

        <button type="submit" class="authkit-button">
            Register
        </button>

        @if ($showLoginLink)
            <div class="authkit-links">
                <a href="{{ route('login') }}" class="authkit-link">Already have an account? Log in</a>
            </div>
        @endif
    </form>
</div>
