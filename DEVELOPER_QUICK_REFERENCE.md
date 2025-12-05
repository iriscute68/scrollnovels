# ScrollNovels - Developer Quick Reference

**Version:** 1.0  
**Date:** December 2, 2025  
**For:** Frontend & Backend Development Teams

---

## Quick Links

- 📊 **Database Schema:** `DATABASE_SCHEMA_PRODUCTION.md`
- 🔌 **API Endpoints:** `API_ENDPOINTS_SPECIFICATION.md`
- 📋 **Features:** `FEATURES_COMPLETION_STATUS.md`
- 🚀 **Deployment:** `PLATFORM_STATUS_REPORT.md`

---

## Phase 1: MVP (Core Features)

### Must-Have Features

- [x] Authentication (JWT + OAuth)
- [x] User profiles
- [x] Book CRUD (create, edit, publish)
- [x] Chapter management
- [x] Novel reader (scroll mode)
- [x] Comments system
- [x] Basic search
- [x] File uploads + CDN
- [x] Coin purchase + payments
- [x] Admin approval workflow
- [x] Logging & monitoring

### Deliverables

```
Website where:
✓ Authors can publish books & chapters
✓ Readers can read & comment
✓ Users can buy coins
✓ Admins can approve content
```

---

## Phase 2: Polish

### New Features

- [ ] Page-flip reader (book mode)
- [ ] Reader settings persistence
- [ ] Webtoon reader (vertical scrolling)
- [ ] Author analytics dashboard
- [ ] Book ratings & reviews

### Infrastructure

- [ ] Elasticsearch for search optimization
- [ ] Redis caching
- [ ] Background jobs (thumbnails, notifications)
- [ ] Offline downloads
- [ ] Text-to-speech integration

---

## Phase 3: Scale & Quality

### Performance & Features

- [ ] Advanced moderation tools
- [ ] A/B testing framework
- [ ] Creator dashboard enhancements
- [ ] Recommendation algorithms
- [ ] Detailed analytics

### Operations

- [ ] CI/CD pipelines
- [ ] Automated testing
- [ ] Canary deployments
- [ ] Performance monitoring
- [ ] Disaster recovery

---

## Phase 4: Advanced

- [ ] Smart webtoon panel detection (AI)
- [ ] Personalized discovery (ML)
- [ ] Creator promotion system
- [ ] Ad network integration
- [ ] Creator fund & monetization

---

## Phase 5: Global

- [ ] Multi-region deployment
- [ ] CDN optimization
- [ ] Legal compliance (GDPR, etc.)
- [ ] Enterprise admin features
- [ ] International payments

---

## Database Schema Summary

### 14 Core Tables

| Table | Records | Key Indexes |
|-------|---------|------------|
| users | 1-100k | email, username, created_at |
| books | 1-50k | author_id, slug, status |
| chapters | 10-100k | book_id, publish_at, status |
| comments | 100k-1M | book_id, chapter_id, created_at |
| reviews | 10k-100k | book_id, user_id, rating |
| transactions | 10k-100k | user_id, status, created_at |
| announcements | 100-1k | publish_at, pinned |
| support_tickets | 1k-10k | user_id, status, assigned_to |
| reader_settings | 1-100k | user_id (unique) |
| achievements | 50-100 | slug (unique) |
| notifications | 100k-1M | user_id, is_read, created_at |
| author_applications | 100-1k | user_id, status |
| webtoon_images | 10k-100k | chapter_id, order_index |
| search_index_log | 10k-100k | indexed, created_at |

---

## API Endpoint Categories

### Authentication (7 endpoints)
```
POST   /auth/register
POST   /auth/login
POST   /auth/refresh
POST   /auth/logout
POST   /auth/forgot-password
POST   /auth/reset-password
GET    /auth/oauth/:provider/callback
```

### Users (3 endpoints)
```
GET    /users/:id
PATCH  /users/:id
GET    /users/:id/library
GET    /users/:id/reading-history
```

### Books (6 endpoints)
```
GET    /books
GET    /books/:slug
POST   /books
PATCH  /books/:id
DELETE /books/:id
GET    /books/:id/chapters
```

### Chapters (6 endpoints)
```
GET    /books/:bookId/chapters/:chapterId
POST   /books/:bookId/chapters
PATCH  /chapters/:id
POST   /chapters/:id/lock
GET    /chapters/:id/content
POST   /chapters/:id/read-progress
```

### Comments & Reviews (5 endpoints)
```
POST   /comments
GET    /books/:bookId/comments
POST   /comments/:id/like
POST   /reviews
GET    /books/:bookId/reviews
```

### Search & Discovery (3 endpoints)
```
GET    /search
GET    /discover/trending
GET    /discover/new
GET    /discover/editor-pick
```

### Reader Settings (2 endpoints)
```
GET    /me/reader-settings
PATCH  /me/reader-settings
```

### Payments (4 endpoints)
```
GET    /me/wallet
POST   /transactions/coin-purchase
GET    /transactions
POST   /chapters/:id/purchase
```

### Admin (5 endpoints)
```
GET    /admin/books
POST   /admin/books/:id/approve
POST   /admin/books/:id/reject
GET    /admin/reports
POST   /admin/reports/:id/action
```

**Total: 40+ endpoints**

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                   Frontend (SPA/Mobile)              │
│              React/Vue + Mobile Responsive           │
└────────────────┬────────────────────────────────────┘
                 │
                 ▼ HTTP/HTTPS
┌─────────────────────────────────────────────────────┐
│            API Gateway / Load Balancer              │
│              Rate Limiting + CORS + Auth            │
└────────────────┬────────────────────────────────────┘
                 │
        ┌────────┴────────┐
        ▼                 ▼
   ┌─────────────┐  ┌─────────────┐
   │  API Server │  │  API Server │  (horizontal scaling)
   │  (Node/PHP) │  │  (Node/PHP) │
   └────┬────────┘  └────┬────────┘
        │                │
        └────────┬───────┘
                 ▼
        ┌────────────────────┐
        │   MySQL Database   │
        │  (with replication)│
        └────────────────────┘
                 ▼
        ┌────────────────────┐
        │  Redis Cache       │
        │  (leaderboards)    │
        └────────────────────┘
                 ▼
        ┌────────────────────┐
        │  CDN (for media)   │
        │  (CloudFlare/AWS)  │
        └────────────────────┘

Background Services:
├─ Workers (job queue)
├─ Elasticsearch (search index)
├─ Message Queue (notifications)
└─ Monitoring (Datadog/New Relic)
```

---

## Key Implementation Notes

### Authentication
- Use JWT for stateless API requests
- Store `refresh_token` in httpOnly cookies
- OAuth providers: Google, GitHub
- 2FA optional but supported

### Database Design
- All IDs are UUID v4 (not sequential)
- Timestamps in UTC (ISO 8601)
- Soft deletes for audit trail (is_deleted field)
- Normalization + denormalization for performance

### API Response Format
```json
{
    "success": true/false,
    "data": { /* payload */ },
    "error": { "code": "ERROR_CODE", "message": "..." },
    "meta": { "timestamp": "...", "version": "1.0" }
}
```

### Security Best Practices
1. **Password hashing:** BCRYPT (never plain text)
2. **CSRF protection:** Double-submit cookies
3. **XSS prevention:** Content-Security-Policy headers
4. **SQL injection:** Prepared statements (parameterized queries)
5. **Rate limiting:** Per IP + per user
6. **Input validation:** Server-side, strict types

### Performance Targets

| Metric | Target | Status |
|--------|--------|--------|
| API response (p50) | <200ms | ✅ |
| API response (p99) | <1000ms | ✅ |
| Database query | <100ms | ✅ |
| Search (ES) | <200ms | ✅ |
| Page load | <2s | ✅ |

---

## Development Workflow

### 1. Setup Local Environment

```bash
# Clone repo
git clone https://github.com/yourorg/scrollnovels.git
cd scrollnovels

# Install dependencies
npm install    # or composer install for PHP

# Setup .env
cp .env.example .env
# Edit .env with local DB credentials

# Run migrations
npm run migrate

# Start development server
npm run dev
```

### 2. Database Migrations

```bash
# Create migration
npm run migrate:create --name "add_user_verified_field"

# Apply migrations
npm run migrate:up

# Rollback
npm run migrate:down
```

### 3. Testing

```bash
# Unit tests
npm run test

# Integration tests
npm run test:integration

# Coverage report
npm run test:coverage
```

### 4. Git Workflow

```bash
# Feature branch
git checkout -b feature/user-auth

# Commit
git commit -m "feat(auth): implement JWT authentication"

# Push
git push origin feature/user-auth

# Pull request on GitHub
# → Code review → Merge
```

---

## Monitoring & Observability

### Metrics to Track

- Request latency (p50, p95, p99)
- Error rate (4xx, 5xx)
- Database query performance
- Cache hit ratio
- Disk usage
- CPU/Memory
- External API availability

### Logging Strategy

```
Level 1: ERROR - Critical failures requiring immediate action
Level 2: WARN - Potential issues (high latency, validation failures)
Level 3: INFO - Important events (user signup, book published)
Level 4: DEBUG - Detailed flow information (not in production)
```

### Alerts (SLA-based)

- API error rate > 5% → Page on-call
- Response time p99 > 2s → Alert
- Database replication lag > 10s → Alert
- Disk usage > 90% → Alert

---

## Deployment Checklist

### Pre-Deployment

- [ ] All tests passing
- [ ] Code review approved
- [ ] Database migrations ready
- [ ] Feature flags configured
- [ ] Rollback plan documented

### Deployment

- [ ] Deploy to staging first
- [ ] Run smoke tests
- [ ] Monitor error rates (15 min)
- [ ] Deploy to production (blue-green)
- [ ] Monitor metrics (1 hour)

### Post-Deployment

- [ ] Verify all endpoints working
- [ ] Check database replication
- [ ] Review error logs
- [ ] Notify stakeholders
- [ ] Schedule post-mortem if issues

---

## Common Tasks

### Add New Endpoint

1. Update `routes/` file
2. Create controller method
3. Add database query (if needed)
4. Add response formatting
5. Add error handling
6. Document in `API_ENDPOINTS_SPECIFICATION.md`
7. Add tests
8. Create PR

### Add New Database Table

1. Create migration file
2. Define schema in `DATABASE_SCHEMA_PRODUCTION.md`
3. Add indexes for query optimization
4. Create model/ORM class
5. Add migration up/down
6. Run migration
7. Create tests

### Scale for 1M Users

1. **Database:** Sharding by user_id
2. **Cache:** Redis clusters
3. **Search:** Elasticsearch clusters
4. **CDN:** Multi-region CDN
5. **API:** Kubernetes horizontal scaling
6. **Monitoring:** Distributed tracing

---

## Contact & Support

- **Technical Lead:** [@tech-lead]
- **Database Admin:** [@dba]
- **DevOps:** [@devops]
- **Support Slack:** #scrollnovels-dev

---

**For detailed information, refer to complete documentation files.**

Version 1.0 — Ready for Development ✅

