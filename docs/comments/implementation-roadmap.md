# Comment System Implementation Roadmap

## Phase 1: Model Enhancement & Basic Infrastructure

### 1.1 Complete Comment Model Relationships
**File**: `app/Models/Comment.php`

```php
// Add these relationships
public function parent()
{
    return $this->belongsTo(Comment::class, 'parent_id');
}

public function replies() 
{
    return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
}

public function post()
{
    return $this->belongsTo(\Canvas\Models\Post::class, 'post_id');
}

public function user()
{
    return $this->belongsTo(\App\Models\User::class, 'user_id');
}

// Add helpful methods
public function isReply()
{
    return !is_null($this->parent_id);
}

public function getRepliesCountAttribute()
{
    return $this->replies()->count();
}
```

### 1.2 Add Post Association Migration
**File**: `database/migrations/xxxx_add_post_id_to_comments.php`

```php
public function up()
{
    Schema::table('comments', function (Blueprint $table) {
        $table->unsignedBigInteger('post_id')->nullable()->after('id');
        $table->foreign('post_id')->references('id')->on('canvas_posts')->onDelete('cascade');
        
        // Optional: Add user_id for registered users
        $table->unsignedBigInteger('user_id')->nullable()->after('post_id');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        
        // Moderation fields
        $table->enum('status', ['pending', 'approved', 'spam', 'rejected'])->default('approved')->after('message');
        $table->ipAddress('ip_address')->nullable()->after('status');
    });
}
```

### 1.3 Update Comment Model Fillable Fields
```php
protected $fillable = [
    'post_id',
    'user_id', 
    'parent_id',
    'name',
    'email',
    'message',
    'status',
    'ip_address',
    'fb_comment_id',
    'fb_comment_parent_id',
];
```

**Estimated Time**: 2-3 hours

---

## Phase 2: Comment Controller & Validation

### 2.1 Create Comment Controller
**File**: `app/Http/Controllers/CommentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Canvas\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:128',
            'email' => 'nullable|email|max:128', 
            'message' => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'email' => $request->email,
            'message' => $request->message,
            'ip_address' => $request->ip(),
            'status' => 'approved' // or 'pending' for moderation
        ]);

        return redirect()->back()->with('success', 'Comment posted successfully!');
    }

    public function destroy(Comment $comment)
    {
        // Only allow deletion by comment owner or admin
        $comment->delete();
        return redirect()->back()->with('success', 'Comment deleted.');
    }
}
```

### 2.2 Add Comment Routes
**File**: `routes/web.php`

```php
Route::post('/blog/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
```

**Estimated Time**: 3-4 hours

---

## Phase 3: Frontend Templates & Display

### 3.1 Create Comment Partial Views
**File**: `resources/views/comments/list.blade.php`

```blade
@if($comments->count() > 0)
    <div class="comments-list">
        @foreach($comments->where('parent_id', null) as $comment)
            @include('comments.single', ['comment' => $comment, 'level' => 0])
        @endforeach
    </div>
@else
    <p class="no-comments">No comments yet. Be the first to comment!</p>
@endif
```

**File**: `resources/views/comments/single.blade.php`

```blade
<div class="comment" data-comment-id="{{ $comment->id }}" style="margin-left: {{ $level * 20 }}px;">
    <div class="comment-header">
        <strong>{{ $comment->name }}</strong>
        <small class="text-muted">{{ $comment->created_at->format('M j, Y g:ia') }}</small>
        @if($comment->replies_count > 0)
            <span class="badge badge-secondary">{{ $comment->replies_count }} replies</span>
        @endif
    </div>
    <div class="comment-body">
        {!! nl2br(e($comment->message)) !!}
    </div>
    <div class="comment-actions">
        <button class="btn btn-sm btn-link reply-btn" data-comment-id="{{ $comment->id }}">Reply</button>
    </div>
    
    @if($comment->replies->count() > 0 && $level < 3)
        @foreach($comment->replies as $reply)
            @include('comments.single', ['comment' => $reply, 'level' => $level + 1])
        @endforeach
    @endif
</div>
```

### 3.2 Create Comment Form
**File**: `resources/views/comments/form.blade.php`

```blade
<div class="comment-form" id="comment-form-{{ $parentId ?? 0 }}">
    <h4>{{ isset($parentId) ? 'Reply to Comment' : 'Leave a Comment' }}</h4>
    
    <form method="POST" action="{{ route('comments.store', $post) }}">
        @csrf
        @if(isset($parentId))
            <input type="hidden" name="parent_id" value="{{ $parentId }}">
        @endif
        
        <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" name="email" value="{{ old('email') }}">
            <small class="form-text text-muted">Optional. Will not be published.</small>
        </div>
        
        <div class="form-group">
            <label for="message">Message *</label>
            <textarea class="form-control" name="message" rows="4" required>{{ old('message') }}</textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Post Comment</button>
        @if(isset($parentId))
            <button type="button" class="btn btn-secondary cancel-reply">Cancel</button>
        @endif
    </form>
</div>
```

### 3.3 Update Blog Single Template
**File**: `resources/views/blog/single.blade.php`

Replace the Facebook comments section:

```blade
<div class="comments">
    <h3>Comments ({{ $post->comments()->where('parent_id', null)->count() }})</h3>
    
    @include('comments.list', ['comments' => $post->comments])
    
    @include('comments.form', ['post' => $post])
</div>
```

**Estimated Time**: 4-6 hours

---

## Phase 4: JavaScript Enhancement

### 4.1 Add Reply Functionality
**File**: `resources/js/comments.js`

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Handle reply button clicks
    document.querySelectorAll('.reply-btn').forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const originalForm = document.getElementById('comment-form-0');
            const replyForm = originalForm.cloneNode(true);
            
            // Update form for reply
            replyForm.id = `comment-form-${commentId}`;
            replyForm.querySelector('h4').textContent = 'Reply to Comment';
            
            // Add hidden parent_id input
            const parentInput = document.createElement('input');
            parentInput.type = 'hidden';
            parentInput.name = 'parent_id';
            parentInput.value = commentId;
            replyForm.querySelector('form').appendChild(parentInput);
            
            // Add cancel button functionality
            replyForm.querySelector('.cancel-reply').addEventListener('click', function() {
                replyForm.remove();
            });
            
            // Insert after the comment
            const comment = document.querySelector(`[data-comment-id="${commentId}"]`);
            comment.parentNode.insertBefore(replyForm, comment.nextSibling);
            
            // Focus on form
            replyForm.querySelector('textarea[name="message"]').focus();
        });
    });
});
```

### 4.2 Update webpack.mix.js
```javascript
// Add to existing mix configuration
.js('resources/js/comments.js', 'public/js')
```

**Estimated Time**: 2-3 hours

---

## Phase 5: Facebook Migration Strategy

### 5.1 Create Migration Command
**File**: `app/Console/Commands/MigrateFacebookCommentsCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Comment;
use Canvas\Models\Post;

class MigrateFacebookCommentsCommand extends Command
{
    protected $signature = 'comments:migrate-facebook {--dry-run : Show what would be migrated}';
    protected $description = 'Migrate Facebook comments to associate with blog posts';

    public function handle()
    {
        $comments = Comment::whereNotNull('fb_comment_id')
                          ->whereNull('post_id')
                          ->get();

        $this->info("Found {$comments->count()} Facebook comments to migrate");

        foreach($comments as $comment) {
            // Logic to determine which post this comment belongs to
            // This would require additional data or URL analysis
            $post = $this->findPostForComment($comment);
            
            if ($post && !$this->option('dry-run')) {
                $comment->update(['post_id' => $post->id]);
                $this->info("Migrated comment {$comment->id} to post '{$post->title}'");
            }
        }
    }
    
    private function findPostForComment($comment)
    {
        // Implementation depends on available data
        // Could match based on Facebook URLs, timestamps, etc.
    }
}
```

### 5.2 Facebook Graph API Integration
Consider using Facebook Graph API to:
- Fetch complete comment threads
- Get commenter profile information  
- Sync comment deletions/updates
- Import historical comments

**Estimated Time**: 6-8 hours

---

## Phase 6: Advanced Features

### 6.1 Comment Moderation
- Admin interface for comment approval
- Spam detection/filtering
- Bulk moderation actions

### 6.2 User Authentication Integration
- Allow registered users to comment
- Edit/delete own comments
- Comment ownership tracking

### 6.3 Rich Content Support
- Markdown parsing
- Link preview generation
- Image/media embedding

### 6.4 Performance Optimization
- Comment pagination
- Lazy loading for nested replies
- Caching for comment counts

**Estimated Time**: 10-15 hours

---

## Total Implementation Timeline

| Phase | Description | Estimated Time |
|-------|-------------|----------------|
| 1 | Model Enhancement | 2-3 hours |
| 2 | Controller & Validation | 3-4 hours | 
| 3 | Frontend Templates | 4-6 hours |
| 4 | JavaScript Enhancement | 2-3 hours |
| 5 | Facebook Migration | 6-8 hours |
| 6 | Advanced Features | 10-15 hours |

**Total**: 27-39 hours (3-5 days of development)

## Success Criteria

- [ ] Nested comments display correctly
- [ ] Reply functionality works via web interface
- [ ] Facebook comments are preserved and associated with posts
- [ ] Email notifications continue working
- [ ] No breaking changes to existing Facebook webhook
- [ ] Mobile-responsive comment interface
- [ ] Basic spam protection implemented