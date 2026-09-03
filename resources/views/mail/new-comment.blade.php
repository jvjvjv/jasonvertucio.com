<x-mail::message>
# A New comment on {{ $comment->post?->title ?? 'your site' }}

**From:** {{ $comment->name }}@if ($comment->email) <[{{ $comment->email }}](mailto:{{ $comment->email }})>@endif<br>
**Date:** {{ \Carbon\Carbon::create($comment->created_at) }}

---

**Comment:**

{{ $comment->message }}

</x-mail::message>
