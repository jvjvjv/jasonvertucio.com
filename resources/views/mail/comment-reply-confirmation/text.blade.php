Confirm reply emails for your comment on "{!! $postTitle !!}"

You asked to be emailed when someone replies to your comment. Confirm it's you and we'll start sending them.@if ($wasOptedOut) This address had stopped all comment emails; confirming turns them back on.@endif

{!! $confirmUrl !!}

This link works for {{ $expiresInDays }} {{ \Illuminate\Support\Str::plural('day', $expiresInDays) }}.

--
Didn't ask for this? Ignore this email and nothing more will be sent, or stop all comment emails to this address:
{!! $unsubscribeUrl !!}
