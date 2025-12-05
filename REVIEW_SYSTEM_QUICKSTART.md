# ⚡ Quick Start — Review System Setup

## 30-Second Setup

### Step 1: Create Database Tables
Open phpMyAdmin or terminal and run:

```sql
-- Open phpMyAdmin → scroll_novels database → SQL tab → Paste this:

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  story_id INT NOT NULL,
  user_id INT NOT NULL,
  rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
  review_text TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_story (story_id, user_id),
  FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_story (story_id),
  INDEX idx_user (user_id),
  INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reason VARCHAR(255),
  status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Verify Installation
All these files should exist:
```
✅ /api/submit-review.php
✅ /api/delete-review.php
✅ /api/get-review.php
✅ /api/report-review.php
✅ /includes/review-component.php
✅ /includes/navbar.php (modified)
✅ /pages/read.php (modified)
```

### Step 3: Test It!
1. Go to any story → `/pages/read.php?id=1`
2. You should see **📚 Guides** link in navbar ✅
3. See the review form with gold stars ✅
4. Log in and submit a review ✅

## That's it! 🎉

---

## What You Get

✨ **Professional Review System:**
- Gold gradient stars (5-star rating)
- 1 review per user per story (enforced by database)
- Update/Delete your review anytime
- Report inappropriate reviews
- Dark mode support
- Mobile responsive
- Zero emojis — pure SVG stars

---

## Features at a Glance

| Feature | Status |
|---------|--------|
| 5-Star Rating | ✅ Gold gradient SVG |
| One Review Per User | ✅ Enforced by UNIQUE constraint |
| Update Review | ✅ Button shows if review exists |
| Delete Review | ✅ With confirmation |
| Report Review | ✅ Moderation system ready |
| Dark Mode | ✅ Full support |
| Mobile | ✅ Fully responsive |
| Security | ✅ Authorization checks |

---

## Troubleshooting

**Issue:** Stars not showing gold color
- **Fix:** Check browser cache, do hard refresh (Ctrl+Shift+R)

**Issue:** "Not authenticated" error
- **Fix:** Make sure you're logged in

**Issue:** "One Review Per User" not working
- **Fix:** Check that database tables were created successfully

**Issue:** Guides link not showing in navbar
- **Fix:** Clear browser cache and hard refresh

---

## File Locations

| File | Purpose |
|------|---------|
| `api/submit-review.php` | Create/update reviews |
| `api/delete-review.php` | Delete reviews |
| `api/get-review.php` | Fetch user's review |
| `api/report-review.php` | Report inappropriate reviews |
| `includes/review-component.php` | Review UI component |
| `includes/navbar.php` | Navigation bar (Guides link) |
| `pages/read.php` | Story reading page |

---

## Need Help?

Check `REVIEW_SYSTEM_IMPLEMENTATION.md` for:
- Full API documentation
- Data model details
- Security features
- Testing scenarios
- Future enhancements
