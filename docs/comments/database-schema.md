# Database Schema Documentation

## Comments Table Structure

### Migration File

`database/migrations/2021_10_31_170514_create_comments_table.php`

### Schema Definition

```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('parent_id')->nullable();
    $table->string('fb_comment_id', 64)->nullable();
    $table->string('fb_comment_parent_id', 64)->nullable();
    $table->string('name', 128);
    $table->string('email', 128)->nullable();
    $table->text('message')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('parent_id')->references('id')->on('comments')->onDelete('set null');
});
```

## Field Specifications

### Primary Fields

| Field       | Type            | Length | Nullable | Description                                      |
| ----------- | --------------- | ------ | -------- | ------------------------------------------------ |
| `id`        | BIGINT UNSIGNED | -      | No       | Primary key, auto-increment                      |
| `parent_id` | BIGINT UNSIGNED | -      | Yes      | Self-referencing foreign key for nested comments |
| `name`      | VARCHAR         | 128    | No       | Commenter's display name                         |
| `email`     | VARCHAR         | 128    | Yes      | Email address (currently stores FB user ID)      |
| `message`   | TEXT            | -      | Yes      | Comment content                                  |

### Facebook Integration Fields

| Field                  | Type    | Length | Nullable | Description                            |
| ---------------------- | ------- | ------ | -------- | -------------------------------------- |
| `fb_comment_id`        | VARCHAR | 64     | Yes      | Facebook comment unique identifier     |
| `fb_comment_parent_id` | VARCHAR | 64     | Yes      | Facebook parent comment ID for replies |

### System Fields

| Field        | Type      | Nullable | Description                 |
| ------------ | --------- | -------- | --------------------------- |
| `created_at` | TIMESTAMP | Yes      | Record creation timestamp   |
| `updated_at` | TIMESTAMP | Yes      | Last modification timestamp |
| `deleted_at` | TIMESTAMP | Yes      | Soft delete timestamp       |

## Relationships & Constraints

### Self-Referencing Relationship

```sql
FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL
```

**Behavior**:

-   When a parent comment is deleted, child comments remain but `parent_id` becomes null
-   Prevents cascading deletions that would remove entire comment threads
-   Maintains comment history even if parent is removed

### Nested Structure Example

```
Comment 1 (parent_id: null)
├── Comment 2 (parent_id: 1)
│   └── Comment 4 (parent_id: 2)
└── Comment 3 (parent_id: 1)
```

## Index Recommendations

### Current Indexes

-   Primary key on `id` (automatic)
-   Foreign key index on `parent_id` (automatic)

### Suggested Additional Indexes

```sql
-- For Facebook integration lookups
CREATE INDEX idx_fb_comment_id ON comments(fb_comment_id);

-- For post association (when implemented)
CREATE INDEX idx_post_comments ON comments(post_id, created_at);

-- For soft delete queries
CREATE INDEX idx_deleted_at ON comments(deleted_at);

-- For parent-child lookups
CREATE INDEX idx_parent_created ON comments(parent_id, created_at);
```

## Missing Fields (For Future Implementation)

### Post Association

```sql
ALTER TABLE comments ADD COLUMN post_id BIGINT UNSIGNED NULL;
ALTER TABLE comments ADD FOREIGN KEY (post_id) REFERENCES canvas_posts(id) ON DELETE CASCADE;
```

### User Association (for registered users)

```sql
ALTER TABLE comments ADD COLUMN user_id BIGINT UNSIGNED NULL;
ALTER TABLE comments ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
```

### Moderation Fields

```sql
ALTER TABLE comments ADD COLUMN status ENUM('pending', 'approved', 'spam', 'rejected') DEFAULT 'pending';
ALTER TABLE comments ADD COLUMN ip_address VARCHAR(45) NULL;
ALTER TABLE comments ADD COLUMN user_agent TEXT NULL;
```

### Rich Content Support

```sql
ALTER TABLE comments ADD COLUMN message_html TEXT NULL;  -- Parsed/sanitized HTML
ALTER TABLE comments ADD COLUMN message_raw TEXT NULL;   -- Original markdown/raw content
```

## Data Types & Constraints

### String Length Justifications

-   `name` (128): Accommodates long display names and international characters
-   `email` (128): Standard email length + buffer for Facebook user IDs
-   `fb_comment_id` (64): Facebook's ID format requirements
-   `message` (TEXT): Unlimited comment length (65,535 bytes in MySQL)

### Nullable Field Rationale

-   `parent_id`: Top-level comments have no parent
-   `email`: Anonymous commenting should be allowed
-   `message`: Allows for placeholder/deleted comment content
-   `fb_*` fields: Not all comments originate from Facebook

## Performance Considerations

### Query Patterns

```sql
-- Get comment thread (parent + replies)
SELECT * FROM comments
WHERE id = ? OR parent_id = ?
ORDER BY created_at ASC;

-- Get top-level comments for a post
SELECT * FROM comments
WHERE post_id = ? AND parent_id IS NULL
ORDER BY created_at DESC;

-- Count replies for a comment
SELECT COUNT(*) FROM comments WHERE parent_id = ?;
```

### Optimization Strategies

1. **Nested Set Model**: Consider for deep comment trees
2. **Materialized Path**: Store full parent path for faster queries
3. **Comment Counts**: Cache reply counts in parent records
4. **Pagination**: Implement cursor-based pagination for large comment sets
