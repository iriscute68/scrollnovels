# Scroll Novels Point System API

Complete production-ready point system with Patreon integration and admin-managed guides.

## 🚀 Quick Start (5 minutes)

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
```bash
cp .env.example .env
# Edit .env with your PostgreSQL and Patreon credentials
```

### 3. Setup Database
```bash
# Create database
createdb scroll_novels

# Load schema
psql scroll_novels < postgres-schema.sql

# Or use migration script
npm run migrate
```

### 4. Start Server
```bash
npm start
# Server running on http://localhost:3000
```

### 5. Verify Installation
```bash
curl http://localhost:3000/health
```

## 📚 Documentation

| Document | Purpose |
|----------|---------|
| `API_IMPLEMENTATION_GUIDE.md` | Complete API reference with all endpoints |
| `FRONTEND_INTEGRATION_GUIDE.md` | React components & integration examples |
| `DEPLOYMENT_OPERATIONS_GUIDE.md` | Production setup & monitoring |
| `COMPLETE_IMPLEMENTATION_SUMMARY.md` | System overview & architecture |

## ✨ Features

### Point System
- ✅ Free/Premium/Patreon points with 1x/2x/3x multipliers
- ✅ Immutable transaction ledger (audit trail)
- ✅ Automatic point decay (20% per week, expires after 4 weeks)
- ✅ Support tracking with multiplier history

### Patreon Integration
- ✅ OAuth 2.0 account linking
- ✅ Automatic monthly reward processing
- ✅ Webhook handling with signature verification
- ✅ Tier-based point rewards
- ✅ Token refresh management

### Leaderboards
- ✅ Pre-aggregated rankings (Daily/Weekly/Monthly/All-Time)
- ✅ O(1) ranking queries
- ✅ Supporter counts and total points
- ✅ Author attribution

### Admin Guides
- ✅ Multi-section guide pages
- ✅ Image galleries with captions
- ✅ Markdown content support
- ✅ Publish/unpublish workflow
- ✅ Created/updated audit trail

## 📊 Database Schema

13 optimized PostgreSQL tables:
- users, books
- points_transactions (LEDGER), user_points_balance (DENORMALIZED)
- book_support, point_expiry_schedule
- patreon_links, patreon_tier_config, patreon_webhook_events
- book_rankings
- guide_pages, guide_sections, guide_images

All with 20+ strategic indexes for optimal performance.

## 🔌 API Endpoints

### Points
```
GET    /api/v1/me/points              - Get balance
GET    /api/v1/me/points/transactions - History
POST   /api/v1/books/:id/support      - Support book
GET    /api/v1/books/:id/supports     - Top supporters
GET    /api/v1/rankings               - Leaderboards
```

### Patreon OAuth
```
GET    /api/v1/oauth/patreon/url      - Get auth URL
POST   /api/v1/oauth/patreon/callback - Handle callback
GET    /api/v1/me/patreon             - Check link
DELETE /api/v1/me/patreon             - Unlink
```

### Guides
```
GET    /api/v1/guides                 - List all
GET    /api/v1/guides/:slug           - Get full guide
POST   /api/v1/admin/guides           - Create (ADMIN)
PUT    /api/v1/admin/guides/:id       - Update (ADMIN)
DELETE /api/v1/admin/guides/:id       - Delete (ADMIN)
POST   /api/v1/admin/guides/:id/sections - Add section
POST   /api/v1/admin/guides/:id/images   - Upload image
```

## 🔐 Authentication

- JWT tokens (7-day expiration)
- Bearer token format: `Authorization: Bearer <token>`
- Role-based access (user/author/admin)
- Admin-only endpoints protected with role check

## ⚙️ Configuration

### Required Environment Variables
```
DB_USER=postgres
DB_PASSWORD=your_password
DB_HOST=localhost
DB_PORT=5432
DB_NAME=scroll_novels

JWT_SECRET=your_jwt_secret
SESSION_SECRET=your_session_secret

PATREON_CLIENT_ID=your_client_id
PATREON_CLIENT_SECRET=your_client_secret
PATREON_WEBHOOK_SECRET=your_webhook_secret
PATREON_REDIRECT_URI=http://localhost:3000/api/v1/oauth/patreon/callback
```

## 📅 Scheduled Jobs

| Job | Schedule | Action |
|-----|----------|--------|
| Patreon Rewards | 12:00 AM UTC daily | Award monthly points to active patrons |
| Point Decay | 12:00 AM UTC Monday | Apply 20% weekly decay, expire after 4 weeks |
| Rankings Aggregation | 1:00 AM UTC daily | Pre-calculate all leaderboard periods |

## 🧪 Testing

### Manual Testing
```bash
# Get health status
curl http://localhost:3000/health

# Get rankings
curl http://localhost:3000/api/v1/rankings?period=weekly

# Get guide
curl http://localhost:3000/api/v1/guides/how-points-work
```

### Automated Tests
```bash
npm test
```

### Seed Test Data
```bash
npm run seed
# Creates test users with 1500 points each
```

## 📈 Performance

- **Balance Reads**: O(1) using denormalized user_points_balance
- **Ranking Queries**: O(1) using pre-aggregated book_rankings
- **Transaction Lookups**: O(log N) with indexed (user_id, created_at)
- **Connection Pooling**: 20 simultaneous connections
- **Query Response**: <100ms for most endpoints

## 🔍 Monitoring

### Health Check
```bash
curl http://localhost:3000/health
```

### Key Metrics
```sql
-- Database connections
SELECT count(*) FROM pg_stat_activity;

-- Webhook processing status
SELECT COUNT(*), processed FROM patreon_webhook_events 
WHERE DATE(created_at) = CURRENT_DATE GROUP BY processed;

-- Daily rankings count
SELECT COUNT(*) FROM book_rankings WHERE day = CURRENT_DATE;
```

## 🚨 Troubleshooting

### Database Connection Failed
- Verify PostgreSQL is running
- Check DB_* environment variables
- Test connection: `psql -U postgres`

### Invalid Patreon Signature
- Verify PATREON_WEBHOOK_SECRET matches
- Check webhook URL is publicly accessible
- Review Patreon dashboard webhook logs

### Points Not Updating
- Verify user_points_balance row exists
- Check points_transactions for all movements
- Review database audit trail

## 📦 Dependencies

Core packages:
- `express` - Web framework
- `pg` - PostgreSQL driver
- `axios` - HTTP client (Patreon API)
- `jsonwebtoken` - JWT auth
- `multer` - File uploads
- `node-cron` - Job scheduling
- `cors` - CORS middleware

Development:
- `nodemon` - Auto-reload
- `jest` - Testing
- `supertest` - HTTP testing

## 🛠️ Development

### Start Development Server
```bash
npm run dev
```

### Database Backup
```bash
pg_dump scroll_novels | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Database Restore
```bash
gunzip -c backup_20240115.sql.gz | psql scroll_novels
```

## 📝 Frontend Integration

See `FRONTEND_INTEGRATION_GUIDE.md` for:
- React hook examples (usePointsBalance)
- Component examples (PointsDisplay, SupportModal, Rankings)
- API client setup
- Styling examples

## 🌐 Deployment

See `DEPLOYMENT_OPERATIONS_GUIDE.md` for:
- Production environment setup
- Database backup strategy
- Monitoring configuration
- Performance optimization
- Security hardening
- Scaling strategies

## 📋 Pre-populated Data

**Patreon Tiers:**
- Bronze: 500 points/month (1x)
- Silver: 1200 points/month (2x)
- Gold: 3000 points/month (3x)
- Diamond: 10000 points/month (3x)

**Guide Pages:**
- how-points-work
- supporting-books
- patreon-integration
- rankings-system

## 🔄 Update & Upgrade

### Database Migrations
```bash
npm run migrate
```

### Backup Before Upgrade
```bash
pg_dump scroll_novels | gzip > backup_pre_upgrade.sql.gz
```

## 📞 Support

For issues:
1. Check relevant documentation file
2. Review database audit tables (points_transactions, patreon_webhook_events)
3. Check server logs
4. Verify environment variables
5. Test with curl

## 📄 License

MIT

## 🎯 Next Steps

1. **Setup**: Follow Quick Start above
2. **Configure**: Set Patreon OAuth credentials
3. **Integrate**: Use React components from FRONTEND_INTEGRATION_GUIDE.md
4. **Deploy**: Follow DEPLOYMENT_OPERATIONS_GUIDE.md
5. **Manage**: Use admin guides to create educational content

---

**Version**: 1.0.0 | **Status**: Production Ready | **Node**: 16+ | **PostgreSQL**: 13+
