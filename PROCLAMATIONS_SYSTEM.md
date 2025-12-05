## Proclamations System Documentation

The Proclamations system is a community announcement platform where users can share announcements with their followers, reply to proclamations, and interact through likes.

### Features

✅ **Create Proclamations**
- Users can post announcements with text content
- Support for multiple image uploads
- Rich formatting with proper escaping

✅ **Follower Notifications**
- When a user posts, all followers receive a notification
- Notification type: `proclamation`
- Users can navigate to proclamations feed from notification

✅ **Replies System**
- Users can reply to proclamations
- Inline reply form for each post
- Load all replies with user information
- Proclamation author is notified of replies

✅ **Like/Unlike**
- Like functionality for proclamations
- Like counter
- Visual feedback (heart icon state)
- Proclamation author notified of likes
- Notification type: `proclamation_like`

✅ **Image Support**
- Multiple image uploads per post
- Images stored in `/uploads/proclamations/`
- Automatic thumbnail generation with proper cleanup
- Images displayed in grid layout

### Database Schema

#### Followers Table
```sql
CREATE TABLE followers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id),
    FOREIGN KEY (following_id) REFERENCES users(id)
);
```

#### Proclamations Table
```sql
CREATE TABLE proclamations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    images JSON,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Proclamation Replies Table
```sql
CREATE TABLE proclamation_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proclamation_id INT NOT NULL,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    images JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proclamation_id) REFERENCES proclamations(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Proclamation Likes Table
```sql
CREATE TABLE proclamation_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proclamation_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (proclamation_id, user_id),
    FOREIGN KEY (proclamation_id) REFERENCES proclamations(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### File Structure

```
pages/
├── proclamations.php                 # Main proclamations feed page

api/
├── proclamations.php                 # Create proclamation with images
├── proclamation-replies.php          # Create reply to proclamation
├── get-replies.php                   # Fetch replies for proclamation
└── proclamation-like.php             # Like/unlike proclamation

database/
└── proclamations-schema.sql          # Database schema setup

uploads/
└── proclamations/                    # Image storage directory
```

### API Endpoints

#### POST `/api/proclamations.php`
**Create a new proclamation**

Request:
```
FormData:
- content (required): Text content of proclamation
- images[] (optional): Multiple image files
```

Response:
```json
{
  "success": true,
  "proclamation_id": 123
}
```

Notifications sent to all followers

---

#### POST `/api/proclamation-replies.php`
**Reply to a proclamation**

Request:
```json
{
  "proclamation_id": 123,
  "content": "Reply text"
}
```

Response:
```json
{
  "success": true
}
```

Notifications sent to proclamation author

---

#### GET `/api/get-replies.php`
**Fetch all replies for a proclamation**

Query Parameters:
- `proclamation_id` (required): ID of proclamation

Response:
```json
{
  "success": true,
  "replies": [
    {
      "id": 1,
      "proclamation_id": 123,
      "user_id": 5,
      "username": "john_doe",
      "content": "Great announcement!",
      "created_at": "2024-12-02 10:30:00"
    }
  ]
}
```

---

#### POST `/api/proclamation-like.php`
**Like or unlike a proclamation**

Request:
```json
{
  "proclamation_id": 123
}
```

Response:
```json
{
  "success": true
}
```

- If already liked: removes like
- If not liked: adds like and sends notification to author

### UI/UX Elements

**Create Form**
- Textarea for content
- Image upload with preview (drag & drop support)
- Submit button with loading state
- Clear after successful post

**Proclamation Card**
- Author profile picture and name (clickable to profile)
- Post timestamp
- Content with proper text wrapping
- Image gallery (2-column grid)
- Like/reply stats
- Like button (toggles color when liked)
- Reply button (opens inline form)
- Reply viewer section

**Reply Form**
- Textarea for reply content
- Post/Cancel buttons
- Hidden by default, toggle with button

**Reply Display**
- User profile icon
- Username
- Timestamp
- Reply content
- Nested under proclamation

### Notification Integration

The system integrates with the existing notification system:

**Notification Types:**
- `proclamation`: User posted a new proclamation
  - Message: "posted a new proclamation: [first 50 chars]"
  - URL: `/pages/proclamations.php`

- `proclamation_reply`: Someone replied to user's proclamation
  - Message: "replied to your proclamation"
  - URL: `/pages/proclamations.php`

- `proclamation_like`: Someone liked user's proclamation
  - Message: "liked your proclamation"
  - URL: `/pages/proclamations.php`

**Note:** The `notify()` function is called from `/includes/functions.php`

### Image Handling

**Upload Process:**
1. File size validation (handled by PHP)
2. Files stored in `/uploads/proclamations/`
3. Filename: `proc_{user_id}_{timestamp}_{random}.{ext}`
4. Stored as JSON array in database

**Image Display:**
- Direct path to uploaded file
- Fallback image handling
- Responsive grid layout
- Click to view full size (future enhancement)

**Cleanup:**
- Images deleted when proclamation is deleted (database cascade)
- Manual cleanup via admin tools (future)

### JavaScript Functions

**toggleReplyForm(procId)**
- Shows/hides reply form for proclamation

**submitReply(procId)**
- Posts reply to proclamation
- Reloads replies after success
- Closes form

**loadReplies(procId)**
- Fetches all replies for proclamation
- Displays in card format

**toggleLike(procId)**
- Sends like/unlike request
- Reloads page on success

### Security Features

✅ **Input Sanitization**
- `htmlspecialchars()` for all user output
- SQL prepared statements for all queries
- File upload validation

✅ **Authentication**
- All endpoints check for logged-in user
- User ID from session

✅ **Authorization**
- Users can only like/reply with their own account
- Database constraints prevent duplicate likes

### Usage Instructions

**For Users:**
1. Click "📢 Proclamations" in sidebar menu
2. Write announcement in textarea
3. Click "🖼️ Add Images" to upload images
4. Click "📢 Post Proclamation"
5. See announcements from followed users
6. Click "💬 Reply" to respond to announcements
7. Click "🤍 Like" to like announcement

**To Set Up Database:**
```bash
mysql -u root < database/proclamations-schema.sql
```

Or run in MySQL CLI:
```sql
USE scroll_novels;
SOURCE database/proclamations-schema.sql;
```

### Future Enhancements

- Image compression before upload
- Lightbox/modal for full-size images
- Edit proclamation feature
- Delete proclamation feature
- Search proclamations
- Tag users in proclamations
- Scheduled proclamations
- Proclamation templates
- Emoji picker
- Link preview generation
- Markdown support

### Troubleshooting

**Issue:** Images not uploading
- Check `/uploads/proclamations/` directory exists
- Verify directory permissions (755)
- Check PHP upload limits in php.ini

**Issue:** Notifications not appearing
- Verify `notify()` function exists in functions.php
- Check notifications table exists in database
- Verify follower relationship created

**Issue:** Replies not loading
- Check JavaScript console for errors
- Verify API endpoint is accessible
- Check database has proclamation_replies table

### Testing Checklist

- [ ] Create proclamation without images
- [ ] Create proclamation with multiple images
- [ ] Verify followers receive notification
- [ ] Reply to proclamation
- [ ] Like proclamation
- [ ] Unlike proclamation
- [ ] Load replies
- [ ] Click author name (goes to profile)
- [ ] Dark mode styling works
- [ ] Mobile responsive
- [ ] Error handling for large files
- [ ] Check database constraints

