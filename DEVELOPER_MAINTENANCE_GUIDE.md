# 🛠️ Supporter System - Developer Maintenance Guide

## Overview

This guide helps developers maintain, debug, and extend the supporter system.

---

## 📁 File Organization

```
scrollnovels/
├── api/
│   ├── supporters/
│   │   ├── add-support-link.php (68 lines) - UPSERT pattern
│   │   ├── get-author-links.php (42 lines) - Public API
│   │   └── get-top-supporters.php (73 lines) - Ranked list
│   └── webhooks/
│       ├── patreon.php (118 lines) - Event handler
│       └── kofi.php (97 lines) - Donation handler
├── pages/
│   ├── book.php (MODIFIED)
│   │   ├── loadSupporters() function
│   │   ├── openSupportModal() function
│   │   └── supporters-content div
│   ├── profile-settings.php (MODIFIED)
│   │   └── Added tab navigation
│   ├── support-settings.php (285 lines) - Author dashboard
│   └── supporter-setup.php (98 lines) - DB init
└── includes/
    └── header.php (MODIFIED) - Added menu link
```

---

## 🔍 Key Functions & Methods

### Database Helper
```php
// Auto-create tables if missing (in each API)
$pdo->exec("CREATE TABLE IF NOT EXISTS supporters (...)");
```

### API Response Pattern
```php
// Standard response format
echo json_encode([
    'success' => true,
    'message' => 'Success message',
    'data' => $data
]);

// Error response
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Error message'
]);
```

### JavaScript Functions (book.php)
```javascript
// Load supporters when tab clicked
loadSupporters()

// Open support modal
openSupportModal(bookId)

// Close support modal  
closeSupportModal()

// HTML escaping utility
htmlEscapeComment(text)
```

### Database Queries Used

**Insert/Update (UPSERT):**
```sql
INSERT INTO author_links (...)
ON DUPLICATE KEY UPDATE
    link_url = VALUES(link_url),
    updated_at = NOW()
```

**Get Links:**
```sql
SELECT link_type, link_url FROM author_links
WHERE author_id = ? AND is_verified = 1
```

**Get Top Supporters:**
```sql
SELECT s.*, u.username, u.profile_image
FROM supporters s
JOIN users u ON s.supporter_id = u.id
WHERE s.author_id = ?
GROUP BY s.supporter_id
ORDER BY s.tip_amount DESC, s.created_at DESC
LIMIT ?
```

---

## 🐛 Common Issues & Solutions

### Issue: Support links not showing on book
**Cause:** Link not verified or wrong author_id

**Debug:**
```sql
SELECT * FROM author_links 
WHERE author_id = ? AND is_verified = 1;
```

**Solution:** 
- Verify `is_verified = 1` 
- Check correct author_id in URL

---

### Issue: Supporters tab shows nothing
**Cause:** No supporter records or API failing

**Debug:**
```javascript
// Check in browser console
fetch('/api/supporters/get-top-supporters.php?author_id=1')
  .then(r => r.json())
  .then(d => console.log(d))
```

**Solution:**
- Insert test supporter record
- Check for JavaScript errors in console
- Verify author_id parameter

---

### Issue: Webhooks not processing
**Cause:** Signature mismatch or wrong token

**Debug:**
```php
// Log webhook data
error_log("Webhook received: " . json_encode($_POST));
```

**Solution:**
- Verify PATREON_WEBHOOK_SECRET matches
- Check KOFI_WEBHOOK_TOKEN in env
- Ensure webhook URL is publicly accessible

---

### Issue: Session authentication failing
**Cause:** Not checking $_SESSION['user_id']

**Debug:**
```php
// In any page
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/auth/login.php'));
    exit;
}
```

**Solution:** All API endpoints except webhooks require session

---

## 🔄 Update Procedures

### Adding New Support Platform

1. **Update Database:**
```sql
ALTER TABLE author_links ADD COLUMN paypal_id VARCHAR(255);
```

2. **Update Enum:**
```sql
ALTER TABLE author_links MODIFY COLUMN link_type 
ENUM('kofi','patreon','paypal','buymeacoffee');
```

3. **Update UI:** Add input in support-settings.php

4. **Update APIs:** Add handling in get-author-links.php

---

### Changing Preview Display

Edit `pages/support-settings.php` JavaScript:
```javascript
// Add new platform to preview
if (buymeacoffee) {
    html += `<a href="${htmlEscape(buymeacoffee)}" ...>
        🍵 Buy Me a Coffee
    </a>`;
}
```

---

### Modifying Webhook Events

In `api/webhooks/patreon.php`:
```php
switch ($event_type) {
    case 'new_event_type':
        // Handle new event
        break;
}
```

---

## 📊 Database Maintenance

### Backup Tables
```sql
-- Before major changes
CREATE TABLE supporters_backup LIKE supporters;
INSERT supporters_backup SELECT * FROM supporters;
```

### Clean Up Old Webhooks
```sql
-- Remove processed webhooks older than 30 days
DELETE FROM patreon_webhooks 
WHERE processed = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Verify Data Integrity
```sql
-- Check orphaned records
SELECT s.* FROM supporters s
LEFT JOIN users u ON s.supporter_id = u.id
WHERE u.id IS NULL;

SELECT al.* FROM author_links al
LEFT JOIN users u ON al.author_id = u.id
WHERE u.id IS NULL;
```

---

## 🧪 Testing Procedures

### API Endpoint Testing
```bash
# Get author links
curl -X GET "http://localhost/api/supporters/get-author-links.php?author_id=1"

# Add support link
curl -X POST "http://localhost/api/supporters/add-support-link.php" \
  -H "Content-Type: application/json" \
  -d '{"link_type":"kofi","link_url":"https://ko-fi.com/test"}'

# Get top supporters
curl -X GET "http://localhost/api/supporters/get-top-supporters.php?author_id=1&limit=10"
```

### JavaScript Console Testing
```javascript
// Test API from browser
fetch('/api/supporters/get-author-links.php?author_id=1')
  .then(r => r.json())
  .then(d => console.log(d))

// Test modal function
openSupportModal(1)

// Test supporters load
loadSupporters()
```

---

## 📝 Code Review Checklist

When reviewing changes:

### Security
- [ ] All user input validated
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS prevention (htmlspecialchars)
- [ ] Authentication checked where needed
- [ ] Webhook signatures verified

### Performance
- [ ] Database queries optimized
- [ ] Indexes used correctly
- [ ] Limits applied to prevent abuse
- [ ] No N+1 query problems
- [ ] Cache strategies implemented

### Code Quality
- [ ] Functions single-purpose
- [ ] Variables clearly named
- [ ] Comments explain "why" not "what"
- [ ] Error handling comprehensive
- [ ] No dead code

### User Experience
- [ ] Error messages helpful
- [ ] Success feedback shown
- [ ] Loading states visible
- [ ] Empty states handled
- [ ] Responsive design works

---

## 🚨 Monitoring & Logging

### Key Metrics to Monitor

```php
// Log API calls
error_log("API called: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Log webhook events
error_log("Webhook event: $event_type from " . $_SERVER['REMOTE_ADDR']);

// Log errors
error_log("Error: " . $e->getMessage());
```

### Alert Triggers

- Webhook failures (check logs hourly)
- API error rate > 5%
- Database connection failures
- Session authentication failures

---

## 🔐 Security Hardening

### Additional Measures (Optional)

```php
// Rate limiting for APIs
$ip = $_SERVER['REMOTE_ADDR'];
$key = "api_calls_$ip";
// Implement rate limiting logic

// CSRF tokens for forms
// Add if not already in place

// API key authentication
// Consider for future public APIs
```

---

## 📚 Learning Resources

### Understanding Supporter System

1. **Database Design:** See `SUPPORTER_SYSTEM_COMPLETE.md`
2. **API Specifications:** See Quick Reference
3. **Testing Guide:** See `SUPPORTER_SYSTEM_TESTING.md`
4. **Implementation Details:** Code comments

### PHP Patterns Used

- **UPSERT Pattern:** INSERT...ON DUPLICATE KEY UPDATE
- **Repository Pattern:** API methods fetch/store data
- **Factory Pattern:** Automatic table creation
- **Observer Pattern:** Webhooks trigger updates

---

## 🎓 Training for New Developers

### Week 1: Understanding
1. Read all 3 documentation files
2. Review database schema
3. Study API endpoints

### Week 2: Development
1. Set up local environment
2. Run test procedures
3. Make small modifications

### Week 3: Deployment
1. Deploy to staging
2. Run full test suite
3. Deploy to production

### Week 4: Maintenance
1. Monitor logs
2. Handle support tickets
3. Optimize performance

---

## 📞 Support & Troubleshooting

### Quick Diagnostic Steps

```php
// Check database tables exist
$tables = ['supporters', 'author_links', 'patreon_webhooks'];
foreach ($tables as $table) {
    $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    echo $result ? "✓ $table exists\n" : "✗ $table missing\n";
}

// Check API connectivity
$response = @file_get_contents('/api/supporters/get-author-links.php?author_id=1');
echo $response ? "✓ API responding\n" : "✗ API error\n";

// Check session
session_start();
echo isset($_SESSION['user_id']) ? "✓ Session active\n" : "✗ No session\n";
```

---

## 🔄 Version History

### v1.0 (Current)
- Core supporter system implemented
- Ko-fi and Patreon webhooks
- Support settings page
- Book page integration
- Top supporters display

### v1.1 (Planned)
- Patreon OAuth integration
- Subscriber-only content
- Badge system
- Analytics dashboard

---

## 📋 Deployment Checklist

Before deploying:

- [ ] All tests passing
- [ ] Database migrated
- [ ] Environment variables set
- [ ] Webhooks configured
- [ ] Backups created
- [ ] Logs enabled
- [ ] Monitoring active
- [ ] Team trained

---

## 🎯 Success Metrics

Track these KPIs:

- Authors with support links added
- Total amount tipped through platform
- Number of top supporters displayed
- Webhook event processing rate
- API error rate (target < 1%)
- Support modal click-through rate

---

## 📞 Contact & Escalation

For issues:

1. **Check logs** - Look at error logs first
2. **Debug locally** - Reproduce in dev environment
3. **Consult docs** - Review relevant documentation
4. **Test thoroughly** - Verify fix works
5. **Deploy carefully** - Use staging first

---

## 🎓 Knowledge Base

### Key Files to Understand
1. `api/supporters/add-support-link.php` - UPSERT pattern
2. `api/supporters/get-top-supporters.php` - JOIN queries
3. `api/webhooks/patreon.php` - Event handling
4. `pages/support-settings.php` - Live preview JavaScript
5. `pages/book.php` - Modal and tab integration

### Common Patterns
1. **Authentication:** Check `$_SESSION['user_id']`
2. **Validation:** Use `filter_var(..., FILTER_VALIDATE_URL)`
3. **Responses:** Always return JSON with success/error
4. **Errors:** Use proper HTTP status codes
5. **Logging:** Log important events

---

## ✅ Final Checklist

- [ ] Documentation read
- [ ] Database understood
- [ ] APIs tested
- [ ] Security verified
- [ ] Performance checked
- [ ] Monitoring setup
- [ ] Team trained
- [ ] Ready to maintain

---

**Last Updated:** Today  
**Maintained By:** Development Team  
**Status:** Production Ready ✅  
