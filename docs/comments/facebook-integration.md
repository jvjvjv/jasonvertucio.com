# Facebook Integration Documentation

## Overview

The site captures Facebook comments through a webhook system that automatically creates local Comment records when users comment via Facebook's comment widget.

## Webhook Configuration

### Endpoint
- **URL**: `/mlopnadjs22tn` (non-standard path for security)
- **Controller**: `FacebookCallbackController`
- **Methods**: GET (verification), POST (webhook data)

### Authentication
- **Verify Token**: `jasonvertucioisamotherfuckingdog`
- Facebook sends this token during webhook verification
- Returns 401 if token doesn't match

### Webhook Verification Flow
```php
if ($request->hub_mode === "subscribe") {
    return response($request->hub_challenge, 200);
}
```

## Comment Processing

### Event Types Handled

#### 1. `plugin_comment` - New Top-Level Comment
```php
Comment::create([
    'fb_comment_id' => $value['id'],
    'name' => $value['from']['name'], 
    'email' => $value['from']['id'],  // Facebook user ID stored as email
    'message' => $value['message'],
    'created_at' => $value['created_time']
]);
```

#### 2. `plugin_comment_reply` - Reply to Existing Comment
```php
$old_comment = Comment::firstWhere('fb_comment_id', $value['parent']['id']);
$old_comment_id = $old_comment->id ?? null;

Comment::create([
    'parent_id' => $old_comment_id,
    'fb_comment_id' => $value['id'],
    'fb_comment_parent_id' => $value['parent']['id'],
    'name' => $value['from']['name'],
    'email' => $value['from']['id'],
    'message' => $value['message'], 
    'created_at' => $value['created_time']
]);
```

## Data Mapping

### Facebook → Comment Model
| Facebook Field | Comment Field | Notes |
|----------------|---------------|-------|
| `id` | `fb_comment_id` | Unique Facebook comment ID |
| `parent.id` | `fb_comment_parent_id` | Parent Facebook comment ID |
| `from.name` | `name` | Commenter's Facebook display name |
| `from.id` | `email` | Facebook user ID (not actual email) |
| `message` | `message` | Comment text content |
| `created_time` | `created_at` | Facebook timestamp |

### Hierarchy Mapping
- Facebook parent-child relationships are preserved via `parent_id`
- System looks up existing comments by `fb_comment_id` to establish relationships
- If parent comment not found, `parent_id` is set to null

## Frontend Integration

### Blog Post Template (`resources/views/blog/single.blade.php`)

#### Facebook Comments Widget
```html
<div class="fb-comments"
     data-href="{{ Request::url() }}"
     data-lazy="true"
     data-order-by="reverse_time" 
     data-colorscheme="light"
     data-numposts="10"
     data-width="100%">
</div>
```

#### Required Meta Tags
```html
<meta property="fb:app_id" content="{{ env('FB_APP_ID') }}" />
```

#### Widget Configuration
- **URL**: Current blog post URL
- **Order**: Reverse chronological (newest first)
- **Theme**: Light mode
- **Limit**: 10 comments displayed
- **Width**: 100% responsive

## Environment Configuration

### Required Environment Variables
- `FB_APP_ID` - Facebook application ID
- Webhook verify token (hardcoded in controller)

## Logging & Debugging

### Request Logging
```php
Log::info($request->method(), $request->input());
```
All webhook requests are logged with method and payload data.

### Response Format
```json
{
    "method": "POST",
    "data": { /* webhook payload */ }
}
```

## Security Considerations

1. **Non-standard webhook URL** reduces discovery by bots
2. **Verify token validation** prevents unauthorized webhook calls
3. **IP restrictions** could be added for Facebook's webhook IPs
4. **HTTPS required** for production webhook endpoints

## Limitations

1. **Email field misuse**: Facebook user IDs stored in email field
2. **No post association**: Comments not linked to specific blog posts
3. **No Facebook profile links**: User IDs not converted to profile URLs
4. **Single-direction sync**: Changes to local comments don't sync back to Facebook
5. **No moderation sync**: Deleted Facebook comments may remain in local database