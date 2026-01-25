Hello {{ $code->name }},

Jason has shared his resume with you. Your resume share code is: <strong>{{ $code->id }}</strong>

View the resume here: <a href="{{ $shareUrl }}">{{ $shareUrl }}</a>

This code {{ $code->expires_at ? 'expires on ' . $code->expires_at->format('F j, Y') : 'never expires' }}.

---

If you have any questions, feel free to reach out to Jason.
