<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirm reply emails</title>
</head>
<body style="margin:0;padding:24px 16px;background:#f9fafb;color:#1f2937;font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.5;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:6px;padding:24px;">
<h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#1b587c;">Confirm reply emails for your comment on &ldquo;{{ $postTitle }}&rdquo;</h1>
<p style="margin:0 0 20px;">You asked to be emailed when someone replies to your comment. Confirm it's you and we'll start sending them.@if ($wasOptedOut) This address had stopped all comment emails; confirming turns them back on.@endif</p>
<p style="margin:0 0 8px;"><a href="{{ $confirmUrl }}" style="display:inline-block;padding:8px 16px;background:#1b587c;color:#ffffff;text-decoration:none;border-radius:4px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;">Confirm reply emails</a></p>
<p style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">This link works for {{ $expiresInDays }} {{ \Illuminate\Support\Str::plural('day', $expiresInDays) }}.</p>
<p style="margin:0;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">Didn't ask for this? Ignore this email and nothing more will be sent, or <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">stop all comment emails to this address</a>.</p>
</div>
</body>
</html>
