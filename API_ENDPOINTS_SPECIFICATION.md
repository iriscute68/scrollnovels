# ScrollNovels - API Endpoints Specification (v1)

**Version:** 1.0  
**Base URL:** `https://api.scrollnovels.com/api/v1`  
**Status:** Production-Ready  
**Date:** December 2, 2025

---

## Table of Contents

1. [Authentication Endpoints](#authentication)
2. [User Endpoints](#users)
3. [Books & Webtoons Endpoints](#books--webtoons)
4. [Chapters & Episodes Endpoints](#chapters--episodes)
5. [Comments & Reviews Endpoints](#comments--reviews)
6. [Search & Discovery Endpoints](#search--discovery)
7. [Reader Settings Endpoints](#reader-settings)
8. [Transactions & Payments Endpoints](#transactions--payments)
9. [Admin Endpoints](#admin)
10. [Error Codes](#error-codes)
11. [Rate Limiting](#rate-limiting)
12. [Response Format](#response-format)

---

## Authentication

All endpoints (except `/auth/register`, `/auth/login`, `/auth/oauth/:provider/callback`) require:

```
Authorization: Bearer {token}
```

Or cookie-based session (`Set-Cookie: sessionId=...`).

---

## Endpoints

### 1. AUTHENTICATION

#### POST /auth/register

Register a new user account.

**Request:**
```json
{
    "email": "user@example.com",
    "username": "john_doe",
    "password": "SecurePass123!",
    "terms_accepted": true
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "email": "user@example.com",
        "username": "john_doe",
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "expires_in": 86400
    }
}
```

**Validation:**
- Email: Valid format, unique
- Username: 3-50 chars, alphanumeric + underscore, unique
- Password: Min 8 chars, 1 uppercase, 1 number, 1 special char
- Terms: Must be true

---

#### POST /auth/login

Authenticate user and return JWT token.

**Request:**
```json
{
    "email_or_username": "john_doe",
    "password": "SecurePass123!"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "refresh_token_here",
        "expires_in": 86400,
        "user": {
            "email": "user@example.com",
            "username": "john_doe",
            "role": "user",
            "avatar_url": "https://cdn.example.com/avatar.jpg"
        }
    }
}
```

---

#### POST /auth/refresh

Refresh JWT token using refresh token.

**Request:**
```json
{
    "refresh_token": "refresh_token_here"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "token": "new_token_here",
        "expires_in": 86400
    }
}
```

---

#### POST /auth/logout

Invalidate current token and session.

**Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

---

#### POST /auth/forgot-password

Request password reset email.

**Request:**
```json
{
    "email": "user@example.com"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Reset email sent to user@example.com"
}
```

---

#### POST /auth/reset-password

Reset password using token from email.

**Request:**
```json
{
    "token": "reset_token_from_email",
    "password": "NewSecurePass456!"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Password reset successfully"
}
```

---

#### GET /auth/oauth/:provider/callback

OAuth callback (Google, GitHub, etc).

**Query Params:**
- `code` - Authorization code from OAuth provider
- `state` - State parameter for security

**Response (302):** Redirect to app with token in URL fragment

---

### 2. USERS

#### GET /users/:id

Get user profile by ID.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "username": "john_doe",
        "email": "user@example.com",
        "bio": "Fantasy author from Canada",
        "avatar_url": "https://cdn.example.com/avatar.jpg",
        "role": "author",
        "is_verified_author": true,
        "followers_count": 1250,
        "following_count": 342,
        "books_count": 5,
        "total_reads": 125000,
        "created_at": "2024-01-15T10:30:00Z",
        "last_active": "2025-12-02T15:45:00Z"
    }
}
```

---

#### PATCH /users/:id

Update user profile (auth required, must be self or admin).

**Request:**
```json
{
    "bio": "Updated bio text",
    "avatar_url": "https://cdn.example.com/new-avatar.jpg",
    "theme_preference": "dark"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": { /* updated user object */ }
}
```

---

#### GET /users/:id/library

Get user's saved/bookmarked books.

**Query Params:**
- `page` (default: 1)
- `limit` (default: 20, max: 100)
- `sort` (default: `added_at`, options: `added_at`, `title`, `rating`)
- `status` (filter: `reading`, `completed`, `paused`)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "books": [
            {
                "id": "...",
                "title": "Book Title",
                "author": { /* author object */ },
                "cover_url": "...",
                "status": "reading",
                "progress": {
                    "current_chapter": 25,
                    "total_chapters": 150,
                    "percentage": 16.7
                },
                "last_read_at": "2025-12-02T10:00:00Z"
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 20,
            "total": 45,
            "pages": 3
        }
    }
}
```

---

#### GET /users/:id/reading-history

Get user's recent reading history.

**Query Params:**
- `limit` (default: 50)
- `days` (default: 30, show history from last N days)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "history": [
            {
                "book_id": "...",
                "chapter_id": "...",
                "chapter_number": 25,
                "read_at": "2025-12-02T10:30:00Z",
                "read_duration_seconds": 1200
            }
        ]
    }
}
```

---

### 3. BOOKS & WEBTOONS

#### GET /books

List books with pagination, filtering, and sorting.

**Query Params:**
```
page=1
limit=20
sort=created_at|rating|reads|trending
status=ongoing|completed|dropped
is_webtoon=true|false
language=en
tag=fantasy,romance
author_id=<uuid>
search=query_text
visibility=public
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "books": [
            {
                "id": "...",
                "title": "The Dragon's Quest",
                "slug": "the-dragons-quest",
                "author": {
                    "id": "...",
                    "username": "author_name",
                    "avatar_url": "...",
                    "is_verified": true
                },
                "cover_url": "https://cdn.example.com/cover.jpg",
                "synopsis": "Epic fantasy adventure...",
                "status": "ongoing",
                "is_webtoon": false,
                "rating_avg": 4.5,
                "rating_count": 1250,
                "reads_count": 50000,
                "followers_count": 5000,
                "chapters_count": 120,
                "tags": ["fantasy", "adventure", "magic"],
                "language": "en",
                "created_at": "2024-06-15T00:00:00Z"
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 20,
            "total": 5420,
            "pages": 271
        }
    }
}
```

---

#### GET /books/:slug

Get book details by slug (SEO-friendly).

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": "...",
        "title": "The Dragon's Quest",
        "slug": "the-dragons-quest",
        /* full book object */,
        "author": { /* detailed author */ },
        "chapters": [
            {
                "id": "...",
                "chapter_number": 1,
                "title": "Chapter 1: The Beginning",
                "is_locked": false,
                "reads_count": 5000,
                "created_at": "..."
            }
        ],
        "latest_chapter": { /* latest chapter object */ },
        "next_chapter": { /* next scheduled chapter */ },
        "user_progress": {
            "current_chapter": 50,
            "total_chapters": 120,
            "percentage": 41.7,
            "last_read_at": "2025-12-02T10:00:00Z"
        },
        "has_read_access": true,
        "is_bookmarked": false,
        "is_following": false
    }
}
```

---

#### POST /books

Create a new book (requires `author` role).

**Request:**
```json
{
    "title": "My New Book",
    "synopsis": "Book description...",
    "cover_url": "https://cdn.example.com/cover.jpg",
    "is_webtoon": false,
    "language": "en",
    "tags": ["fantasy", "adventure"],
    "status": "draft",
    "visibility": "draft"
}
```

**Response (201):**
```json
{
    "success": true,
    "data": { /* created book object */ }
}
```

---

#### PATCH /books/:id

Update book metadata (auth as author or admin).

**Request:**
```json
{
    "title": "Updated Title",
    "synopsis": "Updated description...",
    "status": "ongoing",
    "visibility": "public"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": { /* updated book object */ }
}
```

---

#### DELETE /books/:id

Delete a book (auth as author or admin, book must have no chapters).

**Response (204):** No content

---

#### GET /books/:id/chapters

List all chapters of a book.

**Query Params:**
- `page`, `limit`, `sort`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "chapters": [
            {
                "id": "...",
                "chapter_number": 1,
                "title": "Chapter 1",
                "status": "published",
                "word_count": 3500,
                "is_locked": false,
                "reads_count": 5000,
                "comments_count": 150,
                "publish_at": "2024-06-20T00:00:00Z"
            }
        ],
        "pagination": { /* ... */ }
    }
}
```

---

### 4. CHAPTERS & EPISODES

#### GET /books/:bookId/chapters/:chapterId

Get full chapter content with reader options.

**Query Params:**
- `format` (default: `html`, options: `html`, `markdown`, `plain`)
- `include_annotations` (default: false)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": "...",
        "book_id": "...",
        "chapter_number": 1,
        "title": "Chapter 1: The Beginning",
        "content": "<p>Chapter content here...</p>",
        "word_count": 3500,
        "status": "published",
        "publish_at": "2024-06-20T00:00:00Z",
        "is_locked": false,
        "reads_count": 5000,
        "comments_count": 150,
        "user_progress": {
            "read_percentage": 45,
            "read_duration_seconds": 1200
        },
        "navigation": {
            "previous_chapter": { /* chapter object or null */ },
            "next_chapter": { /* chapter object or null */ }
        },
        "reader_settings": {
            "font_family": "Georgia",
            "font_size": 18,
            "theme": "light",
            "line_height": 1.6
        }
    }
}
```

**Note:** Content is sanitized HTML. Client can apply reader settings for display.

---

#### POST /books/:bookId/chapters

Create new chapter (auth as author).

**Request:**
```json
{
    "chapter_number": 1,
    "title": "Chapter 1: Beginning",
    "content": "<p>Chapter text...</p>",
    "status": "draft",
    "publish_at": "2025-12-05T10:00:00Z"
}
```

**Response (201):**
```json
{
    "success": true,
    "data": { /* created chapter object */ }
}
```

---

#### PATCH /chapters/:id

Update chapter (auth as author).

**Request:**
```json
{
    "title": "Updated Title",
    "content": "<p>Updated content...</p>",
    "status": "published"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": { /* updated chapter object */ }
}
```

---

#### POST /chapters/:id/lock

Set chapter paywall (auth as author).

**Request:**
```json
{
    "is_locked": true,
    "price_coins": 50
}
```

**Response (200):**
```json
{
    "success": true,
    "data": { /* updated chapter */ }
}
```

---

#### GET /chapters/:id/content

Get optimized chapter content (prerendered HTML + images).

**Response (200):**
```json
{
    "success": true,
    "data": {
        "html": "<article>...</article>",
        "images": [
            {
                "url": "https://cdn.example.com/image.jpg",
                "alt": "Image description",
                "width": 800,
                "height": 600
            }
        ],
        "estimated_read_time_minutes": 12,
        "character_count": 3500
    }
}
```

---

#### POST /chapters/:id/read-progress

Track user's reading progress.

**Request:**
```json
{
    "percentage": 50,
    "read_duration_seconds": 600
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Progress recorded"
}
```

---

### 5. COMMENTS & REVIEWS

#### POST /comments

Post a comment on book or chapter.

**Request:**
```json
{
    "book_id": "uuid_or_null",
    "chapter_id": "uuid_or_null",
    "parent_comment_id": "uuid_or_null",
    "content": "Great story!",
    "is_spoiler": false
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": "...",
        "user": { /* commenter */ },
        "content": "Great story!",
        "likes_count": 0,
        "replies_count": 0,
        "is_spoiler": false,
        "created_at": "2025-12-02T15:30:00Z"
    }
}
```

---

#### GET /books/:bookId/comments

Get all comments on a book.

**Query Params:**
- `page`, `limit`
- `sort` (default: `latest`, options: `latest`, `top`, `oldest`)
- `include_spoilers` (default: false)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "comments": [
            {
                "id": "...",
                "user": { /* user object */ },
                "content": "Comment text",
                "likes_count": 25,
                "replies_count": 5,
                "replies": [
                    { /* nested reply */ }
                ],
                "created_at": "..."
            }
        ],
        "pagination": { /* ... */ }
    }
}
```

---

#### POST /comments/:id/like

Like a comment.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "likes_count": 26,
        "user_liked": true
    }
}
```

---

#### POST /reviews

Post a review for a book.

**Request:**
```json
{
    "book_id": "...",
    "rating": 5,
    "content": "Amazing book! Highly recommend.",
    "is_spoiler": false
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "id": "...",
        "user": { /* reviewer */ },
        "rating": 5,
        "content": "Amazing book!...",
        "likes_count": 0,
        "created_at": "..."
    }
}
```

---

#### GET /books/:bookId/reviews

Get reviews for a book.

**Query Params:**
- `page`, `limit`
- `sort` (default: `helpful`, options: `helpful`, `rating_high`, `rating_low`, `latest`)
- `rating` (filter: 1-5)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "reviews": [
            {
                "id": "...",
                "user": { /* reviewer */ },
                "rating": 5,
                "content": "...",
                "likes_count": 120,
                "created_at": "..."
            }
        ],
        "summary": {
            "average_rating": 4.5,
            "total_reviews": 1200,
            "rating_distribution": {
                "5": 600,
                "4": 400,
                "3": 150,
                "2": 30,
                "1": 20
            }
        },
        "pagination": { /* ... */ }
    }
}
```

---

### 6. SEARCH & DISCOVERY

#### GET /search

Full-text search across books, chapters, users.

**Query Params:**
```
q=search_query (required)
type=book|chapter|user|all (default: all)
page=1
limit=20
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "query": "dragon",
        "results": {
            "books": [
                {
                    "id": "...",
                    "title": "Dragon's Quest",
                    "relevance_score": 0.95,
                    "highlight": "The <mark>dragon</mark> awoke from its slumber..."
                }
            ],
            "users": [ /* ... */ ],
            "total_results": 342
        }
    }
}
```

---

#### GET /discover/trending

Get trending books (last 7 days).

**Query Params:**
- `limit` (default: 20)
- `tag` (optional filter)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "trending": [
            {
                "id": "...",
                "title": "...",
                "trend_score": 95.5,
                "new_readers_count": 500,
                "new_reviews_count": 120
            }
        ]
    }
}
```

---

#### GET /discover/new

Get newly published books.

**Query Params:**
- `limit` (default: 20)
- `days` (default: 7)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "new_books": [
            {
                "id": "...",
                "title": "...",
                "published_at": "2025-12-02T00:00:00Z"
            }
        ]
    }
}
```

---

#### GET /discover/editor-pick

Get editor-curated books.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "picks": [
            {
                "id": "...",
                "title": "...",
                "featured_reason": "Editor's Choice",
                "featured_image": "..."
            }
        ]
    }
}
```

---

### 7. READER SETTINGS

#### GET /me/reader-settings

Get current user's reader preferences.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "id": "...",
        "font_family": "Georgia",
        "font_size": 18,
        "theme": "light",
        "alignment": "left",
        "padding": 20,
        "line_height": 1.6,
        "mode": "scroll",
        "updated_at": "2025-12-02T10:00:00Z"
    }
}
```

---

#### PATCH /me/reader-settings

Update reader preferences.

**Request:**
```json
{
    "font_family": "Lato",
    "font_size": 16,
    "theme": "dark",
    "alignment": "justify",
    "padding": 25,
    "line_height": 1.8,
    "mode": "page-flip"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": { /* updated settings */ }
}
```

**Note:** Settings auto-sync across all user devices.

---

### 8. TRANSACTIONS & PAYMENTS

#### GET /me/wallet

Get user's wallet and coin balance.

**Response (200):**
```json
{
    "success": true,
    "data": {
        "balance": 500,
        "currency": "USD",
        "total_spent": 2500,
        "total_earned": 5000,
        "last_transaction": "2025-12-02T10:00:00Z"
    }
}
```

---

#### POST /transactions/coin-purchase

Initiate coin purchase.

**Request:**
```json
{
    "amount": 100,
    "currency": "USD",
    "payment_method": "card_id_or_new"
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "transaction_id": "txn_...",
        "status": "pending",
        "amount": 100,
        "coins_received": 1000,
        "provider": "stripe",
        "next_url": "https://checkout.stripe.com/..."
    }
}
```

---

#### GET /transactions

Get user's transaction history.

**Query Params:**
- `page`, `limit`
- `type` (coin_purchase, payout, tip, etc.)
- `status` (pending, completed, failed)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "transactions": [
            {
                "id": "...",
                "type": "coin_purchase",
                "amount": 100,
                "coins": 1000,
                "status": "completed",
                "created_at": "2025-12-02T10:00:00Z"
            }
        ],
        "pagination": { /* ... */ }
    }
}
```

---

#### POST /chapters/:id/purchase

Purchase access to locked chapter.

**Request:**
```json
{
    "coins_amount": 50
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Chapter unlocked",
    "data": {
        "access_until": "2026-12-02T10:00:00Z"
    }
}
```

---

### 9. ADMIN

#### GET /admin/books

List all books with admin filters.

**Query Params:**
- `status` (draft, published, rejected)
- `author_id`
- `page`, `limit`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "books": [ /* books with admin fields */ ],
        "pagination": { /* ... */ }
    }
}
```

---

#### POST /admin/books/:id/approve

Approve a book for publication.

**Request:**
```json
{
    "notes": "Approved for publication"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Book approved"
}
```

---

#### POST /admin/books/:id/reject

Reject a book submission.

**Request:**
```json
{
    "reason": "Violates content policy"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Book rejected"
}
```

---

#### GET /admin/reports

Get abuse/moderation reports.

**Query Params:**
- `status` (open, resolved)
- `page`, `limit`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": "...",
                "type": "inappropriate_content",
                "entity_type": "comment",
                "entity_id": "...",
                "reported_by": { /* user */ },
                "status": "open",
                "created_at": "..."
            }
        ],
        "pagination": { /* ... */ }
    }
}
```

---

#### POST /admin/reports/:id/action

Take action on a report.

**Request:**
```json
{
    "action": "delete_content|warn_user|ban_user",
    "reason": "Violates policy"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Action taken"
}
```

---

## Error Codes

### Standard Error Response Format

```json
{
    "success": false,
    "error": {
        "code": "INVALID_REQUEST",
        "message": "Human-readable error message",
        "details": { /* additional context */ }
    }
}
```

### HTTP Status Codes & Error Codes

| Status | Code | Message |
|--------|------|---------|
| 400 | `INVALID_REQUEST` | Invalid parameters or malformed request |
| 401 | `UNAUTHORIZED` | Missing or invalid authentication |
| 403 | `FORBIDDEN` | User lacks permission (e.g., not author) |
| 404 | `NOT_FOUND` | Resource not found |
| 409 | `CONFLICT` | Duplicate resource (e.g., username taken) |
| 422 | `VALIDATION_ERROR` | Input validation failed |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `SERVER_ERROR` | Internal server error |
| 503 | `SERVICE_UNAVAILABLE` | Service temporarily unavailable |

### Example Error Response

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation failed",
        "details": {
            "email": ["Invalid email format"],
            "password": ["Must be at least 8 characters"]
        }
    }
}
```

---

## Rate Limiting

### Rate Limit Headers

All responses include:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 995
X-RateLimit-Reset: 1638460800
```

### Limits (per user, per hour)

| Endpoint | Limit | Notes |
|----------|-------|-------|
| `/auth/login` | 10 | Per IP address |
| `/auth/register` | 5 | Per IP address |
| `/search` | 100 | Per user |
| `/books` | 200 | Per user |
| `/comments` | 50 | Per user |
| All write endpoints | 100 | Per user |
| All other endpoints | 1000 | Per user |

---

## Response Format

### Success Response

```json
{
    "success": true,
    "data": { /* response payload */ },
    "meta": {
        "timestamp": "2025-12-02T15:45:00Z",
        "version": "1.0"
    }
}
```

### Paginated Response

```json
{
    "success": true,
    "data": {
        "items": [ /* array of items */ ],
        "pagination": {
            "page": 1,
            "limit": 20,
            "total": 5420,
            "pages": 271,
            "has_next": true,
            "has_previous": false
        }
    }
}
```

### List Response (deprecated format, use pagination)

```json
{
    "success": true,
    "data": [ /* array of items */ ]
}
```

---

## Best Practices for API Consumers

1. **Always check `success` flag** before accessing `data`
2. **Handle rate limiting** with exponential backoff
3. **Validate input** before sending requests
4. **Use pagination** for large datasets (don't fetch all at once)
5. **Cache read-only responses** when possible
6. **Refresh tokens** before expiration
7. **Follow redirect URLs** from payment providers
8. **Sanitize user content** on client-side for display

---

## SDK & Client Libraries

**Official SDKs:**
- JavaScript/TypeScript: `@scrollnovels/client-js`
- Python: `scrollnovels-python`
- Ruby: `scrollnovels-ruby`

---

**API Version:** 1.0  
**Last Updated:** December 2, 2025  
**Status:** Production-Ready ✅

