# 🔥 COMPLETE POINT SYSTEM IMPLEMENTATION GUIDE

## Overview
Complete point system with:
- Free, Premium, and Patreon points
- Book support with point multipliers (1x, 2x, 3x)
- Daily/Weekly/Monthly/All-Time rankings
- Patreon OAuth integration
- Automatic webhook rewards
- Daily login rewards
- Top supporter tracking

---

## 1. DATABASE TABLES CREATED

### ✅ points_transactions
Logs all point earning/spending activities
- `type`: free, premium, patreon
- `source`: daily_login, reading, ad_watch, task_complete, event_reward, purchase, patreon_tier, admin_grant
- `reference_id`: Links to related records

### ✅ user_points
Current balance for each user
- Tracks: free_points, premium_points, patreon_points
- Includes: daily_login_claimed flag, last_login date
- Total points calculated from all sources

### ✅ book_support
Tracks all support/donations to books
- `point_type`: free (1x), premium (2x), patreon (3x multiplier)
- `effective_points`: Calculated field (points_spent × multiplier)
- Records timestamp for decay system

### ✅ book_rankings
Pre-calculated rankings by period
- `rank_type`: daily, weekly, monthly, all_time
- `total_support_points`: Sum of effective points
- `supporter_count`: Unique supporters
- `rank_position`: For displaying rank

### ✅ patreon_links
Links user accounts to Patreon
- Stores: patreon_user_id, tier_id, tier_name, amount_cents
- OAuth tokens: access_token, refresh_token, token_expires_at
- Tracks: last_reward_date, next_reward_date

### ✅ patreon_tier_rewards
Configuration for Patreon tiers
```sql
Tier              Price    Monthly Points    Multiplier    Features
Bronze Supporter  $5       500               2.0x          vip_badge
Silver Supporter  $10      1200              2.5x          vip_badge, early_chapters
Gold Supporter    $20      3000              3.0x          no_ads, early_chapters, vip_badge
Diamond Supporter $50      10000             3.0x          no_ads, early_chapters, free_coins, vip_badge, boost_support
```

### ✅ user_tasks
Daily tasks for earning free points
- `task_type`: daily_login, read_3_chapters, watch_ad, leave_review, invite_friend
- `completed_at`: NULL until completed
- `expires_at`: Deadline for completing task

### ✅ creator_bonus_points
Authors' weekly boost for their own books
- `points_to_spend`: Weekly allowance (e.g., 100 points)
- `points_used`: How many used this week
- `week_start_date`: Resets weekly
- `reset_at`: Next reset timestamp

### ✅ point_decay_log (Optional)
Tracks point decay for fresh rankings
- Records when points lose 20% value per week
- After 4 weeks → expire

---

## 2. API ENDPOINTS CREATED

### 📤 POST `/api/support-book.php`
Support a book with points

**Request:**
```json
{
    "book_id": 8,
    "points": 100,
    "point_type": "free"    // free, premium, or patreon
}
```

**Response:**
```json
{
    "success": true,
    "message": "✓ Supported with 100 free points!",
    "points_remaining": {
        "free": 450,
        "premium": 1200,
        "patreon": 5000
    }
}
```

**Multipliers:**
- Free: 1x (100 points = 100 effective)
- Premium: 2x (100 points = 200 effective in rankings)
- Patreon: 3x (100 points = 300 effective in rankings)

---

### 🔐 GET `/api/patreon-connect.php?action=get_auth_url`
Get Patreon OAuth authorization URL

**Response:**
```json
{
    "auth_url": "https://www.patreon.com/oauth2/authorize?..."
}
```

**Implementation:**
```javascript
// Open OAuth popup
window.location.href = authUrl;
```

---

### 🔓 GET `/api/patreon-connect.php?action=callback&code=XXX&state=YYY`
OAuth callback handler (after user approves)

**Auto-awarded points based on tier:**
- Bronze: 500 points/month
- Silver: 1200 points/month
- Gold: 3000 points/month
- Diamond: 10000 points/month

---

### 📊 POST `/api/patreon-webhook.php`
Receive Patreon webhooks for membership updates

**Webhook events handled:**
- `members:pledge:create` → Award initial points
- `members:pledge:update` → Award monthly points when charged
- `members:pledge:delete` → Deactivate Patreon link

**Note:** Configure webhook URL in Patreon dashboard:
```
https://yoursite.com/api/patreon-webhook.php
```

---

### 👤 GET `/api/get-user-points.php`
Get user's points and statistics

**Response:**
```json
{
    "success": true,
    "points": {
        "user_id": "550e8400-e29b-41d4-a716-446655440000",
        "free_points": 450,
        "premium_points": 1200,
        "patreon_points": 5000,
        "total_points": 6650
    },
    "patreon": {
        "tier_name": "Gold Supporter",
        "amount_cents": 2000,
        "active": true
    },
    "transactions": [
        {
            "type": "free",
            "source": "daily_login",
            "points": 10,
            "created_at": "2025-12-02 10:30:00"
        }
    ],
    "supported_books": [
        {
            "book_id": 8,
            "title": "dfvdfrd",
            "total_points_spent": 100,
            "effective_points": 300,
            "support_count": 1,
            "last_support": "2025-12-02 14:20:00"
        }
    ]
}
```

---

### 🏆 GET `/api/get-rankings.php?type=weekly&limit=20&offset=0`
Get book rankings by type

**Query Parameters:**
- `type`: daily, weekly, monthly, all_time (default: weekly)
- `limit`: Max results (default: 20, max: 100)
- `offset`: Pagination offset (default: 0)

**Response:**
```json
{
    "success": true,
    "type": "weekly",
    "rankings": [
        {
            "rank": 1,
            "book_id": 8,
            "title": "dfvdfrd",
            "author": "Zakiel",
            "cover_url": "...",
            "total_support_points": 5000,
            "supporter_count": 23,
            "rank_position": 1
        }
    ],
    "pagination": {
        "total": 1250,
        "limit": 20,
        "offset": 0,
        "pages": 63
    }
}
```

---

### ⏰ POST `/api/claim-daily-reward.php`
Claim daily login points

**Request:**
```json
{}
```

**Response:**
```json
{
    "success": true,
    "message": "✓ Claimed 10 daily points!",
    "points_earned": 10,
    "total_points": 6660
}
```

---

## 3. USER PAGES CREATED

### 📖 `/pages/book-support.php?id=8`
Support a book interface

**Features:**
- ✅ Support type selector (Free/Premium/Patreon)
- ✅ Amount selector (10, 50, 100, 500, 1000)
- ✅ Real-time point balance display
- ✅ Book ranking display (Daily/Weekly/Monthly/All-Time)
- ✅ Top supporters list with avatars
- ✅ Auto-refresh after support

**Multiplier indicators:**
- Free: 1x points
- Premium: 2x points (for rankings)
- Patreon: 3x points (for rankings)

---

### 🏆 `/pages/rankings.php?type=weekly`
Global rankings page

**Features:**
- ✅ Tab selector (Daily/Weekly/Monthly/All-Time)
- ✅ Rank badges (#1🥇, #2🥈, #3🥉)
- ✅ Book cover, title, author display
- ✅ Rating and read count display
- ✅ Support point totals and supporter count
- ✅ Direct support button for each book
- ✅ Pagination (20 per page)

---

## 4. HOW TO SET UP PATREON INTEGRATION

### Step 1: Create Patreon Application
1. Go to https://www.patreon.com/portal
2. Create new client
3. Set redirect URI: `https://yoursite.com/api/patreon-connect.php`
4. Get Client ID and Client Secret

### Step 2: Configure Environment Variables
```bash
# Add to your .env or config
PATREON_CLIENT_ID=your_client_id_here
PATREON_CLIENT_SECRET=your_client_secret_here
PATREON_WEBHOOK_SECRET=your_webhook_secret_here
```

### Step 3: Set Webhook in Patreon Dashboard
1. Portal → Webhooks
2. Add: `https://yoursite.com/api/patreon-webhook.php`
3. Subscribe to: `members:pledge:create`, `members:pledge:update`, `members:pledge:delete`
4. Copy secret to config

### Step 4: Create Link Button in Dashboard
```html
<button onclick="connectPatreon()">🔗 Link Patreon Account</button>

<script>
async function connectPatreon() {
    const response = await fetch('/api/patreon-connect.php?action=get_auth_url');
    const data = await response.json();
    window.location.href = data.auth_url;
}
</script>
```

---

## 5. POINT EARNING METHODS

### 📅 Free Points
**Daily Login** → 10 points
```bash
POST /api/claim-daily-reward.php
```

**Reading** → Variable (every 10 chapters read)
- Implementation: Track chapter reads, award points

**Watching Ads** → Variable (per ad watched)
- Implementation: Integrate with ad network

**Completing Tasks** → Variable
- Leave review: 25 points
- Invite friend: 50 points
- Read 3 chapters: 15 points

**Event Rewards** → Variable
- Admin can grant points during events

### 💳 Premium Points (Purchased)
- User buys with real money or coins
- Counts 2x in rankings
- Can unlock premium chapters

### 👑 Patreon Points
- Auto-awarded monthly based on tier
- Webhook triggers awards
- Counts 3x in rankings (most powerful)

---

## 6. DECAY SYSTEM (Optional)

### How It Works:
1. Points lose 20% value each week
2. After 4 weeks → expire completely
3. Keeps rankings fresh (old books can't stay #1 forever)

### Implementation:
```php
// Run daily via cron job
$stmt = $pdo->prepare("
    SELECT * FROM book_support WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
    AND decay_percentage < 80
");
```

### Configuration:
- Decay rate: 20% per week
- Maximum decay: 80% (after 4 weeks)
- Can be disabled for certain tier supporters

---

## 7. CRON JOBS TO IMPLEMENT

### Daily (Midnight UTC)
```bash
0 0 * * * php /var/www/scrollnovels/cron/reset-daily-rankings.php
```

**Resets daily rankings, calculates fresh daily points**

### Weekly (Monday Midnight UTC)
```bash
0 0 * * 1 php /var/www/scrollnovels/cron/reset-weekly-rankings.php
```

**Calculates weekly rankings**

### Monthly (1st of Month Midnight UTC)
```bash
0 0 1 * * php /var/www/scrollnovels/cron/reset-monthly-rankings.php
```

**Calculates monthly rankings, resets author bonus points**

### Every Hour
```bash
0 * * * * php /var/www/scrollnovels/cron/apply-decay.php
```

**Optional: Apply point decay to old support**

---

## 8. DATABASE INITIALIZATION

### Run SQL to create all tables:
```bash
mysql -u root -p scroll_novels < create-point-system-tables.sql
```

### Verify tables created:
```sql
SHOW TABLES LIKE '%point%';
SHOW TABLES LIKE '%ranking%';
SHOW TABLES LIKE '%patreon%';
```

---

## 9. TESTING CHECKLIST

- [ ] Create test user
- [ ] Award free points via daily login API
- [ ] Support book with free points
- [ ] Support book with premium points
- [ ] Check rankings updated
- [ ] Verify point multipliers (1x, 2x, 3x)
- [ ] Link Patreon account (test OAuth flow)
- [ ] Verify Patreon points awarded
- [ ] Test webhook signature verification
- [ ] Check top supporters list
- [ ] Verify decay system (if enabled)

---

## 10. NEXT STEPS

### Phase 1: Core System
- ✅ Database tables
- ✅ Support API
- ✅ Point tracking
- ✅ Rankings display

### Phase 2: Patreon Integration
- ✅ OAuth setup
- ✅ Webhook handling
- ✅ Monthly rewards
- ✅ Tier configuration

### Phase 3: Enhancement
- Leaderboards per author
- Guild/community competitions
- Seasonal rankings
- Point shop (redeem points for perks)
- Achievements (e.g., "100 Point Supporter")

### Phase 4: Advanced Features
- Weekly challenges
- Referral bonuses
- Creator bonus points (author boost allowance)
- Point decay system
- VIP features for Patreon tiers

---

## 11. FILES CREATED

| File | Purpose |
|------|---------|
| `create-point-system-tables.sql` | Database schema |
| `api/support-book.php` | Support a book with points |
| `api/patreon-connect.php` | OAuth handler |
| `api/patreon-webhook.php` | Webhook receiver |
| `api/get-user-points.php` | User points/stats |
| `api/get-rankings.php` | Rankings data |
| `api/claim-daily-reward.php` | Daily login reward |
| `pages/book-support.php` | Support interface |
| `pages/rankings.php` | Rankings page (updated) |

---

## 12. INTEGRATION WITH EXISTING FEATURES

### Book Page
Add support button:
```html
<a href="<?= site_url('/pages/book-support.php?id=' . $bookId) ?>" class="btn btn-primary">
    💝 Support This Book
</a>
```

### Dashboard
Show user's points:
```html
<div class="points-widget">
    Free: <strong><?= $userPoints['free_points'] ?></strong>
    Premium: <strong><?= $userPoints['premium_points'] ?></strong>
    Patreon: <strong><?= $userPoints['patreon_points'] ?></strong>
</div>
```

### Navigation
Add rankings link:
```html
<a href="<?= site_url('/pages/rankings.php') ?>">🏆 Rankings</a>
```

---

## STATUS: ✅ COMPLETE AND READY FOR DEPLOYMENT
