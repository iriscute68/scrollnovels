<?php
/**
 * QUICK API REFERENCE - Admin & Support Systems
 * 
 * All endpoints return JSON responses
 * All endpoints require session_start() (login)
 * All admin endpoints require specific admin_level
 */

// ============================================================================
// SUPPORT TICKETS
// ============================================================================

/**
 * CREATE SUPPORT TICKET
 * POST /api/create-support-ticket.php
 * 
 * Required: user must be logged in
 * 
 * Request:
 * {
 *     "subject": "Issue title",
 *     "description": "Detailed description",
 *     "category": "bug|feature|payment|account|content|other",
 *     "priority": "low|medium|high|urgent"
 * }
 * 
 * Response (201):
 * {
 *     "success": true,
 *     "ticket_id": 123,
 *     "message": "Support ticket created..."
 * }
 */

/**
 * GET SUPPORT TICKETS
 * GET /api/get-support-tickets.php
 * GET /api/get-support-tickets.php?status=open
 * 
 * Required: user must be logged in (returns only their tickets)
 * 
 * Query params:
 *   - status: filter by status (optional)
 * 
 * Response (200):
 * {
 *     "success": true,
 *     "tickets": [...],
 *     "count": 5
 * }
 */

/**
 * ADMIN REPLY TO TICKET
 * POST /api/admin-reply-ticket.php
 * 
 * Required: admin_level >= 1
 * 
 * Request:
 * {
 *     "ticket_id": 123,
 *     "message": "Admin response message",
 *     "status": "resolved"  (optional: open|pending|resolved|closed)
 * }
 * 
 * Response (201):
 * {
 *     "success": true,
 *     "message": "Reply posted successfully"
 * }
 * 
 * Side effects:
 *   - Adds to ticket_replies
 *   - Updates ticket status if provided
 *   - Notifies ticket creator
 */

// ============================================================================
// COMPETITIONS
// ============================================================================

/**
 * CREATE COMPETITION
 * POST /api/create-competition.php
 * 
 * Required: admin_level >= 1
 * 
 * Request:
 * {
 *     "title": "Competition Name",
 *     "description": "Full description",
 *     "category": "genre/category",
 *     "start_date": "2025-12-01 00:00:00",
 *     "end_date": "2025-12-31 23:59:59",
 *     "entry_limit": 100,
 *     "prize_pool": 1000.00,
 *     "rules": "Competition rules...",
 *     "status": "draft|active|closed|completed"
 * }
 * 
 * Response (201):
 * {
 *     "success": true,
 *     "competition_id": 42
 * }
 * 
 * Side effects:
 *   - If status='active', all users notified
 */

/**
 * SUBMIT STORY TO COMPETITION
 * POST /api/submit-competition-entry.php
 * 
 * Required: user must be logged in, story must be theirs
 * 
 * Request:
 * {
 *     "competition_id": 42,
 *     "story_id": 15
 * }
 * 
 * Response (201):
 * {
 *     "success": true,
 *     "entry_id": 234
 * }
 * 
 * Validations:
 *   - Competition must be active/closed
 *   - Entry count < entry_limit
 *   - Story must belong to user
 *   - Can't submit same story twice
 * 
 * Errors:
 *   - 404: Competition not found/not active
 *   - 400: Entry limit reached OR duplicate entry
 *   - 403: Story not found or not yours
 */

// ============================================================================
// FORUM MODERATION
// ============================================================================

/**
 * MODERATE FORUM POST
 * POST /api/moderate-forum-post.php
 * 
 * Required: admin_level >= 2
 * 
 * Request:
 * {
 *     "post_id": 789,
 *     "action": "warn|delete|edit|suspend|restore",
 *     "reason": "Moderation reason",
 *     "notes": "Optional notes",
 *     "content": "New content"  (only for action='edit')
 * }
 * 
 * Response (200):
 * {
 *     "success": true,
 *     "action": "suspend"
 * }
 * 
 * Actions:
 * 
 *   - warn: Issue warning (creates user_warnings entry)
 *   - delete: Mark post deleted (status='deleted')
 *   - edit: Update post content, mark as edited by mod
 *   - suspend: Temporary ban + mark suspended (7 days)
 *   - restore: Restore deleted/suspended post
 * 
 * Side effects:
 *   - Logs in forum_moderation table
 *   - Creates user_warnings (warn/suspend)
 *   - Sends notification to affected user
 *   - Updates forum_posts status
 */

// ============================================================================
// ERROR RESPONSES
// ============================================================================

/**
 * All endpoints return errors in this format:
 * 
 * {
 *     "success": false,
 *     "error": "Error message"
 * }
 * 
 * HTTP Status Codes:
 *   - 200/201: Success
 *   - 400: Bad request (validation failed)
 *   - 401: Unauthorized (not logged in)
 *   - 403: Forbidden (insufficient permissions)
 *   - 404: Not found
 *   - 500: Server error
 */

// ============================================================================
// AUTHENTICATION
// ============================================================================

/**
 * Admin Levels:
 *   - 0: Regular user
 *   - 1: Basic admin (support tickets, competitions)
 *   - 2: Moderator (forum moderation)
 *   - 3+: Super admin
 * 
 * All endpoints check $_SESSION['user_id']
 * Admin endpoints also check $_SESSION['admin_level']
 */

// ============================================================================
// USAGE EXAMPLE
// ============================================================================

/*
// Create support ticket
fetch('/api/create-support-ticket.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        subject: 'Bug in chapter display',
        description: 'Chapters not loading properly',
        category: 'bug',
        priority: 'high'
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        console.log('Ticket created:', data.ticket_id);
    } else {
        console.error(data.error);
    }
});

// Admin replies to ticket
fetch('/api/admin-reply-ticket.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        ticket_id: 123,
        message: 'We have fixed this issue',
        status: 'resolved'
    })
})
.then(r => r.json())
.then(data => console.log(data.success ? 'Reply sent' : data.error));

// User submits to competition
fetch('/api/submit-competition-entry.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        competition_id: 42,
        story_id: 15
    })
})
.then(r => r.json())
.then(data => console.log(data.success ? 'Submitted!' : data.error));
*/
?>
