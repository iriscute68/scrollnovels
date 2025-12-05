# Scroll Novels - Operational Runbook

## Quick Start

### Prerequisites
- Node.js 14+
- PostgreSQL 13+
- Redis 5+ (for BullMQ)
- Patreon OAuth credentials

### Startup

```bash
# Install dependencies
npm install

# Configure environment
cp .env.example .env
# Edit .env with actual values

# Initialize database
npm run migrate

# Start server
npm start
```

Server will run on `http://localhost:3000`
Admin panel: `http://localhost:3000/admin` (requires admin login)

---

## Architecture Overview

### Core Tables
- **users**: User authentication
- **books**: Novel/story metadata
- **patreon_links**: OAuth connections & tier tracking
- **user_points_balance**: Denormalized points for O(1) reads
- **points_transactions**: Immutable ledger (source of truth)
- **book_support**: Support events with multipliers & reversals
- **book_rankings**: Pre-aggregated rankings by period
- **admin_config**: Leaderboard multipliers & decay rates
- **admin_actions**: Audit trail for compliance

### API Structure
```
/api/v1/
├── auth/          # Login, logout, refresh
├── oauth/         # Patreon callback
├── patreon/       # Profile, links, tier
├── points/        # Balance, history, top-ups
├── books/         # Support, rankings
├── admin/         # Dashboard, ledger, reversals, config
└── webhooks/      # Patreon events (POST only, no auth)
```

### Background Jobs
- **Patreon Rewards** (daily 12am UTC): Monthly point reconciliation
- **Leaderboards** (daily 1am UTC): Rankings aggregation
- **Point Decay** (weekly Mon 12am UTC): Expiration & decay application
- **Webhook Cleanup** (daily 2am UTC): Dedupe cache pruning

---

## Common Operations

### 1. Verify System Health

```bash
# Check database connection
psql -h localhost -U scrollnovels -d scrollnovels -c "SELECT version();"

# Check Redis connection
redis-cli ping

# Check current point balances
psql -d scrollnovels -c "SELECT user_id, total_points FROM user_points_balance ORDER BY total_points DESC LIMIT 5;"

# Check leaderboard status
psql -d scrollnovels -c "SELECT DISTINCT period FROM book_rankings LIMIT 1;"
```

### 2. Manual Patreon Reconciliation

If automatic job fails:

```bash
# Via admin API
curl -X POST http://localhost:3000/api/v1/admin/patreon-reconcile \
  -H "Authorization: Bearer <admin_token>" \
  -H "Content-Type: application/json"

# Or direct DB query
SELECT pl.user_id, COUNT(*) as links
FROM patreon_links pl
WHERE active = true AND (last_reward_date IS NULL OR last_reward_date < CURRENT_DATE - interval '1 day')
GROUP BY pl.user_id;
```

### 3. Regenerate Leaderboards

```bash
# Via admin UI: Dashboard > Leaderboards > Regenerate

# Or via API
curl -X POST http://localhost:3000/api/v1/admin/leaderboards/regenerate \
  -H "Authorization: Bearer <admin_token>"

# Or trigger job directly
node -e "
  const { leaderboardQueue } = require('./server/jobs/background-tasks');
  leaderboardQueue.add({}, { removeOnComplete: true });
"
```

### 4. Handle User Chargeback/Fraud

```bash
# Reverse all points and deactivate user
curl -X POST http://localhost:3000/api/v1/admin/fraud-reverse \
  -H "Authorization: Bearer <admin_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "12345",
    "reason": "Chargebank dispute - credit card fraud"
  }'

# Verify reversal in transaction log
psql -d scrollnovels -c "
  SELECT * FROM points_transactions 
  WHERE user_id = 12345 
  ORDER BY created_at DESC 
  LIMIT 5;
"
```

### 5. Refund Specific Support

```bash
# Via admin UI: Book Support > [Select Support] > Reverse

# Or via API
curl -X POST http://localhost:3000/api/v1/admin/book-support/9999/reverse \
  -H "Authorization: Bearer <admin_token>" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Duplicate purchase"}'
```

### 6. View Admin Audit Trail

```bash
# Recent admin actions
psql -d scrollnovels -c "
  SELECT created_at, admin_user_id, action_type, target_type, target_id
  FROM admin_actions
  ORDER BY created_at DESC
  LIMIT 20;
"

# Actions by admin
psql -d scrollnovels -c "
  SELECT admin_user_id, COUNT(*) as action_count
  FROM admin_actions
  WHERE created_at > NOW() - interval '7 days'
  GROUP BY admin_user_id;
"
```

### 7. Export Points Ledger

```bash
# Via admin UI: Points Ledger > Export CSV

# Or via direct SQL
psql -d scrollnovels -c "
  COPY (
    SELECT 
      pt.created_at, 
      u.username, 
      pt.type, 
      pt.delta, 
      pt.source,
      pt.metadata
    FROM points_transactions pt
    JOIN users u ON pt.user_id = u.id
    ORDER BY pt.created_at DESC
  ) TO STDOUT WITH CSV HEADER;
" > ledger.csv
```

---

## Troubleshooting

### Issue: Patreon Rewards Not Processing

**Symptoms**: Users not receiving monthly points, `last_reward_date` not updating

**Diagnosis**:
```bash
# Check background job queue
redis-cli -c "SELECT 0" -c "LLEN bull:patreon-rewards:active"

# Check job failures
redis-cli -c "SELECT 0" -c "LRANGE bull:patreon-rewards:failed 0 -1"

# Check logs
tail -f patreon-rewards.log | grep ERROR

# Verify token refresh
psql -d scrollnovels -c "
  SELECT user_id, patreon_user_id, access_token_expires
  FROM patreon_links 
  WHERE active = true 
  ORDER BY access_token_expires ASC 
  LIMIT 5;
"
```

**Solutions**:
1. If tokens expired: Refresh manually
   ```bash
   curl -X POST http://localhost:3000/api/v1/oauth/refresh \
     -H "Authorization: Bearer <user_token>"
   ```

2. If queue stuck: Restart job processor
   ```bash
   npm stop
   npm start
   ```

3. If Patreon API down: Check status at status.patreon.com
   - Queue will retry automatically (max 3 attempts)

4. If database transaction locked: Kill stale connections
   ```bash
   psql -d scrollnovels -c "
     SELECT pid, query, query_start
     FROM pg_stat_activity
     WHERE state = 'idle in transaction';
   "
   ```

### Issue: High Database Load / Slow Queries

**Symptoms**: Dashboard slow, timeouts, 503 errors

**Diagnosis**:
```bash
# Check slow queries
psql -d scrollnovels -c "
  SELECT query, calls, total_time, mean_time
  FROM pg_stat_statements
  ORDER BY mean_time DESC
  LIMIT 10;
"

# Check missing indexes
SELECT schemaname, tablename, indexname
FROM pg_indexes
WHERE tablename IN ('points_transactions', 'book_support', 'patreon_links');

# Check table bloat
psql -d scrollnovels -c "
  SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename))
  FROM pg_tables
  WHERE schemaname = 'public'
  ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
"
```

**Solutions**:
1. Add missing index:
   ```sql
   CREATE INDEX idx_points_transactions_user_created 
   ON points_transactions(user_id, created_at DESC);
   ```

2. Analyze table statistics:
   ```bash
   psql -d scrollnovels -c "ANALYZE;"
   ```

3. Vacuum to reclaim space:
   ```bash
   psql -d scrollnovels -c "VACUUM FULL ANALYZE;"
   # (WARNING: Locks tables - run during maintenance window)
   ```

4. Increase connection pool in `.env`:
   ```
   DB_MAX_POOL=20
   ```

### Issue: Webhook Events Not Processing

**Symptoms**: Patreon tier changes not reflecting, duplicate transactions

**Diagnosis**:
```bash
# Check webhook event queue
psql -d scrollnovels -c "
  SELECT event_id, processed, created_at
  FROM patreon_webhook_events
  ORDER BY created_at DESC
  LIMIT 20;
"

# Check for duplicate processing
psql -d scrollnovels -c "
  SELECT event_id, COUNT(*)
  FROM points_transactions
  WHERE metadata->>'event_id' IS NOT NULL
  GROUP BY event_id
  HAVING COUNT(*) > 1;
"

# Verify webhook signature checking
grep -i "signature" logs/*.log | tail -20
```

**Solutions**:
1. Mark problematic event as processed:
   ```sql
   UPDATE patreon_webhook_events 
   SET processed = true 
   WHERE event_id = 'problematic_id';
   ```

2. Manual transaction replay (be careful!):
   ```bash
   # Get raw event from Patreon API
   curl https://www.patreon.com/api/v2/campaigns/[campaign_id]/webhook_events?filter[direction]=desc \
     -H "Authorization: Bearer <client_token>"
   ```

3. Verify webhook secret is correct:
   ```bash
   # Should match value in .env
   echo $PATREON_WEBHOOK_SECRET
   # Compare with Patreon dashboard: Settings > Webhooks
   ```

### Issue: Memory Leak / Node Process Growing

**Symptoms**: Memory usage increases over time, eventual OOM crash

**Diagnosis**:
```bash
# Monitor process
watch -n 1 'ps aux | grep node'

# Check heap dumps
node --inspect=0.0.0.0:9229 server/index.js
# Connect via Chrome DevTools: chrome://inspect

# Check for leaking timers
grep -r "setInterval\|setTimeout" server/ | grep -v "clearInterval\|clearTimeout"
```

**Solutions**:
1. Ensure all DB connections released:
   ```javascript
   // Always call client.release() in finally block
   try { ... } finally { client.release(); }
   ```

2. Limit array growth in background jobs:
   ```javascript
   const errors = [];
   errors.push(...); // Bad: unbounded array
   
   // Better: log and discard
   console.error('Error:', err);
   ```

3. Enable garbage collection logging:
   ```bash
   node --trace-gc server/index.js 2>&1 | tee gc.log
   ```

### Issue: Admin Dashboard 401/403 Errors

**Symptoms**: Can't access admin panel, "Not authorized" messages

**Diagnosis**:
```bash
# Check token in localStorage
# Open DevTools Console: localStorage.getItem('token')

# Verify token validity
curl -H "Authorization: Bearer <token>" \
  http://localhost:3000/api/v1/admin/dashboard

# Check admin role in database
psql -d scrollnovels -c "
  SELECT id, username, role FROM users WHERE role = 'admin';
"

# Check token expiration
# JWT structure: [header].[payload].[signature]
# Decode payload (base64): console.log(atob('[payload]'))
```

**Solutions**:
1. Re-login to generate new token:
   - Clear localStorage: `localStorage.clear()`
   - Navigate to `/login`
   - Login with admin credentials

2. If token valid but still rejected:
   ```sql
   UPDATE users SET role = 'admin' WHERE id = your_user_id;
   ```

3. Check token secret in `.env`:
   ```bash
   echo $JWT_SECRET
   # Token won't decode if secret changed
   ```

---

## Performance Tuning

### Database Connection Pool
```javascript
// In server/db.js
const pool = new Pool({
  max: 20,              // Max connections
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
});
```

Increase `max` if seeing "Client pool is exhausted"

### API Response Caching

Add Redis caching for expensive queries:
```javascript
// Cache dashboard stats for 5 minutes
const cacheKey = 'dashboard:stats';
const cached = await redis.get(cacheKey);
if (cached) return JSON.parse(cached);

const stats = await computeStats();
await redis.setex(cacheKey, 300, JSON.stringify(stats));
return stats;
```

### Leaderboard Aggregation Optimization

Pre-compute rankings at 1am instead of on-demand:
```javascript
// Already done in background-tasks.js
// If still slow: break into book-specific jobs
patreonRewardQueue.add({ book_id: id }, { repeat: { cron: '0 1 * * *' } });
```

### Webhook Processing

Use queue workers for async processing:
```javascript
// Don't process webhooks synchronously in handler
// Already done: webhooks → queue → background job
```

---

## Maintenance Schedule

### Daily
- Monitor error logs
- Check Redis memory usage
- Verify webhook processing

### Weekly
- Review admin audit trail
- Check point decay job execution
- Backup database

### Monthly
- Update leaderboard multipliers if needed
- Review Patreon tier configuration
- Analyze query performance

### Quarterly
- Database maintenance (VACUUM, REINDEX)
- Security audit (token rotation, permissions)
- Backup restore test

---

## Disaster Recovery

### Database Backup

```bash
# Full backup
pg_dump -U scrollnovels scrollnovels > backup_$(date +%Y%m%d_%H%M%S).sql

# With compression
pg_dump -U scrollnovels scrollnovels | gzip > backup.sql.gz

# Restore from backup
psql -U scrollnovels scrollnovels < backup.sql
```

### Restore Points Transaction Ledger

If data corrupted but ledger intact:
```sql
-- Recalculate all balances from ledger
TRUNCATE user_points_balance CASCADE;

INSERT INTO user_points_balance (user_id, free_points, total_points)
SELECT 
  user_id,
  SUM(CASE WHEN type IN ('free_topup', 'bonus') THEN delta ELSE 0 END),
  SUM(delta)
FROM points_transactions
GROUP BY user_id
ON CONFLICT (user_id) DO UPDATE SET
  free_points = EXCLUDED.free_points,
  total_points = EXCLUDED.total_points;
```

### Rebuild Rankings

```bash
# If book_rankings corrupted
DELETE FROM book_rankings;

# Run regenerate job
node -e "
  const { leaderboardQueue } = require('./server/jobs/background-tasks');
  leaderboardQueue.add({}, { removeOnComplete: true });
"

# Or via API
curl -X POST http://localhost:3000/api/v1/admin/leaderboards/regenerate \
  -H "Authorization: Bearer <token>"
```

---

## Monitoring & Alerts

### Key Metrics to Watch

1. **Failed Background Jobs**: Check Redis queue failures
2. **Database Replication Lag**: If using replicas
3. **API Response Time**: Target <100ms for dashb
4. **Point Balance Discrepancies**: Ledger vs user_points_balance
5. **Webhook Processing Latency**: Should complete <5s

### Sample Alert Thresholds

- If failed jobs > 10 in 1 hour: Page on-call
- If database connection pool > 90%: Investigate & scale
- If API p95 response time > 500ms: Check slow queries
- If disk space < 10GB: Archive old webhook events

---

## Support Contacts

- Patreon API Issues: support@patreon.com
- Database Issues: PostgreSQL docs, Stack Overflow
- Redis Issues: redis-io/redis (GitHub)
- Node.js Issues: nodejs.org/issues

---

**Last Updated**: 2024
**Version**: 1.0
**Status**: Production Ready
