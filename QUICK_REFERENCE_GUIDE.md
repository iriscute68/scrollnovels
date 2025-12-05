# Developer Quick Reference Guide

## Essential Commands

### Start Development Server
```bash
npm start              # Start Node.js server on port 3000
npm run dev          # Start with auto-reload (nodemon)
npm run migrate      # Initialize database schema
```

### Access Points
```
API Base:    http://localhost:3000/api/v1
Admin UI:    http://localhost:3000/admin
Health:      http://localhost:3000/health
```

### Database
```bash
psql -U scrollnovels -d scrollnovels    # Connect to DB
\dt                                      # List tables
\di                                      # List indexes
SELECT * FROM users LIMIT 5;            # Query example
```

### Redis
```bash
redis-cli                               # Connect to Redis
KEYS *                                  # List all keys
LRANGE bull:patreon-rewards:* 0 -1    # Check job queue
FLUSHDB                                 # Clear all data (WARNING!)
```

---

## API Endpoints Reference

### Authentication
```bash
# Login
POST /api/v1/auth/login
Body: { "username": "user", "password": "pass" }
Response: { "token": "jwt_token_here" }

# Refresh Token
POST /api/v1/auth/refresh
Header: Authorization: Bearer $TOKEN
```

### Patreon Integration
```bash
# Link Patreon Account
POST /api/v1/oauth/patreon/callback?code=CODE
Response: Redirects to dashboard with token

# Get Profile
GET /api/v1/patreon/profile
Header: Authorization: Bearer $TOKEN
Response: { "tier_id": "123", "pledge_amount": 500, ... }

# List Active Links (Admin)
GET /api/v1/admin/patreon-links
Header: Authorization: Bearer $ADMIN_TOKEN
```

### Points System
```bash
# Get Balance
GET /api/v1/points/balance
Header: Authorization: Bearer $TOKEN
Response: { "total_points": 1500, "free_points": 500, "patreon_points": 1000 }

# Get History
GET /api/v1/points/history?limit=50&offset=0
Header: Authorization: Bearer $TOKEN
Response: [{ "delta": 100, "type": "patreon_reward", "created_at": "..." }, ...]

# Purchase Points
POST /api/v1/points/topup
Header: Authorization: Bearer $TOKEN
Body: { "amount": 100 }
Response: { "new_balance": 1600, "transaction_id": "..." }
```

### Leaderboards
```bash
# Get Rankings
GET /api/v1/books/rankings?period=monthly&limit=20
Header: Authorization: Bearer $TOKEN
Response: [{ "book_id": 1, "rank": 1, "total_support_points": 5000 }, ...]

# Support Book
POST /api/v1/books/support
Header: Authorization: Bearer $TOKEN
Body: { "book_id": 1, "amount": 100 }
Response: { "support_id": "...", "total_support": 1500 }
```

### Admin Operations
```bash
# Dashboard Stats
GET /api/v1/admin/dashboard
Header: Authorization: Bearer $ADMIN_TOKEN
Response: { "total_distributed": 50000, "active_patrons": 25, ... }

# Export Ledger
POST /api/v1/admin/points-transactions/export
Header: Authorization: Bearer $ADMIN_TOKEN
Body: { "user_id": null, "start_date": "2024-01-01", "end_date": "2024-12-31" }
Response: CSV file

# Reverse Support
POST /api/v1/admin/book-support/SUPPORT_ID/reverse
Header: Authorization: Bearer $ADMIN_TOKEN
Body: { "reason": "Duplicate purchase" }
Response: { "refunded_points": 100, "transaction_id": "..." }
```

---

## Database Schema Quick Reference

### Core Tables

**users**
```sql
SELECT id, username, email, role, created_at FROM users WHERE id = $1;
-- Roles: user, admin, moderator
```

**user_points_balance**
```sql
SELECT user_id, total_points, free_points, patreon_points FROM user_points_balance WHERE user_id = $1;
-- Updated: denormalized for O(1) lookups
```

**points_transactions** (immutable ledger)
```sql
SELECT * FROM points_transactions 
WHERE user_id = $1 
ORDER BY created_at DESC 
LIMIT 50;
-- Types: patreon_reward, support, topup, decay, reversed, expired
```

**patreon_links**
```sql
SELECT pl.*, ptc.monthly_points 
FROM patreon_links pl
LEFT JOIN patreon_tier_config ptc ON pl.tier_id = ptc.tier_id
WHERE pl.user_id = $1;
-- Fields: active, last_reward_date, access_token, patreon_user_id
```

**book_support**
```sql
SELECT bs.*, b.title, bs.effective_points
FROM book_support bs
JOIN books b ON bs.book_id = b.id
WHERE bs.user_id = $1
ORDER BY bs.created_at DESC;
-- Fields: reversed, multiplier, effective_points
```

**book_rankings**
```sql
SELECT * FROM book_rankings
WHERE day = CURRENT_DATE AND period = 'monthly'
ORDER BY rank_position
LIMIT 10;
-- Periods: daily, weekly, monthly, all_time
```

**admin_actions** (audit trail)
```sql
SELECT * FROM admin_actions
WHERE created_at > NOW() - INTERVAL '7 days'
ORDER BY created_at DESC
LIMIT 100;
-- Tracks: fraud_detected, points_reversed, support_reversed, etc.
```

---

## Code Patterns

### Making Authenticated API Calls
```javascript
// In admin-dashboard.js
const token = localStorage.getItem('token');
const response = await fetch('/api/v1/admin/dashboard', {
  headers: { 'Authorization': `Bearer ${token}` }
});
const data = await response.json();
```

### Database Query Pattern
```javascript
// In routes/admin.js
const client = await pool.connect();
try {
  await client.query('BEGIN');
  
  // Query 1
  const result1 = await client.query('SELECT ...', [params]);
  
  // Query 2 (dependent on result1)
  const result2 = await client.query('UPDATE ...', [result1.rows[0].id]);
  
  await client.query('COMMIT');
  res.json({ success: true });
} catch (err) {
  await client.query('ROLLBACK');
  res.status(500).json({ error: err.message });
} finally {
  client.release();
}
```

### Background Job Pattern
```javascript
// In background-tasks.js
jobQueue.process(async (job) => {
  console.log(`[Job Type] Processing job: ${job.id}`);
  
  const result = await performWork();
  
  // Return result or throw error
  return { processed: result };
});

// Schedule job
jobQueue.add({}, {
  repeat: { cron: '0 0 * * *' },  // Daily at midnight
  removeOnComplete: true
});
```

### Modal Confirmation Pattern
```javascript
// In admin-dashboard.js
function showConfirmModal(title, message, onConfirm) {
  const modal = document.getElementById('confirmModal');
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMessage').textContent = message;
  
  document.getElementById('confirmBtn').onclick = async () => {
    modal.style.display = 'none';
    await onConfirm();
  };
  
  modal.style.display = 'flex';
}

// Usage
showConfirmModal(
  'Reverse Transaction?',
  'This will refund points and create an audit record.',
  async () => {
    await reverseSupport(supportId);
  }
);
```

---

## Common Tasks

### Task: Add New Patreon Tier
```sql
-- 1. Add tier config
INSERT INTO patreon_tier_config (tier_id, tier_name, monthly_points, multiplier)
VALUES ('new-tier-123', 'Gold Tier', 1000, 1.5);

-- 2. Verify users can receive rewards
SELECT COUNT(*) FROM patreon_links WHERE tier_id = 'new-tier-123';

-- 3. Next reconciliation will apply new rewards (or manually run job)
```

### Task: Manually Award Points
```sql
-- 1. Update balance
UPDATE user_points_balance
SET total_points = total_points + 500, free_points = free_points + 500
WHERE user_id = $1;

-- 2. Log transaction
INSERT INTO points_transactions (user_id, delta, type, source, metadata)
VALUES ($1, 500, 'manual_grant', 'admin', '{"reason": "Customer service"}');

-- 3. Log admin action
INSERT INTO admin_actions (admin_user_id, action_type, target_type, target_id, metadata)
VALUES ($ADMIN_ID, 'points_granted', 'user', $1, '{"amount": 500}');
```

### Task: Reverse User's Points
```sql
-- 1. Get current balance
SELECT total_points FROM user_points_balance WHERE user_id = $1;

-- 2. Reverse all points
UPDATE user_points_balance SET total_points = 0, patreon_points = 0 WHERE user_id = $1;

-- 3. Record transaction
INSERT INTO points_transactions (user_id, delta, type, source, metadata)
VALUES ($1, -$ORIGINAL_AMOUNT, 'fraud_reversal', 'system', '{"reason": "..."}');

-- 4. Deactivate Patreon links
UPDATE patreon_links SET active = false WHERE user_id = $1;
```

### Task: Export Points Ledger
```javascript
// Already implemented in admin UI at: /admin > Points Ledger > Export CSV
// Or via API:
POST /api/v1/admin/points-transactions/export
Body: {
  "user_id": null,           // Optional: filter by user
  "type": "patreon_reward",  // Optional: filter by type
  "start_date": "2024-01-01",
  "end_date": "2024-12-31"
}
// Returns: CSV file download
```

### Task: Check Background Job Status
```bash
# Patreon rewards job
redis-cli LRANGE bull:patreon-rewards:completed 0 -1
redis-cli LRANGE bull:patreon-rewards:failed 0 -1

# Leaderboards job
redis-cli LRANGE bull:leaderboards:active 0 -1

# Check job details
redis-cli GET bull:patreon-rewards:1234  # Get job data by ID

# Clear failed jobs (after fixing issue)
redis-cli DEL bull:patreon-rewards:failed
```

---

## Debugging

### Enable Verbose Logging
```bash
# In server/index.js
console.debug = (msg) => {
  const timestamp = new Date().toISOString();
  console.log(`${timestamp} [DEBUG] ${msg}`);
};

// In code
console.debug('User ID:', userId);
console.debug('Query result:', result.rows);
```

### Check Database Slow Queries
```sql
-- Enable query logging
ALTER DATABASE scrollnovels SET log_min_duration_statement = 100;  -- Log queries > 100ms

-- Check slow query log
SELECT query, calls, mean_time FROM pg_stat_statements ORDER BY mean_time DESC LIMIT 10;
```

### Monitor API Calls
```javascript
// In admin-dashboard.js - add logging
const response = await fetch(url, { headers });
console.log(`${new Date().toISOString()} ${method} ${url} ${response.status}`);
if (!response.ok) {
  console.error('Response body:', await response.text());
}
```

### Test Webhook Locally
```bash
# Create signature
TOKEN="your_webhook_secret"
BODY='{"type":"members:pledge:create","data":{"id":"123"}}'
SIGNATURE=$(echo -n "$BODY" | openssl dgst -md5 -hmac "$TOKEN" -hex | awk '{print $2}')

# Send to local server
curl -X POST http://localhost:3000/api/v1/webhooks/patreon \
  -H "X-Patreon-Signature: $SIGNATURE" \
  -H "Content-Type: application/json" \
  -d "$BODY"
```

---

## Important Notes

### ⚠️ DO NOT
- ❌ Modify `points_transactions` table (immutable ledger)
- ❌ Delete `admin_actions` records (audit trail)
- ❌ Manually change `user_points_balance` without transaction entry
- ❌ Expose `PATREON_CLIENT_SECRET` in frontend code
- ❌ Use `client.query()` without try/finally to release connection
- ❌ Run `TRUNCATE` on production without backup
- ❌ Change `JWT_SECRET` without re-issuing all tokens

### ✅ DO
- ✓ Always use transactions for multi-step operations
- ✓ Log all manual operations to `admin_actions` table
- ✓ Release database connections in finally block
- ✓ Verify webhook signatures before processing
- ✓ Test changes on staging before production
- ✓ Back up database before major operations
- ✓ Monitor background job execution daily

---

## Performance Tips

### Query Optimization
```sql
-- ✅ GOOD: Use index
SELECT * FROM points_transactions 
WHERE user_id = $1 AND created_at > now() - interval '7 days'
ORDER BY created_at DESC
LIMIT 50;

-- ❌ SLOW: No index used
SELECT * FROM points_transactions 
WHERE EXTRACT(MONTH FROM created_at) = 5
AND user_id = $1;
```

### Connection Pool Management
```javascript
// Monitor pool health
app.get('/debug/pool', (req, res) => {
  res.json({
    available: pool.availableObjectsCount,
    waiting: pool.waitingClientsCount,
    total: pool.waitingClientsCount + pool.availableObjectsCount
  });
});
```

### Caching Strategy
```javascript
// Cache expensive queries (e.g., rankings)
const rankingsCache = new Map();
const CACHE_TTL = 60 * 60 * 1000; // 1 hour

async function getRankings(period) {
  const cacheKey = `rankings:${period}`;
  if (rankingsCache.has(cacheKey)) {
    const { data, timestamp } = rankingsCache.get(cacheKey);
    if (Date.now() - timestamp < CACHE_TTL) {
      return data;
    }
  }
  
  const data = await queryRankings(period);
  rankingsCache.set(cacheKey, { data, timestamp: Date.now() });
  return data;
}
```

---

## Testing Checklist

### Before Deployment
- [ ] All 13 database tables created and verified
- [ ] All 20+ indexes present
- [ ] Admin user created with correct role
- [ ] JWT secret configured and strong
- [ ] Patreon OAuth credentials verified
- [ ] Redis connection working
- [ ] All API endpoints responding correctly
- [ ] Admin dashboard loading
- [ ] Background jobs executing
- [ ] Webhooks endpoint accepting requests
- [ ] CSV export working
- [ ] Transaction reversals working
- [ ] Patreon link management working
- [ ] SSL certificate installed
- [ ] Backup tested

### Smoke Tests (Post-Deploy)
```bash
# 1. Health check
curl http://localhost:3000/health

# 2. Login
curl -X POST http://localhost:3000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'

# 3. Get balance
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:3000/api/v1/points/balance

# 4. Admin dashboard
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:3000/api/v1/admin/dashboard

# 5. Job status
redis-cli LRANGE bull:patreon-rewards:completed 0 -1
```

---

## Resources & Documentation

- **Express.js**: https://expressjs.com/
- **PostgreSQL**: https://www.postgresql.org/docs/
- **BullMQ**: https://docs.bullmq.io/
- **JWT**: https://jwt.io/
- **Patreon API**: https://docs.patreon.com/
- **Redis**: https://redis.io/commands/

---

**Last Updated**: 2024
**Version**: 1.0
**Status**: Production Ready ✅
