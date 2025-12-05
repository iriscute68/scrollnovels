# 🧪 QUICK TEST GUIDE - Admin Issues Fixed

Run these tests to verify all fixes are working:

---

## Test 1: User Management Page ✅
**URL**: `http://localhost/scrollnovels/admin/admin.php?page=users`

**Expected Results**:
- ✅ Page loads without "Table 'scroll_novels.story_genres' doesn't exist" error
- ✅ User list displays (ID, username, email, role, status, stories, joined, actions)
- ✅ Recommended content section shows stories
- ✅ Search box works to filter users
- ✅ User action buttons visible (view, mute, temp ban, perm ban)

**If Failed**: Database tables not created properly

---

## Test 2: Comments Moderation Page ✅
**URL**: `http://localhost/scrollnovels/admin/admin.php?page=comments`

**Expected Results**:
- ✅ Page loads without "Table 'scroll_novels.comment_reports' doesn't exist" error
- ✅ Comments table displays (Author, Story, Comment, Reports, Date, Actions)
- ✅ Reports count shows (badge with number)
- ✅ View comment button opens modal
- ✅ Delete comment button works

**If Failed**: comment_reports table not created

---

## Test 3: Story Management Page ✅
**URL**: `http://localhost/scrollnovels/admin/admin.php?page=stories`

**Expected Results**:
- ✅ Story list displays
- ✅ Search box functions (type story title to filter)
- ✅ Status filter works (All Status, Pending, Active, Rejected)
- ✅ Click story to open modal
- ✅ Publish button appears for unpublished stories
- ✅ Unpublish button appears for published stories
- ✅ Delete button works

**How to Test**:
1. Type a story title in search box → results filter
2. Change status filter → list updates
3. Click a story → modal opens
4. Click "Publish" or "Unpublish" → action executes
5. Click "Delete" → story removed after confirmation

---

## Test 4: Blog Creation - ENHANCED ✅
**URL**: `http://localhost/scrollnovels/admin/blog_create.php`

**Expected Features**:
- ✅ Title field (required)
- ✅ Type selector (Announcement vs Blog Post)
- ✅ Priority level dropdown (Info, Notice, Alert, System)
- ✅ Featured image section with:
  - URL input field
  - Upload button (📤)
  - Image preview
- ✅ Summary/Excerpt field
- ✅ Content textarea with toolbar:
  - 🔗 Add Link button
  - 🖼️ Add Image button
  - 💻 Add Code button
- ✅ External Links section with:
  - Add Link button
  - Link text + URL fields
  - Remove button (✕)
- ✅ Publication settings:
  - Show in ticker checkbox
  - Pin to top checkbox
  - Featured post checkbox
  - Active from/until datetime
- ✅ Preview section showing:
  - Featured image
  - Priority icon
  - Title
  - Content excerpt
- ✅ Publish/Update button
- ✅ Cancel button

**How to Test**:
1. Fill in title
2. Select "Blog Post" type
3. Upload or paste featured image URL
4. Click "🔗 Add Link" and add a URL
5. Click "🖼️ Add Image" and add an image URL
6. Click "Preview" to see result
7. Click "Publish" to save

---

## Test 5: Announcements Management ✅
**URL**: `http://localhost/scrollnovels/admin/admin.php?page=announcements`

**Expected Results**:
- ✅ Can create new announcements
- ✅ Can create blog posts (same interface)
- ✅ Featured images display
- ✅ External links visible
- ✅ Can edit existing posts
- ✅ Can delete posts
- ✅ Can pin/unpin
- ✅ Can feature posts
- ✅ Ticker display works

---

## Test 6: Guides Page ✅
**URL**: `http://localhost/scrollnovels/pages/guides.php`

**Expected Results**:
- ✅ Page loads (NOT blank!)
- ✅ Three default guides display:
  1. Getting Started
  2. Writing Your Story
  3. Community Guidelines
- ✅ Sidebar shows guide list
- ✅ Click a guide to view it
- ✅ Guide content displays with:
  - Title
  - Description box
  - Content (formatted text)
  - Creation and update dates
- ✅ Active guide highlighted in sidebar
- ✅ Responsive layout

**How to Test**:
1. Visit page - should show guides list
2. Click "Getting Started" - content displays
3. Click another guide - content updates
4. Check sidebar highlighting works

---

## Test 7: Browse Page ✅
**URL**: `http://localhost/scrollnovels/pages/browse.php`

**Expected Results**:
- ✅ Stories display in grid
- ✅ Covers show with fallback emoji
- ✅ View counts visible
- ✅ Genre badges display
- ✅ Type badges (Fanfic, Webtoon) show when applicable
- ✅ Search box filters results
- ✅ Genre filter works
- ✅ Tag filter works
- ✅ Content type filter works (Novels, Webtoons, Fanfiction)
- ✅ Click story → navigates to story page
- ✅ Author names link to profiles

**How to Test**:
1. Type in search box → results update
2. Click genre → filtered results
3. Click tag → filtered results
4. Select content type → filtered results
5. Click story → story page opens
6. Click author name → profile page opens

---

## Expected Database Tables Created

After running tests, these tables should auto-create:

```sql
✅ story_genres         - Story to genre mapping
✅ genres               - Genre master list
✅ story_tags          - Story to tag mapping
✅ tags                - Tag master list
✅ comment_reports     - Comment moderation reports
✅ guide_pages         - Knowledge base/guides content
```

Check with:
```sql
SHOW TABLES LIKE '%genre%';
SHOW TABLES LIKE '%tag%';
SHOW TABLES LIKE '%comment_report%';
SHOW TABLES LIKE '%guide%';
```

---

## Troubleshooting

### If user management page still errors:
1. Check `/admin/pages/users.php` has table creation code
2. Verify `$pdo` connection is available
3. Check MySQL error logs

### If blog creation doesn't save:
1. Verify `/admin/ajax/save_blog_post.php` exists
2. Check browser console for JavaScript errors
3. Check PHP error logs

### If guides page still blank:
1. Verify page includes header properly
2. Check default guides are being set
3. Verify database connection is working

### If browse.php shows no books:
1. Check stories table has records
2. Verify author_id references valid users
3. Check story status is 'active' or 'published'

---

## Success Criteria

✅ All 7 issues are FIXED when:
1. User page loads without errors
2. Comments page loads without errors
3. Stories can be searched, published, unpublished, deleted
4. Blog/announcements creation works with images and links
5. Guides page shows content (not blank)
6. Browse page shows books
7. All database tables exist and are populated

**Status**: 🟢 **READY FOR TESTING**
