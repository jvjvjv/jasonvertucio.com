# Comment System Documentation

This documentation covers the existing and planned comment system for the Jason Vertucio blog.

## Overview

The site currently uses Facebook Comments for user interaction but has a custom Comment model and database structure that captures Facebook comments via webhooks. The goal is to migrate to a fully custom nested comment system while preserving existing Facebook comment data.

## Documentation Structure

- [Current Architecture](./current-architecture.md) - Existing comment system components
- [Facebook Integration](./facebook-integration.md) - How Facebook comments are captured and stored
- [Database Schema](./database-schema.md) - Comment table structure and relationships
- [Implementation Roadmap](./implementation-roadmap.md) - Steps to build full comment system
- [Migration Strategy](./migration-strategy.md) - How to migrate from Facebook to custom system

## Quick Reference

### Key Files
- `app/Models/Comment.php` - Comment model
- `app/Observers/CommentObserver.php` - Email notifications
- `app/Http/Controllers/FacebookCallbackController.php` - Facebook webhook handler
- `database/migrations/2021_10_31_170514_create_comments_table.php` - Database schema
- `resources/views/blog/single.blade.php` - Blog post template with Facebook comments

### Current Status
- ✅ Database schema with nesting support
- ✅ Facebook webhook integration
- ✅ Email notifications
- ❌ Web interface for comments
- ❌ Comment display in templates
- ❌ Comment CRUD operations
- ❌ Moderation features