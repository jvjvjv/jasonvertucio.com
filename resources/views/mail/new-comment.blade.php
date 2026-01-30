<x-mail::message>
# A New comment on {{ $comment->post->title }}

**From:** {{ $comment->name }} <[{{ $comment->email  }}]({{ $comment->email  }})><br>
**Date:** {{ \Carbon\Carbon::create($comment->created_at) }}

---

**Comment:**

{{ $comment->message }}

</x-mail::message>
