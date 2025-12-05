# Supporter System - Integration Test Guide

## 🧪 Testing Checklist

Use this guide to verify all supporter system features work correctly.

---

## Phase 1: Database Initialization ✅

### Test 1.1: Check Database Tables
```sql
-- Run these queries to verify tables exist:
SHOW TABLES LIKE 'supporters';
SHOW TABLES LIKE 'author_links';
SHOW TABLES LIKE 'patreon_webhooks';
```

**Expected:** All 3 tables should appear (top_supporters_cache is optional)

---

## Phase 2: Support Settings Page 🎯

### Test 2.1: Access Support Settings
1. Log in as any user
2. Click account dropdown → "💝 Support Links"
3. Should navigate to `/pages/support-settings.php`

**Expected:** 
- ✅ Page loads without errors
- ✅ Three sections visible (Ko-fi, Patreon, PayPal)
- ✅ Preview section at bottom
- ✅ Info boxes on page

### Test 2.2: Add Ko-fi Link
1. Enter valid Ko-fi URL: `https://ko-fi.com/testauthor`
2. Click "💾 Save Support Links"
3. Wait for success message

**Expected:**
- ✅ Success message appears
- ✅ Ko-fi link saved to database
- ✅ Preview updates with Ko-fi button

**Database Check:**
```sql
SELECT * FROM author_links 
WHERE author_id = YOUR_USER_ID 
AND link_type = 'kofi';
```

### Test 2.3: Add Patreon Link
1. Enter valid Patreon URL: `https://www.patreon.com/testauthor`
2. Click "💾 Save Support Links"

**Expected:**
- ✅ Success message appears
- ✅ Patreon link saved
- ✅ Preview shows both Ko-fi and Patreon buttons

### Test 2.4: Live Preview
1. Edit Ko-fi URL field
2. Observe preview section update in real-time

**Expected:**
- ✅ Preview updates without clicking save
- ✅ Preview shows/hides buttons correctly

### Test 2.5: Error Handling
1. Enter invalid URL: `not a url`
2. Click "💾 Save Support Links"

**Expected:**
- ✅ Error message displayed
- ✅ Data not saved to database

---

## Phase 3: Book Page Integration 📖

### Test 3.1: Support Modal Display
1. Create a story as test user
2. Go to another user's story page
3. Click "💝 Support" button

**Expected:**
- ✅ Modal opens with "Support This Author" title
- ✅ Author name displays in modal
- ✅ Ko-fi and Patreon buttons visible if links exist

### Test 3.2: Support Links Work
1. Click Ko-fi button in support modal
2. Should open new tab with Ko-fi link

**Expected:**
- ✅ New browser tab opens
- ✅ URL is correct Ko-fi link

### Test 3.3: Supporters Tab
1. Go to book page
2. Click "Supporters" tab

**Expected:**
- ✅ Tab loads without errors
- ✅ Shows message if no supporters yet
- ✅ Tab styling updates (active color)

### Test 3.4: Top Supporters Display
1. Create supporter record in database:
```sql
INSERT INTO supporters 
(supporter_id, author_id, tip_amount, patreon_tier, status)
VALUES (5, YOUR_AUTHOR_ID, 50.00, 'Gold', 'active');
```

2. Refresh book page
3. Click "Supporters" tab

**Expected:**
- ✅ Supporter appears in list
- ✅ Shows ranking #1
- ✅ Displays amount: 💰 $50.00
- ✅ Shows patreon tier if present
- ✅ Shows status (✅ Active)

---

## Phase 4: API Endpoints 🔌

### Test 4.1: Get Author Links API
```bash
# In browser console or curl:
fetch('/api/supporters/get-author-links.php?author_id=1')
  .then(r => r.json())
  .then(d => console.log(d))
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "kofi": "https://ko-fi.com/...",
    "patreon": "https://www.patreon.com/...",
    "paypal": null
  }
}
```

### Test 4.2: Get Top Supporters API
```bash
fetch('/api/supporters/get-top-supporters.php?author_id=1&limit=10')
  .then(r => r.json())
  .then(d => console.log(d))
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {
      "supporter_id": 5,
      "username": "supporter_name",
      "profile_image": "/path/to/image.jpg",
      "tip_amount": "50.00",
      "patreon_tier": "Gold",
      "status": "active",
      "created_at": "2024-01-15 10:30:00"
    }
  ]
}
```

### Test 4.3: Add Support Link API
```javascript
fetch('/api/supporters/add-support-link.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    link_type: 'kofi',
    link_url: 'https://ko-fi.com/test'
  })
})
.then(r => r.json())
.then(d => console.log(d))
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Support link saved successfully"
}
```

---

## Phase 5: Webhook Testing 🪝

### Test 5.1: Patreon Webhook Structure
1. Save webhook example to test file
2. Send POST request with valid signature

**Test Payload:**
```json
{
  "data": {
    "id": "webhook-123",
    "type": "pledges:create",
    "relationships": {
      "patron": {
        "data": {
          "id": "patron-456"
        }
      }
    }
  }
}
```

**Expected:**
- ✅ Returns 200 OK
- ✅ Event stored in patreon_webhooks table
- ✅ Processed flag set to 1

### Test 5.2: Ko-fi Webhook Structure
1. Send POST with verification token

**Test Payload:**
```
verification_token=YOUR_TOKEN&
data={"type":"Donation","from_name":"donor@example.com","amount":"10.00","message":"For: @authorname"}
```

**Expected:**
- ✅ Returns 200 OK
- ✅ Supporter record created/updated
- ✅ Tip amount recorded correctly

---

## Phase 6: Navigation 🗺️

### Test 6.1: Menu Access
1. Log in as any user
2. Click account dropdown
3. Look for "💝 Support Links" item

**Expected:**
- ✅ Menu item appears between Settings and Blocked Users
- ✅ Clicking navigates to support-settings.php

### Test 6.2: Profile Settings Tab
1. Go to `/pages/profile-settings.php`
2. Look for tabs at top

**Expected:**
- ✅ Two tabs visible: "👤 Profile" and "💝 Support Links"
- ✅ Profile tab active (green)
- ✅ Support Links tab can be clicked
- ✅ Clicking navigates to support-settings.php

---

## Phase 7: Data Integrity 🛡️

### Test 7.1: Unique Constraint
1. Try to add same support link twice:
```sql
INSERT INTO author_links 
(author_id, link_type, link_url, is_verified)
VALUES (1, 'kofi', 'https://ko-fi.com/test', 1);

INSERT INTO author_links 
(author_id, link_type, link_url, is_verified)
VALUES (1, 'kofi', 'https://ko-fi.com/test2', 1);
-- Should update, not insert duplicate
```

**Expected:**
- ✅ Second insert updates existing record
- ✅ No duplicate entries created

### Test 7.2: Foreign Keys
1. Delete user from users table
2. Check supporters and author_links tables

**Expected:**
- ✅ Cascade delete removes related records
- ✅ No orphaned records remain

### Test 7.3: URL Validation
1. In API, send invalid URL: `htp://notaurl`
2. Send empty string

**Expected:**
- ✅ API returns error for invalid URLs
- ✅ Data not saved to database

---

## Phase 8: User Experience 🎨

### Test 8.1: Empty State Messages
1. Visit book with no supporters
2. Click Supporters tab

**Expected:**
- ✅ Shows "Be the first supporter!" message
- ✅ No console errors

### Test 8.2: Empty Support Links
1. Visit book by author with no links
2. Click Support button

**Expected:**
- ✅ Modal opens
- ✅ Shows "This author hasn't set up support links yet"
- ✅ No broken buttons

### Test 8.3: Success Feedback
1. Add support link
2. Observe success message

**Expected:**
- ✅ Green success banner appears at top
- ✅ Auto-hides after 3 seconds
- ✅ User can dismiss it

### Test 8.4: Error Feedback
1. Try to add link without being logged in
2. Try invalid URL

**Expected:**
- ✅ Red error banner appears
- ✅ Error message is clear
- ✅ User directed on how to fix

---

## Phase 9: Security 🔐

### Test 9.1: Session Required
1. Log out
2. Try to access `/pages/support-settings.php`

**Expected:**
- ✅ Redirects to login page
- ✅ Does not expose settings

### Test 9.2: HTTPS Recommended
1. Check webhook endpoints

**Expected:**
- ✅ Webhooks should only work over HTTPS
- ✅ Signature verification prevents spoofing

### Test 9.3: XSS Prevention
1. Try to add support link with script tag:
```
https://ko-fi.com"><script>alert('xss')</script>
```

**Expected:**
- ✅ Script not executed
- ✅ URL stored safely escaped
- ✅ URL validation rejects invalid input

---

## Phase 10: Performance ⚡

### Test 10.1: Large Dataset
1. Insert 100+ supporters for one author:
```sql
INSERT INTO supporters (supporter_id, author_id, tip_amount, status)
SELECT id, 1, RAND()*100, 'active' FROM users LIMIT 100;
```

2. Click Supporters tab on book

**Expected:**
- ✅ Page loads quickly
- ✅ Limit correctly caps results (20 default)
- ✅ Sorting by amount works

### Test 10.2: API Response Time
1. Use browser DevTools Network tab
2. Call get-top-supporters API with large dataset

**Expected:**
- ✅ Response < 500ms
- ✅ Database query optimized with indexes

---

## 📋 Test Report Template

```
SUPPORTER SYSTEM TEST REPORT
Date: ___________
Tester: ___________

PHASE 1: Database
[ ] Tables created correctly
[ ] Foreign keys working

PHASE 2: Support Settings
[ ] Page loads without errors
[ ] Can add Ko-fi link
[ ] Can add Patreon link
[ ] Preview updates live
[ ] Error handling works

PHASE 3: Book Integration
[ ] Support modal appears
[ ] Links open in new tab
[ ] Supporters tab displays
[ ] Top supporters ranked correctly

PHASE 4: APIs
[ ] Get author links API works
[ ] Get top supporters API works
[ ] Add support link API works

PHASE 5: Webhooks
[ ] Patreon webhook receives events
[ ] Ko-fi webhook receives donations
[ ] Events processed without duplication

PHASE 6: Navigation
[ ] Menu item appears
[ ] Profile settings tabs work

PHASE 7: Data Integrity
[ ] Unique constraints work
[ ] Foreign keys cascade
[ ] URL validation working

PHASE 8: UX
[ ] Empty states display
[ ] Success messages show
[ ] Error messages clear

PHASE 9: Security
[ ] Session required
[ ] XSS prevented
[ ] Data properly escaped

PHASE 10: Performance
[ ] Large datasets handled
[ ] API response time < 500ms

OVERALL RESULT: [ ] PASS [ ] FAIL

Issues Found:
1. ___________
2. ___________

Sign-off: ___________
```

---

## 🚀 Deployment Checklist

Before going live:
- [ ] All tests pass
- [ ] Environment variables set
- [ ] Database backed up
- [ ] Webhooks configured on Ko-fi/Patreon
- [ ] Error logging enabled
- [ ] Monitors set up for webhook failures
- [ ] Docs provided to team

---

## 📞 Rollback Plan

If issues found:
1. Disable webhooks temporarily
2. Don't process old events
3. Users can still see/add links
4. Don't delete data, investigate first

---

**Last Updated:** 2024
**Status:** Ready for Testing ✅
