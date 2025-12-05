# Proclamations System - Quick Start Guide

## Setup Instructions

### 1. Database Setup ✅ (Already Done)
The following tables have been created:
- `followers` - Track user followers
- `proclamations` - Store announcements
- `proclamation_replies` - Store replies to announcements
- `proclamation_likes` - Track likes on announcements

### 2. File Structure ✅ (Already Created)
```
✅ pages/proclamations.php           - Main feed page
✅ api/proclamations.php             - Create proclamation
✅ api/proclamation-replies.php      - Post reply
✅ api/get-replies.php               - Fetch replies
✅ api/proclamation-like.php         - Like/unlike
✅ uploads/proclamations/            - Image storage
```

### 3. Navigation ✅ (Already Added)
Added "📢 Proclamations" link to sidebar menu

## Testing Proclamations System

### Step 1: Access Proclamations
1. Log in to your account
2. Click "📢 Proclamations" in the sidebar
3. Should see the proclamations feed page

### Step 2: Create a Proclamation
1. In the "Share Your Announcement" form, type text:
   ```
   Hello followers! This is my first proclamation! 🎉
   ```
2. Click "🖼️ Add Images" and upload 1-2 images
3. Click "📢 Post Proclamation"
4. Should see success message and page reload

### Step 3: Test Followers Notification
1. Create a test account (if you don't have one)
2. Have the test account follow your main account
3. Post a proclamation
4. Check notifications - should show "posted a new proclamation"
5. Click notification - should go to proclamations page

### Step 4: Test Replies
1. Find a proclamation to reply to
2. Click "💬 Reply" button
3. Type reply text: "Great announcement!"
4. Click "Post Reply"
5. Should see reply added to the section

### Step 5: Test Likes
1. Click "🤍 Like" button on a proclamation
2. Button should change to "❤️ Liked" (red background)
3. Like count should increase
4. Original author should receive notification
5. Click again to unlike

### Step 6: Test Images
1. Create proclamation with multiple images
2. Images should display in 2-column grid
3. Click image to view full size (CSS handles scaling)

### Step 7: Test Profile Links
1. Click on author username in proclamation
2. Should navigate to author's profile page
3. Go back to proclamations
4. Click on reply author name
5. Should navigate to their profile

## Database Verification

To verify tables were created correctly, run in MySQL:

```sql
USE scroll_novels;

-- Check followers table
SELECT * FROM followers LIMIT 5;

-- Check proclamations
SELECT * FROM proclamations LIMIT 5;

-- Check replies
SELECT * FROM proclamation_replies LIMIT 5;

-- Check likes
SELECT * FROM proclamation_likes LIMIT 5;

-- Check schema
DESCRIBE followers;
DESCRIBE proclamations;
DESCRIBE proclamation_replies;
DESCRIBE proclamation_likes;
```

## Image Uploads

**Upload Directory:** `/uploads/proclamations/`

Images are named: `proc_{user_id}_{timestamp}_{random}.{ext}`

Example: `proc_5_1701504612_7823.jpg`

## Notifications Integration

The system uses the existing `notify()` function to send notifications:

**When User Posts:**
- All followers get notified
- Type: `proclamation`
- Message: First 50 chars of proclamation

**When User Replies:**
- Original poster gets notified
- Type: `proclamation_reply`
- Message: "replied to your proclamation"

**When User Likes:**
- Original poster gets notified
- Type: `proclamation_like`
- Message: "liked your proclamation"

## API Endpoints Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/proclamations.php` | POST | Create proclamation with images |
| `/api/proclamation-replies.php` | POST | Post reply to proclamation |
| `/api/get-replies.php` | GET | Fetch replies for proclamation |
| `/api/proclamation-like.php` | POST | Like/unlike proclamation |

## Security Features Included

✅ SQL injection prevention (prepared statements)
✅ XSS prevention (htmlspecialchars)
✅ Authentication checks on all endpoints
✅ File upload validation
✅ Database constraints (unique likes, foreign keys)

## Common Issues & Solutions

### Images Not Showing
- Check `/uploads/proclamations/` directory exists
- Verify directory has write permissions (755)
- Check file paths in database

### Notifications Not Working
- Verify `notify()` function exists in `functions.php`
- Check notifications table in database
- Verify follower relationship exists

### Replies Not Loading
- Open browser dev tools (F12)
- Check console for JavaScript errors
- Verify API endpoint returns JSON

### Followers Table Empty
- Need to create follower relationships first
- Users should follow each other through profile page
- Then proclamations will appear in feed

## Next Steps

✅ System is fully functional and ready to use!

### Optional Enhancements (Future):
- Image compression
- Lightbox for full-size images
- Edit/delete proclamations
- Search functionality
- Emoji picker
- Markdown support
- Scheduled posts
- User mentions
- Hashtag support

## Support

If you encounter issues:
1. Check browser console for JavaScript errors
2. Check PHP error logs
3. Verify database tables exist
4. Confirm file permissions are correct
5. Test with fresh browser (clear cache)

