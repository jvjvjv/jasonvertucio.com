# Current Comment System Architecture

## Component Overview

### 1. Comment Model (`app/Models/Comment.php`)

**Purpose**: Eloquent model representing comments with nested structure support

**Key Features**:
- Soft deletes enabled
- Self-referencing parent-child relationship via `parent_id`
- Facebook integration fields (`fb_comment_id`, `fb_parent_comment_id`)

**Current Fillable Fields**:
```php
protected $fillable = [
    'parent_id',
    'fb_comment_id', 
    'fb_parent_comment_id',
    'name',
    'email', 
    'message',
    'created_at',
    'updated_at'
];
```

**Missing Relationships** (needs to be added):
```php
public function parent() { 
    return $this->belongsTo(Comment::class, 'parent_id'); 
}

public function replies() { 
    return $this->hasMany(Comment::class, 'parent_id'); 
}

public function post() { 
    return $this->belongsTo(\Canvas\Models\Post::class); 
}
```

### 2. Database Schema

**Table**: `comments`

**Structure**:
- `id` - Primary key
- `parent_id` - Self-referencing foreign key for nesting
- `fb_comment_id` - Facebook comment ID (nullable)
- `fb_comment_parent_id` - Facebook parent comment ID (nullable) 
- `name` - Commenter name (128 chars)
- `email` - Commenter email (128 chars, nullable)
- `message` - Comment content (text)
- `created_at`, `updated_at` - Timestamps
- `deleted_at` - Soft delete timestamp

**Constraints**:
- Foreign key on `parent_id` references `comments.id` with SET NULL on delete

### 3. Comment Observer (`app/Observers/CommentObserver.php`)

**Purpose**: Handles comment lifecycle events

**Current Implementation**:
- Sends email notifications when comments are created
- Email sent to `me@jasonvertucio.com`
- Uses `CommentReceivedMail` mailable class

### 4. Facebook Integration (`app/Http/Controllers/FacebookCallbackController.php`)

**Purpose**: Webhook endpoint for Facebook comment notifications

**Functionality**:
- Handles Facebook webhook verification
- Processes `plugin_comment` and `plugin_comment_reply` events
- Automatically creates Comment records from Facebook data
- Maps Facebook user IDs to email field
- Preserves Facebook comment hierarchy via `parent_id`

### 5. Frontend Display (`resources/views/blog/single.blade.php`)

**Current State**:
- Uses Facebook Comments widget
- No integration with custom Comment model
- Located in `.comments` div on blog post pages

## Data Flow

```
Facebook Comment → Facebook Webhook → FacebookCallbackController → Comment Model → CommentObserver → Email Notification
```

## Missing Components

1. **Web Interface**: No forms or views for creating/displaying custom comments
2. **Comment Controller**: No CRUD operations for comments
3. **Template Integration**: Custom comments not displayed in blog posts
4. **Validation**: No input validation for comment data
5. **Moderation**: No admin interface for comment management
6. **Spam Protection**: No CAPTCHA or spam filtering
7. **Post Association**: Comments not linked to specific blog posts