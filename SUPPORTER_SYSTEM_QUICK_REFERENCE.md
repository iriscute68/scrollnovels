# Supporter System - Quick Reference

## 🎯 What's New?

A complete supporter system allowing authors to collect tips and manage subscriptions through Ko-fi, Patreon, and PayPal.

---

## 📁 New/Modified Files

### New Files (7 total)
| File | Purpose |
|------|---------|
| `pages/support-settings.php` | Author dashboard for managing support links |
| `pages/supporter-setup.php` | Database initialization |
| `api/supporters/add-support-link.php` | Save author support links |
| `api/supporters/get-author-links.php` | Fetch links for book modal |
| `api/supporters/get-top-supporters.php` | Get top supporters list |
| `api/webhooks/patreon.php` | Patreon event handler |
| `api/webhooks/kofi.php` | Ko-fi donation handler |

### Modified Files (3 total)
| File | Change |
|------|--------|
| `pages/book.php` | Added support modal + supporters tab |
| `pages/profile-settings.php` | Added navigation tabs |
| `includes/header.php` | Added "Support Links" menu item |

---

## 🔌 API Quick Reference

### 1. Add Support Link
```bash
POST /api/supporters/add-support-link.php
Content-Type: application/json

{
  "link_type": "kofi",
  "link_url": "https://ko-fi.com/myauthor"
}
```

### 2. Get Author Links
```bash
GET /api/supporters/get-author-links.php?author_id=123
```

Response: `{ kofi, patreon, paypal }` URLs

### 3. Get Top Supporters
```bash
GET /api/supporters/get-top-supporters.php?author_id=123&limit=20
```

Response: Array of supporter objects with ranking

---

## 🗄️ Database Tables

### `supporters`
- Core table for tracking user-author relationships
- `supporter_id` → `author_id` with `tip_amount`, `status`

### `author_links`
- Stores Ko-fi/Patreon/PayPal URLs per author
- `link_type` + `author_id` is unique

### `patreon_webhooks`
- Deduplicates webhook events
- `event_id` is unique

### `top_supporters_cache`
- Materialized view for performance
- Ranks supporters by total donated

---

## 🎨 UI Components

### Support Modal (book.php)
```javascript
openSupportModal(bookId)
// Opens modal with author's Ko-fi/Patreon/PayPal buttons
```

### Supporters Tab (book.php)
```javascript
loadSupporters()
// Loads top supporters when tab clicked
// Displays with ranking, profile image, tier, amount
```

### Support Settings Page
- URL: `/pages/support-settings.php`
- Lets authors add/edit Ko-fi, Patreon, PayPal links
- Live preview of how links appear on books
- Accessible from account dropdown menu

---

## 🔧 Configuration

Add to `.env`:
```bash
PATREON_CLIENT_ID=xxx
PATREON_CLIENT_SECRET=xxx
PATREON_WEBHOOK_SECRET=xxx
KOFI_API_TOKEN=xxx
KOFI_WEBHOOK_TOKEN=xxx
```

---

## 📊 Data Flow

```
Author → Support Settings Page → API → Database
         ↓
         Book Page Modal ← API ← Database → Reader
         ↓
         Supporters Tab
```

---

## 🚀 Usage Examples

### For Authors
1. Go to account dropdown → "💝 Support Links"
2. Enter Ko-fi URL, Patreon URL, or PayPal link
3. Click "Save"
4. Links appear on all their book pages

### For Readers
1. Click "💝 Support" button on book page
2. Choose Ko-fi, Patreon, or PayPal
3. Gets redirected to author's support page

### View Supporters
1. Click "Supporters" tab on book page
2. See ranking of top supporters by amount
3. Shows supporter name, tier, status

---

## 🔐 Authentication

- **Support Settings:** Requires logged-in author
- **Get Links API:** Public (no auth)
- **Get Supporters API:** Public (no auth)
- **Add Link API:** Requires session
- **Webhooks:** Signature verification only

---

## ✅ Features Implemented

| Feature | Status |
|---------|--------|
| Authors add support links | ✅ Complete |
| Readers see support options | ✅ Complete |
| Top supporters display | ✅ Complete |
| Patreon webhook handler | ✅ Complete |
| Ko-fi webhook handler | ✅ Complete |
| Preview support links | ✅ Complete |
| Multiple payment platforms | ✅ Complete |
| Patreon OAuth | ⏳ Next Phase |
| Subscription tracking | ⏳ Next Phase |

---

## 🐛 Troubleshooting

### Support links not showing on book?
- Check `author_links` table for that author_id
- Verify `is_verified = 1`
- Check link_type is 'kofi', 'patreon', or 'paypal'

### Webhooks not working?
- Verify webhook secret in env matches platform
- Check `patreon_webhooks` table for events
- Review server logs for HTTP errors

### Supporters tab empty?
- Check `supporters` table for that author_id
- Verify tip_amount > 0
- Ensure supporter has user account

---

## 📞 Support

For issues or questions about the supporter system:
1. Check database tables for data
2. Review API response in browser console
3. Check server error logs
4. Verify environment variables set

---

## 🎉 Summary

The supporter system is production-ready with:
- ✅ Database design optimized
- ✅ API endpoints secure and validated
- ✅ User interface intuitive
- ✅ Webhook handlers functional
- ✅ Error handling comprehensive
- ✅ Performance optimized
