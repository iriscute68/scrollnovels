# ScrollNovels Admin Panel - Complete Documentation

## Overview

The admin panel is a comprehensive management system built from the `admin.txt` code templates. It provides complete control over users, stories, chapters, comments, tags, monetization, and more.

## Architecture

### File Structure

```
/admin/
├── dashboard.php                 # Main router page
├── css/
│   └── admin.css                # Unified dark theme stylesheet
├── js/
│   ├── admin.js                 # Shared utilities and helpers
│   ├── users.js                 # User management module
│   ├── stories.js               # Story management module
│   ├── chapters.js              # Chapter management module
│   ├── comments.js              # Comment moderation module
│   ├── tags.js                  # Tags and genres module
│   ├── reports.js               # Reports management module
│   └── monetization.js          # Monetization module
├── tabs/
│   ├── overview.php             # KPI dashboard
│   ├── users.php                # User management
│   ├── stories.php              # Story moderation
│   ├── chapters.php             # Chapter management
│   ├── comments.php             # Comment moderation
│   ├── tags.php                 # Tags and genres
│   ├── monetization.php         # Payments and earnings
│   ├── reports.php              # User/content reports
│   ├── analytics.php            # Analytics dashboard
│   ├── announcements.php        # System announcements
│   ├── coins.php                # Coins system
│   ├── achievements.php         # Achievements management
│   ├── staff.php                # Admin staff management
│   ├── settings.php             # Platform settings
│   ├── developer.php            # Developer tools
│   └── support.php              # Support tickets
├── ajax/
│   ├── get_kpis.php             # KPI statistics
│   ├── get_system_status.php    # Server health metrics
│   ├── get_users.php            # User listing
│   ├── get_stories.php          # Story listing
│   ├── get_chapters.php         # Chapter listing
│   ├── get_comments.php         # Comment listing
│   ├── get_tags.php             # Tag listing
│   ├── get_reports.php          # Report listing
│   ├── get_transactions.php     # Transaction listing
│   ├── ban_user.php             # User moderation actions
│   ├── delete_tag.php           # Tag deletion
│   ├── approve_story.php        # Story approval
│   ├── delete_story.php         # Story deletion
│   └── ... (additional endpoints)
└── SCHEMA_MIGRATION.sql         # Database schema migrations

```

## Features Implemented

### 1. Dashboard (Overview Tab)
- **KPI Cards**: Total users, authors, stories, chapters, revenue, pending withdrawals
- **Recent Activity**: Real-time feed of platform activities
- **Server Health**: CPU, RAM, and disk usage monitoring
- **Recent Payments**: Transaction history display

### 2. User Management
- **User Listing**: Searchable table with pagination
- **Status Management**: Active, suspended, banned users
- **User Actions**: View profile, ban, suspend, send warnings
- **Export**: CSV export of user data

### 3. Story Management
- **Story Listing**: Browse all stories with metadata
- **Status Tracking**: Pending, published, rejected, draft stories
- **Moderation**: Approve/reject/feature stories
- **Audit Trail**: Automatic logging of changes
- **Cascading Deletes**: Removes chapters when story deleted

### 4. Chapter Management
- **Chapter Listing**: All chapters with word counts and views
- **Drag & Drop Reordering**: Reorder chapters within stories
- **Paywall System**: Toggle paid/free chapters with pricing
- **Edit/Delete**: Modify chapter metadata
- **Merge Chapters**: Combine two chapters into one

### 5. Comment Moderation
- **Comment Browsing**: View all comments with context
- **Report Tracking**: See which comments have been reported
- **Moderation Actions**: Hide/show/delete comments
- **Blacklist Management**: Add words to content filter
- **Pin Reviews**: Feature best reader reviews

### 6. Tags & Genres
- **Tag Management**: Create, edit, delete tags
- **Tag Merging**: Combine duplicate tags with bulk operations
- **Genre Management**: Add/edit/delete story genres
- **Usage Statistics**: Track tag popularity

### 7. Monetization & Payments
- **Transaction Listing**: View all donations and payments
- **Revenue Tracking**: Monthly revenue breakdown
- **Pending Withdrawals**: Manage author withdrawal requests
- **Top Donors**: See most generous supporters
- **Reversal**: Ability to reverse transactions if needed

### 8. Reports & Moderation
- **Report Browsing**: View user reports and content violations
- **Status Tracking**: Open, resolved, dismissed reports
- **Action History**: Track moderation decisions

### 9. Analytics
- **Platform Statistics**: User growth, story trends
- **Activity Heatmaps**: Usage patterns by time
- **Revenue Analytics**: Monetization trends

### 10. Additional Sections
- **Announcements**: Post system-wide announcements
- **Coins System**: Manage platform currency
- **Achievements**: Create badges and achievement tiers
- **Staff Management**: Admin role assignments
- **Settings**: Configure platform-wide settings
- **Developer Tools**: Debug utilities and cache management
- **Support Tickets**: Support ticket management system

## Database Schema

Required tables created by `SCHEMA_MIGRATION.sql`:
- `admin_activity_logs` - Admin action audit trail
- `moderation_logs` - Moderation decision history
- `user_warnings` - User warning history
- `story_change_logs` - Story edit audit trail
- `chapter_logs` - Chapter edit history
- `chapter_monetization` - Paywall settings
- `story_tags` - Story-tag relationships
- `tags` - Tag registry
- `genres` - Genre registry
- `comment_reports` - Comment report tracking
- `blacklist_words` - Content filter words
- `pinned_reviews` - Featured reviews
- `featured_stories` - Featured carousel
- `announcements` - System announcements
- `admin_users` - Admin user accounts
- `support_tickets` - Support ticket system

## Security Features

1. **Authentication**: `isApprovedAdmin()` function checks user role
2. **SQL Injection Prevention**: Prepared statements with parameter binding
3. **CSRF Protection**: JSON-based AJAX requests
4. **Rate Limiting**: Can be added to AJAX endpoints
5. **Audit Logging**: All admin actions logged with timestamps
6. **Role-Based Access Control**: Different permission levels planned

## API Endpoints

All endpoints require admin authentication and return JSON.

### User Endpoints
- `GET /admin/ajax/get_users.php` - List users with pagination
- `POST /admin/ajax/ban_user.php` - Ban/suspend/unban users
- `POST /admin/ajax/send_warning.php` - Issue user warnings
- `POST /admin/ajax/impersonate_user.php` - Login as user

### Story Endpoints
- `GET /admin/ajax/get_stories.php` - List stories
- `POST /admin/ajax/approve_story.php` - Approve story
- `POST /admin/ajax/delete_story.php` - Delete story with cascade
- `POST /admin/ajax/feature_story.php` - Feature/unfeature story

### Chapter Endpoints
- `GET /admin/ajax/get_chapters.php` - List chapters
- `POST /admin/ajax/update_chapter.php` - Edit chapter
- `POST /admin/ajax/reorder_chapters.php` - Reorder chapters
- `POST /admin/ajax/merge_chapters.php` - Merge two chapters
- `POST /admin/ajax/toggle_paywall.php` - Set chapter pricing

### Comment Endpoints
- `GET /admin/ajax/get_comments.php` - List comments
- `POST /admin/ajax/moderate_comment.php` - Hide/show comment
- `POST /admin/ajax/blacklist_word.php` - Add blacklist word
- `POST /admin/ajax/pin_review.php` - Pin/unpin review

### Tag Endpoints
- `GET /admin/ajax/get_tags.php` - List tags
- `POST /admin/ajax/create_or_update_tag.php` - Create/edit tag
- `POST /admin/ajax/delete_tag.php` - Delete tag
- `POST /admin/ajax/merge_tags.php` - Merge tags

### Dashboard Endpoints
- `GET /admin/ajax/get_kpis.php` - KPI statistics
- `GET /admin/ajax/get_system_status.php` - Server health metrics

## Styling

### Color Scheme
- **Primary**: #6366f1 (Indigo)
- **Accent**: #8b5cf6 (Purple)
- **Success**: #22c55e (Green)
- **Danger**: #ef4444 (Red)
- **Warning**: #f59e0b (Amber)
- **Background**: #0f0f12 (Very dark)
- **Card**: #141418 (Dark)
- **Text**: #e6e7ea (Light gray)
- **Muted**: #9aa0a6 (Gray)

### Responsive Design
- Desktop: Sidebar + main content
- Tablet: Collapsed sidebar (72px)
- Mobile: Hidden sidebar with toggle

## Usage

### Accessing the Admin Panel

1. Navigate to `http://localhost/admin/dashboard.php`
2. Ensure your user account has admin role in the `roles` JSON column
3. The left sidebar shows all available sections
4. Click tabs to navigate between features

### Common Tasks

#### Ban a User
1. Go to Users tab
2. Search for the user
3. Click "Ban" button
4. Confirm in dialog
5. User status changes to "banned"

#### Approve a Story
1. Go to Content tab
2. Find pending story
3. Click "View" to see details
4. Click "Approve" button
5. Story status changes to "published"

#### Delete a Story
1. Go to Content tab
2. Find story to delete
3. Click "Delete" button
4. Confirm deletion
5. Story and all chapters automatically deleted

#### Manage Tags
1. Go to Tags & Genres tab
2. Search for tag
3. Edit, merge, or delete as needed
4. Changes reflected immediately

#### Add Blacklist Word
1. Go to Comments tab
2. Click "Add Blacklist Word"
3. Enter word or phrase
4. Word will be flagged in future comments

## Performance Considerations

1. **Pagination**: All listings use pagination (20-25 items per page)
2. **Indexes**: Database queries use indexed columns
3. **Lazy Loading**: Data loaded via AJAX only when needed
4. **Caching**: Can be added to expensive endpoints
5. **Aggregation**: KPIs use database aggregation functions

## Future Enhancements

- [ ] Real-time notifications for reports/flags
- [ ] Advanced filtering and search
- [ ] Batch operations for multiple items
- [ ] Content moderation automation
- [ ] Machine learning for report prioritization
- [ ] Advanced analytics and reporting
- [ ] Email notifications for admins
- [ ] Two-factor authentication for admins
- [ ] API key management
- [ ] Custom dashboard widgets

## Migration & Deployment

1. Run `SCHEMA_MIGRATION.sql` to create all necessary tables
2. Ensure your user account has 'admin' role in `users.roles` JSON column
3. Test access to `/admin/dashboard.php?tab=overview`
4. Verify all AJAX endpoints respond correctly
5. Set up any additional permissions or role hierarchies

## Support & Troubleshooting

### "Forbidden" Error
- Check `users.roles` JSON column contains 'admin'
- Verify `isApprovedAdmin()` function works correctly
- Check session is properly initialized

### Missing Data in Tables
- Verify tables exist by running `SCHEMA_MIGRATION.sql`
- Check database credentials in config.php
- Look for SQL errors in browser console

### AJAX Endpoints Failing
- Check endpoint file exists in `/admin/ajax/`
- Verify database connection is working
- Check error logs for detailed error messages
- Use browser DevTools Network tab to inspect requests

