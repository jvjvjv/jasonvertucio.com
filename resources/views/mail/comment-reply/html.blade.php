<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $replierName }} replied to your comment</title>
</head>
<body style="margin:0;padding:24px 16px;background:#f9fafb;color:#1f2937;font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.5;">
<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:6px;padding:24px;">
<h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#1b587c;">{{ $replierName }} replied to your comment on &ldquo;{{ $postTitle }}&rdquo;</h1>
<blockquote style="margin:0 0 20px;padding:12px 16px;border-left:3px solid #b35e06;background:#f9fafb;color:#374151;">{!! nl2br(e($replyMessage), false) !!}</blockquote>
<p style="margin:0 0 24px;"><a href="{{ $replyUrl }}" style="display:inline-block;padding:8px 16px;background:#1b587c;color:#ffffff;text-decoration:none;border-radius:4px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;">Read the reply</a></p>
<p style="margin:0;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">You asked to be emailed about replies to your comment. <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Unsubscribe</a></p>
</div>
</body>
</html>
