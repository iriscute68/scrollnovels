# Deployment & Operations Guide

## Deployment Checklist

### Pre-Deployment

- [ ] All environment variables configured in `.env`
- [ ] JWT_SECRET generated securely (`openssl rand -hex 32`)
- [ ] Database migrated and seeded
- [ ] Patreon OAuth credentials obtained
- [ ] Patreon webhook secret configured
- [ ] SSL/TLS certificate installed (production)
- [ ] Backup strategy in place

### Production Environment Variables

```bash
# Database
DB_USER=prod_user
DB_PASSWORD=$(openssl rand -base64 32)
DB_HOST=db.yourdomain.com
DB_PORT=5432
DB_NAME=scroll_novels_prod

# Server
NODE_ENV=production
PORT=3000
FRONTEND_URL=https://yourdomain.com

# Security
JWT_SECRET=$(openssl rand -hex 32)
SESSION_SECRET=$(openssl rand -hex 32)

# Patreon (from dashboard)
PATREON_CLIENT_ID=your_client_id
PATREON_CLIENT_SECRET=your_client_secret
PATREON_WEBHOOK_SECRET=your_webhook_secret
PATREON_REDIRECT_URI=https://yourdomain.com/api/v1/oauth/patreon/callback
```

## Database Backup Strategy

### Daily Backup Script

Create `scripts/backup.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/scroll_novels"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/scroll_novels_$DATE.sql.gz"

# Create backup directory if not exists
mkdir -p $BACKUP_DIR

# Backup database
pg_dump scroll_novels | gzip > $BACKUP_FILE

# Keep only last 30 backups
find $BACKUP_DIR -name "scroll_novels_*.sql.gz" -type f -mtime +30 -delete

echo "Backup completed: $BACKUP_FILE"
```

Schedule with cron:
```bash
0 2 * * * /var/www/scroll-novels/scripts/backup.sh
```

### Point Decay Audit Trail

All point decay operations are logged in:
- Table: `points_transactions` (type: 'decayed', 'expired')
- Metadata includes: original points, decay percentage, week number

Query point activity:
```sql
SELECT user_id, delta, type, source, created_at 
FROM points_transactions 
WHERE user_id = 'user_uuid' 
ORDER BY created_at DESC;
```

## Monitoring

### Health Check Endpoint

```bash
curl https://yourdomain.com/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Key Metrics to Monitor

1. **Database Connection Pool**
   ```sql
   SELECT count(*) FROM pg_stat_activity;
   ```
   - Alert if > 15 connections

2. **Point Decay Job**
   - Check logs for "Point decay processed"
   - Alert if job fails on Monday

3. **Patreon Webhooks**
   ```sql
   SELECT COUNT(*), processed 
   FROM patreon_webhook_events 
   WHERE DATE(created_at) = CURRENT_DATE
   GROUP BY processed;
   ```

4. **Rankings Aggregation**
   ```sql
   SELECT COUNT(*) 
   FROM book_rankings 
   WHERE day = CURRENT_DATE;
   ```
   - Should have ~50-100+ entries per period

### Logging

Configure logging in production:

```javascript
// server/logger.js
const winston = require('winston');

const logger = winston.createLogger({
  level: process.env.LOG_LEVEL || 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.File({ filename: 'logs/error.log', level: 'error' }),
    new winston.transports.File({ filename: 'logs/combined.log' })
  ]
});

module.exports = logger;
```

## Troubleshooting Production Issues

### Database Deadlock

If rankings aggregation causes deadlock:

```sql
-- Kill conflicting queries
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE query LIKE '%book_rankings%';

-- Modify aggregation to use more granular locks
```

### Patreon Webhook Failures

Check webhook event logs:

```sql
SELECT event_type, error_message, created_at
FROM patreon_webhook_events
WHERE processed = false
ORDER BY created_at DESC;
```

Manual retry:
```sql
-- Mark for reprocessing
UPDATE patreon_webhook_events 
SET processed = false 
WHERE id = 'event_uuid';
```

### Point Balance Inconsistency

Audit trailing shows all point movements:

```sql
-- Total points earned
SELECT SUM(delta) as earned 
FROM points_transactions 
WHERE user_id = 'user_uuid' AND delta > 0;

-- Total points spent
SELECT SUM(delta) as spent 
FROM points_transactions 
WHERE user_id = 'user_uuid' AND delta < 0;

-- Current balance
SELECT total_points 
FROM user_points_balance 
WHERE user_id = 'user_uuid';
```

If mismatch exists:
```sql
-- Recalculate balance
WITH point_sum AS (
  SELECT user_id, SUM(delta) as calculated_total
  FROM points_transactions
  GROUP BY user_id
)
SELECT u.user_id, upb.total_points, ps.calculated_total
FROM user_points_balance upb
JOIN point_sum ps ON upb.user_id = ps.user_id
WHERE upb.total_points != ps.calculated_total;
```

## Performance Optimization

### Database Indexes

All critical indexes are already created. Monitor index usage:

```sql
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
ORDER BY idx_scan DESC;
```

### Query Optimization

Check slow queries in PostgreSQL logs:

```
log_min_duration_statement = 1000  # Log queries > 1 second
```

Analyze slow ranking queries:

```sql
EXPLAIN ANALYZE
SELECT br.rank_position, b.title, br.total_support_points
FROM book_rankings br
JOIN books b ON br.book_id = b.id
WHERE br.period = 'weekly' AND br.day = CURRENT_DATE
ORDER BY br.rank_position;
```

### Caching Strategy

Implement Redis caching for:
1. User points balance (cache 1 hour)
2. Rankings (cache 12 hours)
3. Guide pages (cache 24 hours)

```javascript
// Example with redis
const redis = require('redis');
const client = redis.createClient();

// Get rankings with cache
async function getRankingsCached(period, limit) {
  const cacheKey = `rankings:${period}`;
  const cached = await client.get(cacheKey);
  
  if (cached) return JSON.parse(cached);
  
  const data = await getRankings(period, limit);
  await client.setex(cacheKey, 3600, JSON.stringify(data)); // 1 hour
  
  return data;
}
```

## Security Hardening

### Input Validation

All endpoints validate input. Example:

```javascript
// Validate book support request
if (!Number.isInteger(points) || points < 1 || points > 100000) {
  return res.status(400).json({ error: 'Invalid points amount' });
}
```

### Rate Limiting

Implement rate limiting:

```javascript
const rateLimit = require('express-rate-limit');

const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100 // limit each IP to 100 requests per windowMs
});

app.use('/api/', limiter);
```

### SQL Injection Prevention

All queries use parameterized statements:
```javascript
// Safe - uses $1 parameter
await pool.query('SELECT * FROM users WHERE id = $1', [userId]);
```

### CORS Configuration

Restrict to trusted origins:
```javascript
app.use(cors({
  origin: ['https://yourdomain.com', 'https://www.yourdomain.com'],
  credentials: true
}));
```

## Scaling Strategy

### Horizontal Scaling

1. **API Servers**
   - Run multiple Node instances behind load balancer (nginx, HAProxy)
   - Use shared database for consistency
   - Session store in Redis

2. **Database**
   - Read replicas for GET endpoints
   - Primary for writes (transactions)
   - Connection pooling with PgBouncer

3. **Jobs**
   - Run scheduled jobs on dedicated instance
   - Use PostgreSQL advisory locks to prevent parallel execution

### Vertical Scaling

Increase resources if needed:
- Node memory: Increase if event loop lag detected
- DB memory: Increase shared_buffers for larger caches
- CPU: Profile hot code paths with flame graphs

## Disaster Recovery

### Point System Recovery

If user points are lost:

```sql
-- Restore from points_transactions ledger
INSERT INTO user_points_balance (user_id, total_points)
SELECT user_id, SUM(delta)
FROM points_transactions
GROUP BY user_id
ON CONFLICT (user_id) DO UPDATE SET
  total_points = excluded.total_points;
```

### Database Restore

```bash
# Restore from backup
gunzip -c /var/backups/scroll_novels/scroll_novels_20240115_020000.sql.gz | \
  psql scroll_novels
```

## Maintenance Windows

### Scheduled Maintenance

Perform during low-traffic hours (e.g., 2-4 AM UTC):

1. **Database Maintenance**
   ```sql
   VACUUM FULL ANALYZE;
   REINDEX DATABASE scroll_novels;
   ```

2. **Log Rotation**
   ```bash
   logrotate -f /etc/logrotate.d/scroll-novels
   ```

3. **Backup Verification**
   ```bash
   gunzip -t /var/backups/scroll_novels/latest_backup.sql.gz
   ```

### Deployment Updates

```bash
# Zero-downtime deployment
1. Build new version
2. Run database migrations
3. Switch traffic to new version
4. Monitor for errors (15 minutes)
5. If errors: rollback to previous version
```

## Contact & Support

- **Issues**: Check logs in `/var/log/scroll-novels/`
- **Webhooks**: Patreon dashboard webhook event history
- **Database**: PostgreSQL server logs in `/var/log/postgresql/`
