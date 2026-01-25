Hello {{ $code->name }},

Jason has updated his resume to version {{ $version }}. Your resume share code remains valid: <strong>{{ $code->id }}</strong>

View the updated resume here: <a href="{{ $shareUrl }}">{{ $shareUrl }}</a>

---

If you have any questions, feel free to reach out to Jason.
