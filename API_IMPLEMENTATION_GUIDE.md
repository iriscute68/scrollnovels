# API Implementation Guide

This document provides complete implementation details for the Scroll Novels Point System API.

## Table of Contents
1. [Installation & Setup](#installation--setup)
2. [API Endpoints](#api-endpoints)
3. [Authentication](#authentication)
4. [Database Schema](#database-schema)
5. [Patreon Integration](#patreon-integration)
6. [Admin Guide Management](#admin-guide-management)
7. [Scheduled Jobs](#scheduled-jobs)
8. [Error Handling](#error-handling)

---

## Installation & Setup

### Prerequisites
- Node.js 16+
- PostgreSQL 13+
- npm or yarn

### Installation Steps

1. **Install Dependencies**
   ```bash
   npm install
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your credentials
   ```

3. **Setup Database**
   ```bash
   # Create database
   createdb scroll_novels
   
   # Load schema
   psql scroll_novels < postgres-schema.sql
   ```

4. **Start Server**
   ```bash
   npm start          # Production
   npm run dev        # Development with auto-reload
   ```

### Configuration

Key environment variables:
- `DB_*` - PostgreSQL connection details
- `JWT_SECRET` - Token signing key (generate with: `openssl rand -hex 32`)
- `PATREON_CLIENT_ID/SECRET` - Get from Patreon API dashboard
- `PATREON_WEBHOOK_SECRET` - Configure in Patreon webhook settings

---

## API Endpoints

### Authentication
All endpoints except `GET /guides/*` and `POST /webhooks/patreon` require:
```
Authorization: Bearer <JWT_TOKEN>
```

### Points System

#### GET `/api/v1/me/points`
Get current user's point balance
```json
{
  "success": true,
  "data": {
    "free_points": 1000,
    "premium_points": 500,
    "patreon_points": 3000,
    "total_points": 4500
  }
}
```

#### GET `/api/v1/me/points/transactions?limit=20&offset=0`
Get point transaction history
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "type": "spent",
      "source": "book_support:book-uuid",
      "delta": -100,
      "balance_after": 4400,
      "metadata": {"multiplier": 2.0},
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 42,
    "limit": 20,
    "offset": 0
  }
}
```

#### POST `/api/v1/books/:bookId/support`
Support a book with points
```json
{
  "points": 100,
  "point_type": "premium"
}
```
Response:
```json
{
  "success": true,
  "message": "Supported with 100 premium points!",
  "data": {
    "support_id": "uuid",
    "effective_points": 200,
    "new_balance": {
      "free_points": 1000,
      "premium_points": 400,
      "patreon_points": 3000,
      "total_points": 4400
    }
  }
}
```

#### GET `/api/v1/books/:bookId/supports?limit=10`
Get top supporters of a book
```json
{
  "success": true,
  "data": [
    {
      "id": "user-uuid",
      "username": "john_doe",
      "profile_image": "url",
      "support_count": 15,
      "total_support_points": 4200
    }
  ]
}
```

#### GET `/api/v1/rankings?period=weekly&limit=50`
Get book rankings (periods: daily, weekly, monthly, all_time)
```json
{
  "success": true,
  "period": "weekly",
  "data": [
    {
      "rank": 1,
      "id": "book-uuid",
      "title": "Book Title",
      "slug": "book-title",
      "cover_url": "url",
      "author_id": "user-uuid",
      "author": "author_name",
      "supporter_count": 234,
      "total_support_points": 12500
    }
  ]
}
```

#### POST `/api/v1/admin/users/:userId/adjust-points` (ADMIN ONLY)
Manually adjust user points
```json
{
  "delta": 500,
  "point_category": "free_points",
  "reason": "Promotional event bonus"
}
```

### Patreon OAuth

#### GET `/api/v1/oauth/patreon/url`
Get Patreon OAuth authorization URL
```json
{
  "success": true,
  "url": "https://www.patreon.com/oauth2/authorize?client_id=...&state=..."
}
```

#### POST `/api/v1/oauth/patreon/callback`
Exchange OAuth code for account link
```json
{
  "code": "patreon_auth_code",
  "state": "csrf_state_token"
}
```
Response:
```json
{
  "success": true,
  "message": "Patreon account linked successfully",
  "data": {
    "patreon_user_id": "123456",
    "tier_name": "Gold",
    "pledge_amount_cents": 300000,
    "patron_status": "active_patron",
    "active": true
  }
}
```

#### GET `/api/v1/me/patreon`
Get current user's Patreon link info
```json
{
  "success": true,
  "linked": true,
  "data": {
    "patreon_user_id": "123456",
    "tier_name": "Gold",
    "pledge_amount_cents": 300000,
    "patron_status": "active_patron",
    "active": true,
    "last_reward_date": "2024-01-01T00:00:00Z",
    "next_reward_date": "2024-02-01T00:00:00Z"
  }
}
```

#### DELETE `/api/v1/me/patreon`
Unlink Patreon account
```json
{
  "success": true,
  "message": "Patreon account unlinked"
}
```

### Admin Guide Management

#### GET `/api/v1/guides` (Public)
List all published guides
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "slug": "how-points-work",
      "title": "How Points Work",
      "description": "Guide overview",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### GET `/api/v1/guides/:slug` (Public)
Get full guide with sections and images
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "slug": "how-points-work",
    "title": "How Points Work",
    "description": "Complete guide",
    "content": "# Main content...",
    "published": true,
    "sections": [
      {
        "id": "uuid",
        "title": "Section 1",
        "content": "Section content...",
        "order_index": 0
      }
    ],
    "images": [
      {
        "id": "uuid",
        "image_url": "/uploads/guides/guide-xyz.jpg",
        "caption": "Description",
        "alt_text": "Alt text",
        "order_index": 0
      }
    ]
  }
}
```

#### GET `/api/v1/admin/guides` (ADMIN ONLY)
Get all guides (all statuses)
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "slug": "how-points-work",
      "title": "How Points Work",
      "published": true,
      "created_by_name": "admin_user",
      "updated_by_name": "admin_user",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

#### POST `/api/v1/admin/guides` (ADMIN ONLY)
Create new guide
```json
{
  "slug": "new-guide",
  "title": "New Guide",
  "description": "Guide description",
  "content": "# Markdown content...",
  "order_index": 0
}
```

#### PUT `/api/v1/admin/guides/:id` (ADMIN ONLY)
Update guide
```json
{
  "title": "Updated Title",
  "description": "Updated description",
  "content": "Updated content...",
  "published": true,
  "order_index": 0
}
```

#### DELETE `/api/v1/admin/guides/:id` (ADMIN ONLY)
Delete guide (cascades to sections/images)

#### POST `/api/v1/admin/guides/:guideId/sections` (ADMIN ONLY)
Add section to guide
```json
{
  "title": "Section Title",
  "content": "Section content...",
  "order_index": 0
}
```

#### PUT `/api/v1/admin/guides/sections/:sectionId` (ADMIN ONLY)
Update guide section

#### DELETE `/api/v1/admin/guides/sections/:sectionId` (ADMIN ONLY)
Delete guide section

#### POST `/api/v1/admin/guides/:guideId/images` (ADMIN ONLY)
Upload image to guide
```
Content-Type: multipart/form-data
Parameters:
  - image: <file>
  - caption: (optional)
  - alt_text: (optional)
  - order_index: (optional)
```

#### PUT `/api/v1/admin/guides/images/:imageId` (ADMIN ONLY)
Update image metadata

#### DELETE `/api/v1/admin/guides/images/:imageId` (ADMIN ONLY)
Delete image

#### POST `/api/v1/admin/guides/:id/publish` (ADMIN ONLY)
Publish/unpublish guide
```json
{
  "published": true
}
```

---

## Authentication

### JWT Token Generation

1. **User Login** (your existing login endpoint)
   ```javascript
   const token = generateToken(userId, userRole);
   res.json({ token });
   ```

2. **Token Usage**
   ```javascript
   // Frontend
   fetch('/api/v1/me/points', {
     headers: {
       'Authorization': `Bearer ${token}`
     }
   });
   ```

3. **Token Claims**
   ```javascript
   {
     id: "user-uuid",
     role: "user", // or "author", "admin"
     iat: 1234567890,
     exp: 1235259890
   }
   ```

---

## Database Schema

All tables are defined in `postgres-schema.sql`. Key relationships:

```
users (1) ──────> (M) points_transactions
       ├──> (1) user_points_balance
       ├──> (M) book_support
       ├──> (1) patreon_links
       └──> (M) guide_pages (created_by/updated_by)

books (1) ──────> (M) book_support
      └──> (M) book_rankings

patreon_tier_config (1) ──────> (M) patreon_links

guide_pages (1) ──────> (M) guide_sections
           └──> (M) guide_images
```

---

## Patreon Integration

### Setup in Patreon Dashboard

1. **OAuth Setup**
   - Go to `Account Settings > Clients`
   - Create new client
   - Set redirect URI: `https://yourdomain.com/api/v1/oauth/patreon/callback`
   - Save `Client ID` and `Client Secret`

2. **Webhook Setup**
   - Go to `Account Settings > Webhooks`
   - Create webhook with URL: `https://yourdomain.com/webhooks/patreon`
   - Subscribe to: `pledges:create`, `pledges:update`, `pledges:delete`
   - Save `Webhook Secret`

### OAuth Flow

```
1. Frontend calls: GET /api/v1/oauth/patreon/url
2. Frontend redirects user to Patreon login
3. User authorizes app
4. Patreon redirects to: /api/v1/oauth/patreon/callback?code=...&state=...
5. Backend exchanges code for access token
6. Backend fetches patron info and creates patreon_links record
7. Backend awards initial points if active patron
```

### Webhook Flow

```
1. Patreon sends webhook to: POST /webhooks/patreon
2. Verify HMAC-SHA256 signature
3. Process event (pledges:create, pledges:update, pledges:delete)
4. Update patreon_links and award points if applicable
5. Store in patreon_webhook_events for audit trail
```

### Tier Configuration

Pre-configured tiers in database:
- **Bronze**: 500 points/month
- **Silver**: 1200 points/month (2x multiplier)
- **Gold**: 3000 points/month (3x multiplier)
- **Diamond**: 10000 points/month (3x multiplier)

---

## Scheduled Jobs

### 1. Daily Patreon Reward Processing
- **Schedule**: 12:00 AM UTC (every day)
- **Function**: `processPendingRewards()`
- **Action**: Awards monthly points to active patrons who haven't been rewarded this month

### 2. Weekly Point Decay
- **Schedule**: 12:00 AM UTC (every Monday)
- **Function**: `processPointDecay()`
- **Action**: 
  - Applies 20% decay per week
  - Expires points after 4 weeks
  - Logs all decay transactions

### 3. Daily Rankings Aggregation
- **Schedule**: 1:00 AM UTC (every day)
- **Function**: `aggregateBookRankings()`
- **Action**: Pre-calculates rankings for all periods (daily, weekly, monthly, all_time)

---

## Error Handling

### Standard Error Responses

```json
{
  "error": "Error message",
  "status": 400
}
```

### Common Error Codes

| Status | Scenario |
|--------|----------|
| 400 | Missing/invalid parameters |
| 401 | Unauthorized or expired token |
| 403 | Insufficient permissions (admin required) |
| 404 | Resource not found |
| 409 | Conflict (e.g., duplicate slug) |
| 500 | Server error |

### Logging

Set `LOG_LEVEL` in .env to control verbosity:
- `error` - Only errors
- `info` - Errors + important info
- `debug` - Verbose logging

---

## Frontend Integration Example

```javascript
// Get user points
const getPoints = async (token) => {
  const res = await fetch('/api/v1/me/points', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return res.json();
};

// Support a book
const supportBook = async (token, bookId, points, pointType) => {
  const res = await fetch(`/api/v1/books/${bookId}/support`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ points, point_type: pointType })
  });
  return res.json();
};

// Get rankings
const getRankings = async (period = 'weekly') => {
  const res = await fetch(`/api/v1/rankings?period=${period}`);
  return res.json();
};

// Get guide
const getGuide = async (slug) => {
  const res = await fetch(`/api/v1/guides/${slug}`);
  return res.json();
};
```

---

## Troubleshooting

### "Database connection failed"
- Check PostgreSQL is running
- Verify DB_* environment variables
- Check network connectivity

### "Patreon webhook signature invalid"
- Verify PATREON_WEBHOOK_SECRET is correct
- Ensure webhook payload is not being modified
- Check Patreon dashboard for webhook activity logs

### "Token expired"
- Frontend should refresh token or re-login
- Token lifetime is 7 days
- Implement token refresh flow if needed

### Points not updating
- Check user_points_balance row exists
- Verify point_type is valid (free, premium, patreon)
- Check points_transactions for audit trail
- Run: `SELECT * FROM user_points_balance WHERE user_id = 'user_uuid';`
