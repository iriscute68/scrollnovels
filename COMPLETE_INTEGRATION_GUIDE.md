# Complete System Integration Guide

## Overview

This document provides step-by-step integration of all components (database, APIs, background jobs, admin panel, security).

---

## Phase 1: Database Setup

### 1.1 Create PostgreSQL Database

```bash
# Connect as superuser
psql -U postgres

# In psql:
CREATE USER scrollnovels WITH PASSWORD 'your_secure_password';
CREATE DATABASE scrollnovels OWNER scrollnovels;
GRANT ALL PRIVILEGES ON DATABASE scrollnovels TO scrollnovels;

\connect scrollnovels
GRANT ALL PRIVILEGES ON SCHEMA public TO scrollnovels;

\q
```

### 1.2 Load Schema

```bash
# Copy schema file to server
psql -U scrollnovels -d scrollnovels -f postgres-schema.sql

# Verify tables created
psql -U scrollnovels -d scrollnovels -c "\dt"

# Expected output (13 tables):
# public | admin_actions
# public | admin_config
# public | book_rankings
# public | book_support
# public | books
# public | patreon_links
# public | patreon_tier_config
# public | patreon_webhook_events
# public | point_expiry_schedule
# public | points_transactions
# public | user_points_balance
# public | users
```

### 1.3 Verify Indexes

```bash
psql -U scrollnovels -d scrollnovels -c "
  SELECT tablename, indexname 
  FROM pg_indexes 
  WHERE schemaname = 'public' 
  ORDER BY tablename;
"

# Should see 20+ indexes for performance
```

---

## Phase 2: Environment Configuration

### 2.1 Create `.env` File

```bash
cd /path/to/project
cp .env.example .env
```

### 2.2 Configure Environment Variables

Edit `.env`:

```ini
# Server
NODE_ENV=production
PORT=3000
HOST=0.0.0.0

# Database
DB_HOST=localhost
DB_PORT=5432
DB_NAME=scrollnovels
DB_USER=scrollnovels
DB_PASSWORD=your_secure_password
DB_MAX_POOL=20

# Redis (for BullMQ)
REDIS_URL=redis://127.0.0.1:6379

# JWT
JWT_SECRET=your_very_long_random_secret_key_min_32_chars
JWT_EXPIRY=7d

# Patreon OAuth
PATREON_CLIENT_ID=your_client_id_from_patreon_dev
PATREON_CLIENT_SECRET=your_client_secret
PATREON_WEBHOOK_SECRET=your_webhook_secret_from_patreon
PATREON_REDIRECT_URI=http://localhost:3000/api/v1/oauth/patreon/callback

# Admin
ADMIN_USERNAME=admin
ADMIN_PASSWORD=your_secure_admin_password

# Feature Flags
ENABLE_PATREON=true
ENABLE_POINT_DECAY=true
ENABLE_BACKGROUND_JOBS=true

# Logging
LOG_LEVEL=info
LOG_FORMAT=json
```

---

## Phase 3: Node.js Server Setup

### 3.1 Install Dependencies

```bash
npm install

# Should install:
# - express
# - pg (PostgreSQL)
# - bull (job queue)
# - redis
# - jsonwebtoken
# - axios (HTTP client)
# - dotenv
```

### 3.2 Directory Structure

```
server/
├── index.js                    # Main entry point
├── db.js                       # Database pool
├── middleware/
│   └── auth.js                # JWT authentication
├── routes/
│   ├── admin.js              # Admin endpoints (215 lines)
│   ├── auth.js               # Login/refresh
│   ├── oauth.js              # Patreon OAuth
│   ├── patreon.js            # Patreon API
│   ├── points.js             # Points ledger
│   ├── books.js              # Book support
│   └── webhooks.js           # Patreon webhooks
├── jobs/
│   └── background-tasks.js   # BullMQ workers (patreon, leaderboards, decay)
├── utils/
│   └── webhook-security.js   # Signature verification & edge cases
└── logs/                      # Application logs

public/
├── admin.html                 # Admin dashboard UI (140 lines)
├── admin-dashboard.css        # Admin styling (420 lines)
└── admin-dashboard.js         # Admin interactivity (550 lines)
```

### 3.3 Create Main Server File (`server/index.js`)

```javascript
require('dotenv').config();
const express = require('express');
const { pool } = require('./db');
const authMiddleware = require('./middleware/auth');
const { scheduleRecurringJobs } = require('./jobs/background-tasks');

const app = express();

// Middleware
app.use(express.json({ limit: '10mb' }));
app.use(express.static('public'));

// Routes
app.use('/api/v1/auth', require('./routes/auth'));
app.use('/api/v1/oauth', require('./routes/oauth'));
app.use('/api/v1/patreon', authMiddleware, require('./routes/patreon'));
app.use('/api/v1/points', authMiddleware, require('./routes/points'));
app.use('/api/v1/books', authMiddleware, require('./routes/books'));
app.use('/api/v1/admin', authMiddleware, require('./routes/admin'));
app.use('/api/v1/webhooks', require('./routes/webhooks'));

// Admin UI
app.get('/admin', authMiddleware, (req, res) => {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ error: 'Unauthorized' });
  }
  res.sendFile(__dirname + '/../public/admin.html');
});

// Health check
app.get('/health', async (req, res) => {
  try {
    const result = await pool.query('SELECT NOW()');
    res.json({ status: 'ok', timestamp: result.rows[0].now });
  } catch (err) {
    res.status(500).json({ status: 'error', message: err.message });
  }
});

// Error handler
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(err.status || 500).json({
    error: err.message,
    status: err.status || 500
  });
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
  
  // Schedule background jobs
  if (process.env.ENABLE_BACKGROUND_JOBS === 'true') {
    scheduleRecurringJobs();
    console.log('Background jobs scheduled');
  }
});

module.exports = app;
```

### 3.4 Create Database Connection Pool (`server/db.js`)

```javascript
const { Pool } = require('pg');

const pool = new Pool({
  host: process.env.DB_HOST,
  port: process.env.DB_PORT,
  database: process.env.DB_NAME,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  max: parseInt(process.env.DB_MAX_POOL || 20),
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
});

pool.on('error', (err) => {
  console.error('Unexpected error on idle client', err);
  process.exit(-1);
});

module.exports = { pool };
```

---

## Phase 4: Security & Authentication

### 4.1 Create JWT Middleware (`server/middleware/auth.js`)

```javascript
const jwt = require('jsonwebtoken');

const authMiddleware = (req, res, next) => {
  const token = req.headers.authorization?.split(' ')[1];

  if (!token) {
    return res.status(401).json({ error: 'No token provided' });
  }

  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded;
    next();
  } catch (err) {
    res.status(401).json({ error: 'Invalid or expired token' });
  }
};

module.exports = authMiddleware;
```

### 4.2 Generate Initial Admin Token

```bash
node -e "
const jwt = require('jsonwebtoken');
const token = jwt.sign(
  { user_id: 1, username: 'admin', role: 'admin' },
  process.env.JWT_SECRET,
  { expiresIn: '7d' }
);
console.log('Admin Token:', token);
"

# Store this token for initial admin access
```

---

## Phase 5: Background Jobs Integration

### 5.1 Verify Redis Running

```bash
# Start Redis (if not already running)
redis-server

# In another terminal, test connection
redis-cli ping
# Should respond: PONG
```

### 5.2 Initialize Background Jobs

```bash
# The jobs are automatically scheduled on server startup
# Verify by checking logs:
npm start 2>&1 | grep "Background jobs scheduled"

# To manually trigger a job:
node -e "
  const { leaderboardQueue } = require('./server/jobs/background-tasks');
  leaderboardQueue.add({}, { removeOnComplete: true }).then(job => {
    console.log('Job added:', job.id);
  });
"
```

### 5.3 Monitor Job Queue

```bash
# In another terminal, watch Redis queue
redis-cli

# Inside redis-cli:
> MONITOR

# In yet another terminal, check specific queue:
redis-cli LRANGE bull:patreon-rewards:active 0 -1
redis-cli LRANGE bull:leaderboards:completed 0 -1
```

---

## Phase 6: Admin Dashboard Integration

### 6.1 Copy Frontend Files

```bash
cp admin-dashboard.html public/admin.html
cp css-admin-dashboard.css public/admin-dashboard.css
cp js-admin-dashboard.js public/admin-dashboard.js
```

### 6.2 Verify File Linking

In `public/admin.html`, verify links:
```html
<link rel="stylesheet" href="/admin-dashboard.css">
<script src="/admin-dashboard.js"></script>
```

### 6.3 Test Admin Access

```bash
# Get admin token (from Phase 4.2)
TOKEN="eyJhbGc..."

# Test admin endpoint
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:3000/api/v1/admin/dashboard

# Should return dashboard stats
```

---

## Phase 7: Webhook Integration with Patreon

### 7.1 Configure Patreon Webhooks

1. Go to https://www.patreon.com/developers
2. Select your campaign
3. Go to Webhooks
4. Set endpoint URL: `https://yoursite.com/api/v1/webhooks/patreon`
5. Copy webhook secret to `.env` as `PATREON_WEBHOOK_SECRET`
6. Select events:
   - members:pledge:create
   - members:pledge:update
   - members:pledge:delete

### 7.2 Test Webhook Signature Verification

```bash
# Create test webhook
const crypto = require('crypto');
const secret = 'your_webhook_secret';
const body = JSON.stringify({ type: 'members:pledge:create', data: {} });
const signature = crypto.createHmac('md5', secret).update(body).digest('hex');

# Send test request
curl -X POST http://localhost:3000/api/v1/webhooks/patreon \
  -H "X-Patreon-Signature: $signature" \
  -H "Content-Type: application/json" \
  -d "$body"
```

---

## Phase 8: Testing & Validation

### 8.1 Database Integrity

```bash
# Check for data inconsistencies
psql -d scrollnovels -c "
  -- Verify ledger matches balances
  SELECT 
    u.id,
    upb.total_points,
    SUM(pt.delta) as ledger_total
  FROM users u
  LEFT JOIN user_points_balance upb ON u.id = upb.user_id
  LEFT JOIN points_transactions pt ON u.id = pt.user_id
  GROUP BY u.id, upb.total_points
  HAVING upb.total_points != SUM(pt.delta)
  LIMIT 10;
"
```

### 8.2 API Endpoint Testing

```bash
# Login
curl -X POST http://localhost:3000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"password"}'

# Get points balance
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:3000/api/v1/points/balance

# Get book rankings
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:3000/api/v1/books/rankings?period=monthly
```

### 8.3 Background Job Testing

```bash
# Manually trigger Patreon reconciliation
curl -X POST http://localhost:3000/api/v1/admin/patreon-reconcile \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Check job status in Redis
redis-cli LRANGE bull:patreon-rewards:completed 0 -1
```

---

## Phase 9: Production Deployment

### 9.1 Pre-Deployment Checklist

- [ ] `.env` configured with production credentials
- [ ] Database backed up
- [ ] All 13 tables created and verified
- [ ] All 20+ indexes created
- [ ] JWT_SECRET is strong (32+ characters)
- [ ] PATREON_WEBHOOK_SECRET configured
- [ ] SSL certificate installed
- [ ] Redis running and accessible
- [ ] Admin user created and tested

### 9.2 Deploy to Production

```bash
# Using PM2 (recommended for Node.js)
npm install -g pm2

# Start server with PM2
pm2 start server/index.js --name scrollnovels

# Auto-restart on reboot
pm2 startup
pm2 save

# Monitor in real-time
pm2 monit

# View logs
pm2 logs scrollnovels
```

### 9.3 Configure Nginx Reverse Proxy

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /health {
        access_log off;
        proxy_pass http://localhost:3000/health;
    }
}

server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

### 9.4 Configure Database Backups

```bash
# Daily backup cron job
0 2 * * * pg_dump -U scrollnovels scrollnovels | gzip > /backups/scrollnovels_$(date +\%Y\%m\%d).sql.gz

# Keep 30 days of backups
find /backups -name "scrollnovels_*.sql.gz" -mtime +30 -delete
```

---

## Phase 10: Monitoring & Operations

### 10.1 Set Up Logging

```javascript
// In server/index.js
const fs = require('fs');
const logStream = fs.createWriteStream('logs/app.log', { flags: 'a' });

console.log = (msg) => {
  const timestamp = new Date().toISOString();
  logStream.write(`${timestamp} [INFO] ${msg}\n`);
};

console.error = (msg) => {
  const timestamp = new Date().toISOString();
  logStream.write(`${timestamp} [ERROR] ${msg}\n`);
};
```

### 10.2 Key Endpoints for Monitoring

```bash
# Server health
curl http://localhost:3000/health

# Admin dashboard stats
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:3000/api/v1/admin/dashboard

# Database status
psql -d scrollnovels -c "
  SELECT 
    datname,
    numbackends,
    pg_database_size(datname) / 1024 / 1024 AS size_mb
  FROM pg_stat_database
  WHERE datname = 'scrollnovels';
"
```

---

## Integration Flowchart

```
User Login
    ↓
JWT Token Generated
    ↓
User Accesses Points/Rankings
    ↓
API retrieves from user_points_balance (denormalized)
API logs in points_transactions (ledger)
    ↓
Background Job: Patreon Reconciliation (daily 12am)
    ↓
Verify Patreon Status → Grant Monthly Points
    ↓
Background Job: Leaderboard Aggregation (daily 1am)
    ↓
Pre-compute Rankings → Store in book_rankings
    ↓
Background Job: Point Decay (weekly Mon 12am)
    ↓
Apply Weekly Decay → Expire Old Points
    ↓
Admin Dashboard
    ↓
View Stats → Manage Patreon → Reverse Support
```

---

## Troubleshooting Integration Issues

### Issue: "Cannot find module 'pg'"
**Solution**: `npm install pg`

### Issue: "Redis connection refused"
**Solution**: Start Redis or update `REDIS_URL` in `.env`

### Issue: "JWT verification failed"
**Solution**: Ensure `JWT_SECRET` is consistent and tokens are fresh

### Issue: "Admin endpoints return 403"
**Solution**: Verify user has `role = 'admin'` in database

---

## Support & Resources

- PostgreSQL Docs: https://www.postgresql.org/docs/
- Express.js Guide: https://expressjs.com/
- BullMQ Docs: https://docs.bullmq.io/
- JWT Handbook: https://tools.ietf.org/html/rfc7519
- Patreon API: https://docs.patreon.com

---

**Ready for Production**: Yes ✅
**Last Updated**: 2024
**Version**: 1.0.0
