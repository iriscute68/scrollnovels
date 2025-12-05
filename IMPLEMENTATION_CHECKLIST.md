# Implementation Checklist

Complete checklist for implementing the Point System API.

## Phase 1: Setup & Database (Day 1)

### Environment Setup
- [ ] Node.js 16+ installed
- [ ] PostgreSQL 13+ installed and running
- [ ] Git repository initialized
- [ ] `.env` file created from `.env.example`
- [ ] `node_modules/` installed (`npm install`)

### Database
- [ ] Database created: `createdb scroll_novels`
- [ ] Schema loaded: `psql scroll_novels < postgres-schema.sql`
- [ ] All 13 tables verified to exist
- [ ] Indexes created and optimized
- [ ] Pre-populated tiers and guides verified

### Configuration
- [ ] `DB_USER` and `DB_PASSWORD` configured
- [ ] `DB_HOST` and `DB_PORT` correct
- [ ] `JWT_SECRET` generated: `openssl rand -hex 32`
- [ ] `SESSION_SECRET` generated: `openssl rand -hex 32`
- [ ] `NODE_ENV` set to 'development'
- [ ] `PORT` set to 3000 or preferred port

## Phase 2: Backend API (Day 2-3)

### Server Startup
- [ ] `npm start` runs without errors
- [ ] Health endpoint works: `curl http://localhost:3000/health`
- [ ] Database connection verified in logs
- [ ] No port conflicts

### Points API (routes/points.js)
- [ ] GET `/api/v1/me/points` returns balance
- [ ] GET `/api/v1/me/points/transactions` returns history with pagination
- [ ] POST `/api/v1/books/:id/support` spends points correctly
  - [ ] Validates point_type (free, premium, patreon)
  - [ ] Updates user_points_balance
  - [ ] Creates points_transactions record
  - [ ] Returns effective_points with multiplier
- [ ] GET `/api/v1/books/:id/supports` returns top supporters
- [ ] GET `/api/v1/rankings` returns leaderboards for all periods
- [ ] POST `/api/v1/admin/users/:id/adjust-points` works (admin only)
  - [ ] Admin role verification
  - [ ] Manual point adjustment
  - [ ] Audit logging

### Patreon OAuth (routes/oauth.js)
- [ ] GET `/api/v1/oauth/patreon/url` returns authorization URL
- [ ] POST `/api/v1/oauth/patreon/callback` exchanges code for token
  - [ ] Fetches user identity from Patreon
  - [ ] Detects tier and patron status
  - [ ] Creates patreon_links record
  - [ ] Awards initial points if active patron
- [ ] GET `/api/v1/me/patreon` returns link status
- [ ] DELETE `/api/v1/me/patreon` unlinks account

### Webhook Handler (webhooks/patreon.js)
- [ ] POST `/webhooks/patreon` validates HMAC signature
- [ ] Idempotency key prevents duplicates
- [ ] pledges:create event awards points
- [ ] pledges:update event updates tier
- [ ] pledges:delete event deactivates link
- [ ] Error handling and logging functional

### Guide Management (routes/guides.js)
- [ ] GET `/api/v1/guides` lists published guides (public)
- [ ] GET `/api/v1/guides/:slug` returns full guide with sections
- [ ] GET `/api/v1/admin/guides` returns all guides (admin)
- [ ] POST `/api/v1/admin/guides` creates guide (admin)
- [ ] PUT `/api/v1/admin/guides/:id` updates guide (admin)
- [ ] DELETE `/api/v1/admin/guides/:id` deletes guide (admin)
- [ ] POST `/api/v1/admin/guides/:id/sections` adds section (admin)
- [ ] PUT/DELETE sections work (admin)
- [ ] POST `/api/v1/admin/guides/:id/images` uploads images (admin)
- [ ] PUT/DELETE images work (admin)
- [ ] POST `/api/v1/admin/guides/:id/publish` publishes guide (admin)

### Scheduled Jobs
- [ ] Patreon rewards job scheduled for 12:00 AM UTC
  - [ ] Test manual trigger: `npm run rewards`
  - [ ] Verifies no duplicate monthly awards
  - [ ] Awards correct points based on tier
- [ ] Point decay job scheduled for Monday 12:00 AM
  - [ ] Test manual trigger: `npm run decay`
  - [ ] Applies 20% weekly decay correctly
  - [ ] Expires points after 4 weeks
- [ ] Rankings aggregation scheduled for 1:00 AM UTC
  - [ ] Test manual trigger: `npm run rankings`
  - [ ] Pre-calculates all periods
  - [ ] Indexes records correctly

## Phase 3: Frontend Integration (Day 4)

### API Client Setup
- [ ] API_BASE_URL configured in frontend
- [ ] apiClient methods created for each endpoint
- [ ] Error handling implemented
- [ ] Token refresh logic (if needed)

### React Hooks
- [ ] `usePointsBalance()` hook working
  - [ ] Fetches balance on mount
  - [ ] Updates when token changes
  - [ ] Handles loading/error states
- [ ] `usePointsTransactions()` hook for history

### Components
- [ ] `PointsDisplay` component renders correctly
- [ ] `SupportModal` component functional
  - [ ] Shows point type selector
  - [ ] Displays multiplier info
  - [ ] Validates input
  - [ ] Handles submission
- [ ] `PatreonConnect` component working
  - [ ] Links and unlinks accounts
  - [ ] Shows tier information
  - [ ] Displays next reward date
- [ ] `Rankings` component renders leaderboards
  - [ ] Period selector (daily/weekly/monthly/all_time)
  - [ ] Shows rank, book, author, points
- [ ] `GuideViewer` component displays guides
  - [ ] Renders markdown content
  - [ ] Shows sections
  - [ ] Displays images with captions

### Styling
- [ ] All components styled appropriately
- [ ] Mobile responsive design
- [ ] Consistent color scheme
- [ ] Accessibility features (alt text, labels)

## Phase 4: Testing & Validation (Day 5)

### Manual API Testing
- [ ] Test all endpoints with curl
- [ ] Verify request/response formats
- [ ] Check error messages
- [ ] Test pagination on transactions

### Point System Testing
- [ ] Support book with free points
- [ ] Support book with premium points
- [ ] Support book with patreon points
- [ ] Verify multipliers applied correctly
- [ ] Check balance updates
- [ ] Review transaction ledger

### Patreon Integration Testing
- [ ] OAuth flow completes successfully
- [ ] Points awarded on first link
- [ ] Webhook signature verification working
- [ ] Manual tier changes processed
- [ ] Token refresh tested

### Point Decay Testing
- [ ] Create test point_expiry_schedule records
- [ ] Run decay job manually
- [ ] Verify 20% decay applied
- [ ] Verify expiration after 4 weeks
- [ ] Check balance updates
- [ ] Verify transaction logging

### Rankings Testing
- [ ] Create multiple support records
- [ ] Run aggregation job
- [ ] Check book_rankings populated
- [ ] Verify ranking queries fast
- [ ] Test different periods

### Guide Testing
- [ ] Create admin guide
- [ ] Add sections
- [ ] Upload images
- [ ] Publish guide
- [ ] View as public user
- [ ] Edit and republish
- [ ] Unpublish and hide

### Admin Features Testing
- [ ] Admin role authorization working
- [ ] Admin-only endpoints blocked for regular users
- [ ] Manual point adjustments logged
- [ ] Guide management accessible to admins only

## Phase 5: Deployment Preparation (Day 6)

### Security
- [ ] CORS properly configured
- [ ] Sensitive data not logged
- [ ] SQL injection prevented (parameterized queries)
- [ ] JWT secrets not exposed
- [ ] HTTPS enforced in production

### Performance
- [ ] Connection pooling configured
- [ ] All indexes created and used
- [ ] Query times < 100ms verified
- [ ] Database connections monitored

### Backup & Recovery
- [ ] Backup script created
- [ ] Test backup/restore procedure
- [ ] Document recovery steps
- [ ] Schedule daily backups

### Monitoring & Logging
- [ ] Error logging configured
- [ ] Key metrics tracked
- [ ] Health check endpoint working
- [ ] Alerts set up for critical errors

### Documentation
- [ ] API_IMPLEMENTATION_GUIDE.md complete
- [ ] FRONTEND_INTEGRATION_GUIDE.md complete
- [ ] DEPLOYMENT_OPERATIONS_GUIDE.md complete
- [ ] README_API.md complete
- [ ] Environment variables documented
- [ ] Troubleshooting guide complete

## Phase 6: Production Deployment (Day 7)

### Pre-Deployment
- [ ] Code reviewed
- [ ] All tests passing
- [ ] Staging environment tested
- [ ] Database backup created
- [ ] Rollback plan documented

### Production Environment
- [ ] Server provisioned
- [ ] PostgreSQL configured
- [ ] SSL/TLS certificates installed
- [ ] Environment variables configured
- [ ] Database created and migrated
- [ ] Backups configured

### Deployment
- [ ] Code deployed
- [ ] Environment variables verified
- [ ] Health check passing
- [ ] All endpoints tested
- [ ] Patreon webhook configured
- [ ] Cron jobs running

### Post-Deployment Verification
- [ ] All API endpoints responding
- [ ] Database connections healthy
- [ ] Webhook events processing
- [ ] Points transactions logging
- [ ] Rankings aggregating
- [ ] No error spike in logs
- [ ] Performance metrics normal

### Post-Deployment Monitoring
- [ ] Monitor for 24 hours
- [ ] Check error rates
- [ ] Verify scheduled jobs running
- [ ] Monitor database performance
- [ ] Review webhook processing
- [ ] Check point decay accuracy

## Maintenance & Ongoing (Week 2+)

### Weekly Tasks
- [ ] Review error logs
- [ ] Check database size
- [ ] Verify backups completed
- [ ] Monitor performance metrics
- [ ] Review webhook events

### Monthly Tasks
- [ ] Database maintenance (VACUUM, REINDEX)
- [ ] Performance analysis
- [ ] Security audit
- [ ] Capacity planning
- [ ] Update documentation

### Documentation Maintenance
- [ ] Keep API docs updated
- [ ] Document any custom modifications
- [ ] Update guides with new features
- [ ] Maintain changelog

## Sign-Off

- [ ] All phases completed
- [ ] All tests passing
- [ ] Production verified
- [ ] Team trained
- [ ] Documentation complete
- [ ] Ready for production support

---

**Start Date**: _______________
**Completion Date**: _______________
**Signed Off By**: _______________
