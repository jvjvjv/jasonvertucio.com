# Facebook to Custom Comments Migration Strategy

## Overview

This document outlines the strategy for migrating directly from Facebook Comments to a custom comment system while preserving existing comment data. This is a direct migration approach that eliminates any hybrid implementation phase.

## Migration Goals

1. **Preserve Data**: Keep all existing Facebook comments and their hierarchical structure
2. **Maintain URLs**: Ensure comment threads remain accessible at existing blog post URLs
3. **Direct Transition**: Migrate completely from Facebook to custom comments in one step
4. **User Experience**: Provide better commenting experience with full control over the system

## Current State Analysis

### Existing Facebook Comments Data

```sql
SELECT
    COUNT(*) as total_comments,
    COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as top_level,
    COUNT(CASE WHEN parent_id IS NOT NULL THEN 1 END) as replies
FROM comments
WHERE fb_comment_id IS NOT NULL;
```

### Data Quality Issues to Address

1. **No Post Association**: Comments not linked to specific blog posts
2. **Email Field Misuse**: Facebook user IDs stored in email field
3. **Missing User Profiles**: No connection to actual user accounts
4. **Incomplete Data**: May be missing some comment metadata

## Phase 1: Data Enhancement

### 1.1 Add Missing Database Fields

```sql
-- Add post association
ALTER TABLE comments ADD COLUMN post_id BIGINT UNSIGNED NULL;
ALTER TABLE comments ADD FOREIGN KEY (post_id) REFERENCES canvas_posts(id) ON DELETE CASCADE;

-- Add proper user fields
ALTER TABLE comments ADD COLUMN user_id BIGINT UNSIGNED NULL;
ALTER TABLE comments ADD COLUMN fb_user_name VARCHAR(255) NULL;
ALTER TABLE comments ADD COLUMN fb_user_id VARCHAR(64) NULL;

-- Move Facebook user ID from email to proper field
UPDATE comments
SET fb_user_id = email, email = NULL
WHERE fb_comment_id IS NOT NULL AND email NOT LIKE '%@%';
```

### 1.2 Establish Post Associations

#### Option A: URL-Based Matching

If Facebook webhook includes post URLs:

```php
// Parse Facebook comment URLs to extract post slugs
$fbComments = Comment::whereNotNull('fb_comment_id')
                    ->whereNull('post_id')
                    ->get();

foreach($fbComments as $comment) {
    // Extract post slug from Facebook data
    $slug = $this->extractSlugFromFacebookData($comment);
    $post = Post::where('slug', $slug)->first();

    if ($post) {
        $comment->update(['post_id' => $post->id]);
    }
}
```

#### Option B: Timestamp-Based Matching

Match comments to posts based on creation dates:

```php
foreach($fbComments as $comment) {
    // Find post published closest to comment date
    $post = Post::where('published_at', '<=', $comment->created_at)
                ->orderBy('published_at', 'DESC')
                ->first();

    // Validate match with additional criteria
    if ($this->validatePostMatch($post, $comment)) {
        $comment->update(['post_id' => $post->id]);
    }
}
```

#### Option C: Manual Association

Create admin interface for manual comment-to-post mapping:

```php
// Admin route to review and assign orphaned comments
Route::get('/admin/comments/orphaned', [AdminController::class, 'orphanedComments']);
Route::post('/admin/comments/{comment}/assign', [AdminController::class, 'assignToPost']);
```

### 1.3 Facebook Graph API Enhancement

#### Fetch Additional Comment Data

```php
use Facebook\Facebook;

$fb = new Facebook([
    'app_id' => config('services.facebook.app_id'),
    'app_secret' => config('services.facebook.app_secret'),
]);

// Enrich existing comments with additional data
foreach($comments as $comment) {
    try {
        $response = $fb->get("/{$comment->fb_comment_id}?fields=created_time,from,message,parent");
        $fbData = $response->getGraphNode();

        $comment->update([
            'fb_user_name' => $fbData['from']['name'] ?? $comment->name,
            'created_at' => $fbData['created_time'] ?? $comment->created_at,
        ]);
    } catch (Exception $e) {
        Log::warning("Could not fetch Facebook data for comment {$comment->id}");
    }
}
```

## Phase 2: Complete Data Migration

### 2.1 Final Facebook Comment Export

Export all remaining Facebook comments before migration:

```php
use Facebook\Facebook;

$fb = new Facebook([
    'app_id' => config('services.facebook.app_id'),
    'app_secret' => config('services.facebook.app_secret'),
]);

// Get all current Facebook comments for each post
$posts = Post::published()->get();

foreach($posts as $post) {
    try {
        $response = $fb->get("/{$post->facebook_post_id}/comments?fields=id,message,created_time,from,parent");
        $fbComments = $response->getGraphEdge();

        foreach($fbComments as $fbComment) {
            $this->createCustomComment($post, $fbComment);
        }
    } catch (Exception $e) {
        Log::error("Failed to fetch Facebook comments for post {$post->id}: " . $e->getMessage());
    }
}
```

### 2.2 Convert Facebook Comments to Custom Format

```php
private function createCustomComment($post, $fbComment, $parentComment = null)
{
    $comment = Comment::updateOrCreate(
        ['fb_comment_id' => $fbComment['id']],
        [
            'post_id' => $post->id,
            'parent_id' => $parentComment?->id,
            'name' => $fbComment['from']['name'],
            'email' => null, // Facebook doesn't provide emails
            'message' => $fbComment['message'],
            'approved' => true,
            'fb_user_id' => $fbComment['from']['id'],
            'fb_user_name' => $fbComment['from']['name'],
            'created_at' => $fbComment['created_time'],
            'updated_at' => now(),
        ]
    );

    // Handle nested replies
    if (isset($fbComment['comments']['data'])) {
        foreach($fbComment['comments']['data'] as $reply) {
            $this->createCustomComment($post, $reply, $comment);
        }
    }

    return $comment;
}
```

## Phase 3: Template System Update

### 3.1 Remove Facebook Comments Integration

Update all blog post templates to use only custom comments:

```blade
<div class="comments-section">
    {{-- Custom Comments Only --}}
    @if($post->comments->count() > 0)
        <div class="comments-list">
            <h4>{{ $post->comments->count() }} {{ Str::plural('Comment', $post->comments->count()) }}</h4>
            @include('comments.list', ['comments' => $post->comments->whereNull('parent_id')])
        </div>
    @endif

    {{-- New Comment Form --}}
    <div class="comment-form">
        <h4>Leave a Comment</h4>
        @include('comments.form', ['post' => $post])
    </div>
</div>
```

### 3.2 Update Comment Display Templates

```blade
{{-- resources/views/comments/list.blade.php --}}
@foreach($comments as $comment)
    <div class="comment" id="comment-{{ $comment->id }}">
        <div class="comment-meta">
            <strong>{{ $comment->name }}</strong>
            @if($comment->fb_user_id)
                <span class="badge badge-secondary">FB User</span>
            @endif
            <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
        </div>

        <div class="comment-content">
            {!! nl2br(e($comment->message)) !!}
        </div>

        {{-- Display replies --}}
        @if($comment->replies->count() > 0)
            <div class="comment-replies">
                @include('comments.list', ['comments' => $comment->replies])
            </div>
        @endif
    </div>
@endforeach
```

## Phase 4: Migration Execution

### 4.1 Create Migration Command

```bash
php artisan make:command MigrateToCustomComments
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Comment;
use Canvas\Models\Post;

class MigrateToCustomComments extends Command
{
    protected $signature = 'comments:migrate {--dry-run : Show what would be migrated without making changes} {--force : Skip confirmations}';
    protected $description = 'Migrate all Facebook comments to custom comment system';

    public function handle()
    {
        $this->info('Starting complete Facebook comment migration...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Step 1: Pre-migration validation
        $this->validatePrerequisites();

        // Step 2: Create full backup
        $this->createBackup();

        // Step 3: Associate orphaned comments with posts
        $this->associateCommentsWithPosts();

        // Step 4: Import any remaining Facebook comments
        $this->importRemainingFacebookComments();

        // Step 5: Clean up data inconsistencies
        $this->cleanupCommentData();

        // Step 6: Disable Facebook integration
        $this->disableFacebookIntegration();

        // Step 7: Generate migration statistics
        $this->generateStatistics();

        $this->info('Migration completed successfully!');
    }

    private function validatePrerequisites()
    {
        $this->info('Validating prerequisites...');

        // Check custom comment system is ready
        if (!Schema::hasTable('comments')) {
            $this->error('Comments table does not exist. Run comment migrations first.');
            exit(1);
        }

        // Check Facebook API credentials
        if (!config('services.facebook.app_id')) {
            $this->warn('Facebook API credentials not configured. Skipping fresh import.');
        }
    }

    private function createBackup()
    {
        if ($this->option('dry-run')) return;

        $this->info('Creating full database backup...');

        $filename = 'comments_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = storage_path('app/backups/' . $filename);

        if (!File::exists(dirname($backupPath))) {
            File::makeDirectory(dirname($backupPath), 0755, true);
        }

        // Create database backup
        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s comments > %s',
            config('database.connections.mysql.host'),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            config('database.connections.mysql.database'),
            $backupPath
        );

        exec($command);
        $this->info("Backup created: {$filename}");
    }

    private function disableFacebookIntegration()
    {
        if ($this->option('dry-run')) {
            $this->info('Would disable Facebook comment integration');
            return;
        }

        $this->info('Disabling Facebook comment integration...');

        // Update environment variables
        $envFile = base_path('.env');
        $envContent = File::get($envFile);

        $envContent = preg_replace(
            '/ENABLE_FACEBOOK_COMMENTS=true/',
            'ENABLE_FACEBOOK_COMMENTS=false',
            $envContent
        );

        File::put($envFile, $envContent);

        $this->info('Facebook integration disabled');
    }
}
```

### 4.2 Data Validation & Cleanup

```php
// Validate comment hierarchy integrity
$brokenHierarchy = Comment::whereNotNull('parent_id')
    ->whereDoesntHave('parent')
    ->count();

if ($brokenHierarchy > 0) {
    $this->warn("Found {$brokenHierarchy} comments with invalid parent references");

    // Fix broken hierarchy
    Comment::whereNotNull('parent_id')
          ->whereDoesntHave('parent')
          ->update(['parent_id' => null]);
}

// Validate all comments have post associations
$orphaned = Comment::whereNull('post_id')->count();
if ($orphaned > 0) {
    $this->error("Migration incomplete: {$orphaned} comments still not associated with posts");
}
```

## Phase 5: Facebook Integration Removal

### 5.1 Remove Facebook Webhook Handler

Update or remove `FacebookCallbackController` since new comments will use custom system:

```php
// Remove Facebook comment webhook handling entirely
// Or modify to ignore comment events and log them for monitoring

public function index(Request $request)
{
    // ... existing verification logic ...

    // Log that Facebook comments are no longer processed
    Log::info('Facebook comment webhook received but ignored (migrated to custom comments)', [
        'data' => $request->all()
    ]);

    return response('OK', 200);
}
```

### 5.2 Remove Facebook JavaScript SDK

Update blog post templates to remove Facebook comments plugin:

```blade
{{-- Remove this entire section --}}
{{--
<div id="fb-root"></div>
<script async defer crossorigin="anonymous"
        src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0&appId={{ config('services.facebook.app_id') }}">
</script>

<div class="fb-comments"
     data-href="{{ Request::url() }}"
     data-width="100%"
     data-numposts="10">
</div>
--}}
```

### 5.3 Environment Configuration

```env
# Disable Facebook comments completely
ENABLE_FACEBOOK_COMMENTS=false
ENABLE_CUSTOM_COMMENTS=true

# Keep Facebook API credentials for potential data recovery
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
```

## Phase 6: Post-Migration Tasks

### 6.1 SEO and URL Preservation

Ensure migrated comments maintain SEO value:

```php
// Add JSON-LD structured data for comments
public function getCommentStructuredData($post)
{
    $comments = $post->comments()->approved()->get();

    $commentData = $comments->map(function($comment) {
        return [
            '@type' => 'Comment',
            'author' => [
                '@type' => 'Person',
                'name' => $comment->name
            ],
            'dateCreated' => $comment->created_at->toISOString(),
            'text' => $comment->message
        ];
    });

    return [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'comment' => $commentData->toArray()
    ];
}
```

### 6.2 User Notification System

Implement email notifications for new comments:

```php
// Update CommentObserver to handle all comments
class CommentObserver
{
    public function created(Comment $comment)
    {
        // Send notification to post author
        $post = $comment->post;
        if ($post && $post->author) {
            Mail::to($post->author->email)->send(new CommentReceivedMail($comment));
        }

        // Send notification to parent comment author (for replies)
        if ($comment->parent_id && $comment->parent->email) {
            Mail::to($comment->parent->email)->send(new CommentReplyMail($comment));
        }
    }
}
```

### 6.3 Performance Optimization

```php
// Add proper indexes for comment queries
Schema::table('comments', function (Blueprint $table) {
    $table->index(['post_id', 'approved', 'created_at']);
    $table->index(['parent_id', 'created_at']);
    $table->index(['approved', 'created_at']);
});

// Optimize comment loading with eager loading
public function show($slug)
{
    $post = Post::where('slug', $slug)
        ->with(['comments' => function($query) {
            $query->approved()
                  ->whereNull('parent_id')
                  ->with('replies')
                  ->orderBy('created_at');
        }])
        ->firstOrFail();

    return view('posts.show', compact('post'));
}
```

## Risk Mitigation

### Data Loss Prevention

1. **Complete Backup**: Full database backup before migration
2. **Facebook Data Export**: Export all Facebook comments via API before disabling
3. **Rollback Plan**: Keep Facebook API credentials for emergency data recovery

### User Experience

1. **Clear Communication**: Notify users about the new comment system
2. **Improved Features**: Highlight benefits like better threading, notifications, and moderation
3. **Migration Notice**: Temporarily display notice about migrated comments

### Technical Risks

1. **Facebook API Limits**: Complete export before hitting rate limits
2. **Data Integrity**: Thorough validation of parent-child relationships
3. **Performance**: Load test comment display with full migrated dataset
4. **SEO Impact**: Ensure comment content remains crawlable and indexed

## Success Metrics

-   [ ] 100% of Facebook comments migrated and preserved
-   [ ] Correct parent-child relationships maintained
-   [ ] All comments associated with correct posts
-   [ ] No broken comment threads
-   [ ] Email notifications working for new comments
-   [ ] Page load times improved or maintained
-   [ ] Facebook integration completely removed
-   [ ] Custom comment system fully functional

## Timeline

| Phase                   | Duration | Key Activities                                |
| ----------------------- | -------- | --------------------------------------------- |
| **Data Enhancement**    | 1-2 days | Database schema updates, post associations    |
| **Data Migration**      | 1-2 days | Facebook API export, comment conversion       |
| **Template Updates**    | 1 day    | Remove FB integration, update comment display |
| **Migration Execution** | 1 day    | Run migration command, validation             |
| **Integration Removal** | 1 day    | Remove FB webhooks, JavaScript, cleanup       |
| **Post-Migration**      | 1-2 days | SEO optimization, notifications, testing      |

**Total Duration**: 1-2 weeks (no hybrid phase)

## Immediate Action Items

1. **Backup Current Data**: Export existing Facebook comments
2. **Prepare Migration Command**: Complete the artisan command with all phases
3. **Update Templates**: Remove Facebook comments integration from all blog views
4. **Test Custom System**: Ensure custom comment system handles threading and notifications
5. **Plan Communication**: Prepare user-facing notice about the migration
6. **Set Migration Date**: Choose low-traffic time for migration execution
