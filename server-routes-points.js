// server/routes/points.js - Core Points API Endpoints

const express = require('express');
const router = express.Router();
const { pool } = require('../db');
const { authMiddleware } = require('../middleware/auth');

/**
 * GET /api/v1/me/points
 * Get current user's point balances
 */
router.get('/me/points', authMiddleware, async (req, res) => {
  try {
    const result = await pool.query(
      `SELECT * FROM user_points_balance WHERE user_id = $1`,
      [req.user.id]
    );
    
    const balance = result.rows[0] || {
      user_id: req.user.id,
      free_points: 0,
      premium_points: 0,
      patreon_points: 0,
      total_points: 0
    };

    res.json({
      success: true,
      data: {
        free_points: balance.free_points,
        premium_points: balance.premium_points,
        patreon_points: balance.patreon_points,
        total_points: balance.total_points
      }
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get points balance' });
  }
});

/**
 * GET /api/v1/me/points/transactions?limit=20&offset=0
 * Get user's point transaction history
 */
router.get('/me/points/transactions', authMiddleware, async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 20, 100);
    const offset = parseInt(req.query.offset) || 0;

    const result = await pool.query(
      `SELECT id, type, source, delta, balance_after, metadata, created_at
       FROM points_transactions
       WHERE user_id = $1
       ORDER BY created_at DESC
       LIMIT $2 OFFSET $3`,
      [req.user.id, limit, offset]
    );

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM points_transactions WHERE user_id = $1`,
      [req.user.id]
    );

    res.json({
      success: true,
      data: result.rows,
      pagination: {
        total: parseInt(countResult.rows[0].count),
        limit,
        offset
      }
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get transactions' });
  }
});

/**
 * POST /api/v1/books/:bookId/support
 * Support a book with points
 */
router.post('/books/:bookId/support', authMiddleware, async (req, res) => {
  const { bookId } = req.params;
  const { points, point_type } = req.body;

  if (!points || !point_type) {
    return res.status(400).json({ error: 'Missing points or point_type' });
  }

  if (!['free', 'premium', 'patreon'].includes(point_type)) {
    return res.status(400).json({ error: 'Invalid point_type' });
  }

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    // Get user's current balance
    const balanceResult = await client.query(
      `SELECT * FROM user_points_balance WHERE user_id = $1`,
      [req.user.id]
    );
    const balance = balanceResult.rows[0];

    if (!balance) {
      // Create if doesn't exist
      await client.query(
        `INSERT INTO user_points_balance (user_id) VALUES ($1)`,
        [req.user.id]
      );
    }

    const pointsColumn = `${point_type}_points`;
    const availablePoints = balance ? balance[pointsColumn] : 0;

    if (availablePoints < points) {
      await client.query('ROLLBACK');
      return res.status(400).json({ 
        error: 'Insufficient points',
        available: availablePoints,
        required: points
      });
    }

    // Determine multiplier
    const multipliers = { free: 1.0, premium: 2.0, patreon: 3.0 };
    const multiplier = multipliers[point_type];
    const effective_points = Math.round(points * multiplier);

    // Create book_support record
    const supportResult = await client.query(
      `INSERT INTO book_support (user_id, book_id, points, point_type, multiplier, effective_points)
       VALUES ($1, $2, $3, $4, $5, $6)
       RETURNING id`,
      [req.user.id, bookId, points, point_type, multiplier, effective_points]
    );
    const supportId = supportResult.rows[0].id;

    // Deduct points from user_points_balance
    const updateResult = await client.query(
      `UPDATE user_points_balance
       SET ${pointsColumn} = ${pointsColumn} - $1,
           total_points = total_points - $2,
           updated_at = now()
       WHERE user_id = $3
       RETURNING *`,
      [points, points, req.user.id]
    );
    const newBalance = updateResult.rows[0];

    // Create points_transaction record (negative delta = spent)
    await client.query(
      `INSERT INTO points_transactions (user_id, delta, balance_after, type, source, reference_id, metadata)
       VALUES ($1, $2, $3, $4, $5, $6, $7)`,
      [req.user.id, -points, newBalance.total_points, 'spent', `book_support:${bookId}`, supportId, { multiplier }]
    );

    await client.query('COMMIT');

    res.json({
      success: true,
      message: `Supported with ${points} ${point_type} points!`,
      data: {
        support_id: supportId,
        effective_points,
        new_balance: {
          free_points: newBalance.free_points,
          premium_points: newBalance.premium_points,
          patreon_points: newBalance.patreon_points,
          total_points: newBalance.total_points
        }
      }
    });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error(err);
    res.status(500).json({ error: 'Failed to support book' });
  } finally {
    client.release();
  }
});

/**
 * GET /api/v1/books/:bookId/supports?limit=10&offset=0
 * Get top supporters of a book
 */
router.get('/books/:bookId/supports', async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 10, 50);
    const offset = parseInt(req.query.offset) || 0;
    const { bookId } = req.params;

    const result = await pool.query(
      `SELECT 
         u.id,
         u.username,
         u.profile_image,
         COUNT(bs.id) as support_count,
         SUM(bs.effective_points) as total_support_points
       FROM book_support bs
       JOIN users u ON bs.user_id = u.id
       WHERE bs.book_id = $1
       GROUP BY u.id, u.username, u.profile_image
       ORDER BY total_support_points DESC
       LIMIT $2 OFFSET $3`,
      [bookId, limit, offset]
    );

    res.json({
      success: true,
      data: result.rows
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get supporters' });
  }
});

/**
 * GET /api/v1/rankings?period=weekly&limit=50
 * Get book rankings by period
 */
router.get('/rankings', async (req, res) => {
  try {
    const period = req.query.period || 'weekly'; // daily, weekly, monthly, all_time
    const limit = Math.min(parseInt(req.query.limit) || 50, 200);

    // For all_time, use all records; for others, use recent period
    let dateFilter = '';
    if (period === 'daily') {
      dateFilter = `AND bs.created_at >= now() - interval '1 day'`;
    } else if (period === 'weekly') {
      dateFilter = `AND bs.created_at >= now() - interval '7 days'`;
    } else if (period === 'monthly') {
      dateFilter = `AND bs.created_at >= now() - interval '30 days'`;
    }

    const result = await pool.query(
      `SELECT 
         ROW_NUMBER() OVER (ORDER BY SUM(bs.effective_points) DESC) as rank,
         b.id,
         b.title,
         b.slug,
         b.cover_url,
         u.id as author_id,
         u.username as author,
         COUNT(DISTINCT bs.user_id) as supporter_count,
         SUM(bs.effective_points) as total_support_points
       FROM book_support bs
       JOIN books b ON bs.book_id = b.id
       LEFT JOIN users u ON b.author_id = u.id
       WHERE b.status = 'published' ${dateFilter}
       GROUP BY b.id, b.title, b.slug, b.cover_url, u.id, u.username
       ORDER BY total_support_points DESC, supporter_count DESC
       LIMIT $1`,
      [limit]
    );

    res.json({
      success: true,
      period,
      data: result.rows
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get rankings' });
  }
});

/**
 * POST /api/v1/admin/users/:userId/adjust-points (ADMIN ONLY)
 * Admin endpoint to manually adjust user points
 */
router.post('/admin/users/:userId/adjust-points', authMiddleware, async (req, res) => {
  // Check if user is admin
  if (req.user.role !== 'admin') {
    return res.status(403).json({ error: 'Unauthorized' });
  }

  const { userId } = req.params;
  const { delta, point_category, reason } = req.body;

  if (!delta || !point_category || !reason) {
    return res.status(400).json({ error: 'Missing delta, point_category, or reason' });
  }

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    // Update balance
    const updateResult = await client.query(
      `UPDATE user_points_balance
       SET ${point_category}_points = ${point_category}_points + $1,
           total_points = total_points + $2,
           updated_at = now()
       WHERE user_id = $3
       RETURNING *`,
      [delta, delta, userId]
    );

    if (updateResult.rows.length === 0) {
      // Create if doesn't exist
      await client.query(
        `INSERT INTO user_points_balance (user_id, ${point_category}_points, total_points)
         VALUES ($1, $2, $3)`,
        [userId, Math.max(0, delta), Math.max(0, delta)]
      );
    }

    const balance = updateResult.rows[0];

    // Log transaction
    await client.query(
      `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
       VALUES ($1, $2, 'admin_adjust', 'admin', $3)`,
      [userId, delta, JSON.stringify({ reason, adjusted_by: req.user.id })]
    );

    await client.query('COMMIT');

    res.json({
      success: true,
      message: `Adjusted ${delta} ${point_category} points`,
      new_balance: balance
    });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error(err);
    res.status(500).json({ error: 'Failed to adjust points' });
  } finally {
    client.release();
  }
});

module.exports = router;
