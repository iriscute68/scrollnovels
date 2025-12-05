// server/routes/admin.js - Admin Dashboard & Management Endpoints

const express = require('express');
const router = express.Router();
const { pool } = require('../db');
const { authMiddleware } = require('../middleware/auth');

// Admin-only middleware with optional 2FA check
const adminOnly = (req, res, next) => {
  if (req.user?.role !== 'admin') {
    return res.status(403).json({ error: 'Unauthorized - admin only' });
  }
  next();
};

/**
 * GET /api/v1/admin/dashboard
 * Get overview stats for admin dashboard
 */
router.get('/admin/dashboard', authMiddleware, adminOnly, async (req, res) => {
  try {
    const client = await pool.connect();
    try {
      await client.query('BEGIN');

      // Total points distributed (all types)
      const totalPointsResult = await client.query(
        `SELECT 
           SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END) as total_distributed,
           SUM(CASE WHEN type = 'patreon_reward' THEN delta ELSE 0 END) as patreon_distributed,
           SUM(CASE WHEN type = 'spent' THEN ABS(delta) ELSE 0 END) as total_spent
         FROM points_transactions`
      );

      // Active Patreon patrons this month
      const patreonResult = await client.query(
        `SELECT 
           COUNT(*) as active_patrons,
           SUM(pledge_amount_cents) as total_pledge_cents
         FROM patreon_links
         WHERE active = true AND patron_status = 'active_patron'`
      );

      // Monthly Patreon credits issued
      const monthlyRewardsResult = await client.query(
        `SELECT 
           COUNT(DISTINCT user_id) as users_rewarded,
           SUM(delta) as total_points_issued
         FROM points_transactions
         WHERE type = 'patreon_reward' 
           AND created_at >= now() - interval '30 days'`
      );

      // Top supported books this month
      const topBooksResult = await client.query(
        `SELECT 
           b.id, b.title, b.slug,
           COUNT(DISTINCT bs.user_id) as supporter_count,
           SUM(bs.effective_points) as total_support
         FROM book_support bs
         JOIN books b ON bs.book_id = b.id
         WHERE bs.created_at >= now() - interval '30 days'
         GROUP BY b.id, b.title, b.slug
         ORDER BY total_support DESC
         LIMIT 5`
      );

      // Active users (past 7 days)
      const activeUsersResult = await pool.query(
        `SELECT COUNT(DISTINCT user_id) as active_users
         FROM points_transactions
         WHERE created_at >= now() - interval '7 days'`
      );

      await client.query('COMMIT');

      res.json({
        success: true,
        data: {
          overview: {
            total_points_distributed: totalPointsResult.rows[0].total_distributed || 0,
            patreon_distributed: totalPointsResult.rows[0].patreon_distributed || 0,
            total_spent: totalPointsResult.rows[0].total_spent || 0
          },
          patreon: {
            active_patrons: patreonResult.rows[0].active_patrons || 0,
            total_pledge_cents: patreonResult.rows[0].total_pledge_cents || 0,
            monthly_rewards: {
              users_rewarded: monthlyRewardsResult.rows[0].users_rewarded || 0,
              total_points_issued: monthlyRewardsResult.rows[0].total_points_issued || 0
            }
          },
          top_books: topBooksResult.rows,
          active_users: activeUsersResult.rows[0].active_users || 0
        }
      });
    } finally {
      client.release();
    }
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get dashboard stats' });
  }
});

/**
 * GET /api/v1/admin/patreon-links?limit=50&offset=0&active=true
 * List Patreon links with filtering
 */
router.get('/admin/patreon-links', authMiddleware, adminOnly, async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 50, 200);
    const offset = parseInt(req.query.offset) || 0;
    const activeOnly = req.query.active === 'true';

    let whereClause = '';
    const params = [];

    if (activeOnly) {
      whereClause = 'WHERE pl.active = true';
    }

    const result = await pool.query(
      `SELECT 
         pl.id, pl.user_id,
         u.username, u.email,
         pl.patreon_user_id, pl.tier_name, pl.pledge_amount_cents,
         pl.patron_status, pl.active,
         pl.last_reward_date, pl.next_reward_date,
         pl.created_at, pl.updated_at,
         COUNT(bs.id) as support_count,
         SUM(bs.effective_points) as total_points_spent
       FROM patreon_links pl
       LEFT JOIN users u ON pl.user_id = u.id
       LEFT JOIN book_support bs ON u.id = bs.user_id AND bs.created_at >= now() - interval '30 days'
       ${whereClause}
       GROUP BY pl.id, u.id, u.username, u.email
       ORDER BY pl.created_at DESC
       LIMIT $${whereClause ? '1' : ''} OFFSET $${whereClause ? '2' : '1'}`,
      whereClause ? [limit, offset] : [limit, offset]
    );

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM patreon_links ${whereClause}`
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
    res.status(500).json({ error: 'Failed to get Patreon links' });
  }
});

/**
 * POST /api/v1/admin/patreon-links/:id/unlink
 * Unlink Patreon account
 */
router.post('/admin/patreon-links/:id/unlink', authMiddleware, adminOnly, async (req, res) => {
  const { id } = req.params;
  const { reason } = req.body;

  try {
    const client = await pool.connect();
    try {
      await client.query('BEGIN');

      const result = await client.query(
        `UPDATE patreon_links
         SET active = false, updated_at = now()
         WHERE id = $1
         RETURNING user_id`,
        [id]
      );

      if (result.rows.length === 0) {
        await client.query('ROLLBACK');
        return res.status(404).json({ error: 'Patreon link not found' });
      }

      const userId = result.rows[0].user_id;

      // Log admin action
      await client.query(
        `INSERT INTO admin_actions (admin_id, action, target_id, target_type, details)
         VALUES ($1, 'patreon_unlink', $2, 'user', $3)`,
        [req.user.id, userId, JSON.stringify({ reason })]
      );

      await client.query('COMMIT');

      res.json({
        success: true,
        message: 'Patreon link deactivated'
      });
    } catch (err) {
      await client.query('ROLLBACK');
      throw err;
    } finally {
      client.release();
    }
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to unlink Patreon' });
  }
});

/**
 * GET /api/v1/admin/points-transactions?user=&type=&from=&to=&limit=100
 * View points transaction ledger with filters
 */
router.get('/admin/points-transactions', authMiddleware, adminOnly, async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 100, 500);
    const offset = parseInt(req.query.offset) || 0;
    const userId = req.query.user || null;
    const type = req.query.type || null;
    const from = req.query.from || null;
    const to = req.query.to || null;

    let whereClause = '1=1';
    const params = [];

    if (userId) {
      whereClause += ` AND user_id = $${params.length + 1}`;
      params.push(userId);
    }
    if (type) {
      whereClause += ` AND type = $${params.length + 1}`;
      params.push(type);
    }
    if (from) {
      whereClause += ` AND created_at >= $${params.length + 1}`;
      params.push(from);
    }
    if (to) {
      whereClause += ` AND created_at <= $${params.length + 1}`;
      params.push(to);
    }

    const result = await pool.query(
      `SELECT 
         pt.id, pt.user_id, u.username, u.email,
         pt.type, pt.source, pt.delta, pt.balance_after,
         pt.reference_id, pt.metadata,
         pt.created_at
       FROM points_transactions pt
       LEFT JOIN users u ON pt.user_id = u.id
       WHERE ${whereClause}
       ORDER BY pt.created_at DESC
       LIMIT $${params.length + 1} OFFSET $${params.length + 2}`,
      [...params, limit, offset]
    );

    const countParams = params.slice();
    const countResult = await pool.query(
      `SELECT COUNT(*) FROM points_transactions WHERE ${whereClause}`,
      countParams
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
 * POST /api/v1/admin/points-transactions/export
 * Export transactions to CSV
 */
router.post('/admin/points-transactions/export', authMiddleware, adminOnly, async (req, res) => {
  try {
    const { from, to, type } = req.body;

    let whereClause = '1=1';
    const params = [];

    if (type) {
      whereClause += ` AND type = $${params.length + 1}`;
      params.push(type);
    }
    if (from) {
      whereClause += ` AND created_at >= $${params.length + 1}`;
      params.push(from);
    }
    if (to) {
      whereClause += ` AND created_at <= $${params.length + 1}`;
      params.push(to);
    }

    const result = await pool.query(
      `SELECT 
         user_id, username, email, type, source, delta, balance_after, created_at
       FROM points_transactions pt
       LEFT JOIN users u ON pt.user_id = u.id
       WHERE ${whereClause}
       ORDER BY created_at DESC`,
      params
    );

    // Generate CSV
    const csv = [
      'User ID,Username,Email,Type,Source,Delta,Balance After,Created At',
      ...result.rows.map(row =>
        `${row.user_id},"${row.username}","${row.email}",${row.type},${row.source},${row.delta},${row.balance_after},"${row.created_at}"`
      )
    ].join('\n');

    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', `attachment; filename="points-ledger-${new Date().toISOString().split('T')[0]}.csv"`);
    res.send(csv);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to export' });
  }
});

/**
 * GET /api/v1/admin/book-support?limit=50&offset=0&book_id=&user_id=
 * View book support events
 */
router.get('/admin/book-support', authMiddleware, adminOnly, async (req, res) => {
  try {
    const limit = Math.min(parseInt(req.query.limit) || 50, 200);
    const offset = parseInt(req.query.offset) || 0;
    const bookId = req.query.book_id || null;
    const userId = req.query.user_id || null;

    let whereClause = '';
    const params = [];

    if (bookId) {
      whereClause += `${whereClause ? ' AND' : 'WHERE'} bs.book_id = $${params.length + 1}`;
      params.push(bookId);
    }
    if (userId) {
      whereClause += `${whereClause ? ' AND' : 'WHERE'} bs.user_id = $${params.length + 1}`;
      params.push(userId);
    }

    const result = await pool.query(
      `SELECT 
         bs.id, bs.user_id, u.username,
         bs.book_id, b.title, b.slug,
         bs.points, bs.point_type, bs.multiplier, bs.effective_points,
         bs.created_at
       FROM book_support bs
       LEFT JOIN users u ON bs.user_id = u.id
       LEFT JOIN books b ON bs.book_id = b.id
       ${whereClause}
       ORDER BY bs.created_at DESC
       LIMIT $${params.length + 1} OFFSET $${params.length + 2}`,
      [...params, limit, offset]
    );

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM book_support bs ${whereClause}`,
      params.slice(0, -2)
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
    res.status(500).json({ error: 'Failed to get book support' });
  }
});

/**
 * POST /api/v1/admin/book-support/:id/reverse
 * Reverse/refund support event
 */
router.post('/admin/book-support/:id/reverse', authMiddleware, adminOnly, async (req, res) => {
  const { id } = req.params;
  const { reason } = req.body;

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    // Get support record
    const supportResult = await client.query(
      `SELECT user_id, points, point_type FROM book_support WHERE id = $1`,
      [id]
    );

    if (supportResult.rows.length === 0) {
      await client.query('ROLLBACK');
      return res.status(404).json({ error: 'Support record not found' });
    }

    const { user_id, points } = supportResult.rows[0];

    // Refund points
    await client.query(
      `UPDATE user_points_balance
       SET ${supportResult.rows[0].point_type}_points = ${supportResult.rows[0].point_type}_points + $1,
           total_points = total_points + $2
       WHERE user_id = $3`,
      [points, points, user_id]
    );

    // Create reverse transaction
    await client.query(
      `INSERT INTO points_transactions (user_id, delta, type, source, reference_id, metadata)
       VALUES ($1, $2, 'refund', 'admin_reverse', $3, $4)`,
      [user_id, points, id, JSON.stringify({ reason, admin_id: req.user.id })]
    );

    // Mark support as reversed
    await client.query(
      `UPDATE book_support SET reversed = true, reversed_at = now() WHERE id = $1`,
      [id]
    );

    // Log admin action
    await client.query(
      `INSERT INTO admin_actions (admin_id, action, target_id, target_type, details)
       VALUES ($1, 'support_reverse', $2, 'book_support', $3)`,
      [req.user.id, id, JSON.stringify({ reason, user_id, points })]
    );

    await client.query('COMMIT');

    res.json({
      success: true,
      message: `Refunded ${points} points to user`
    });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error(err);
    res.status(500).json({ error: 'Failed to reverse support' });
  } finally {
    client.release();
  }
});

/**
 * GET /api/v1/admin/leaderboards/config
 * Get current leaderboard configuration
 */
router.get('/admin/leaderboards/config', authMiddleware, adminOnly, async (req, res) => {
  try {
    const result = await pool.query(
      `SELECT key, value FROM admin_config WHERE key LIKE 'leaderboard_%'`
    );

    const config = {};
    result.rows.forEach(row => {
      config[row.key] = row.value;
    });

    res.json({
      success: true,
      data: {
        free_multiplier: parseFloat(config.leaderboard_free_multiplier || '1.0'),
        premium_multiplier: parseFloat(config.leaderboard_premium_multiplier || '2.0'),
        patreon_multiplier: parseFloat(config.leaderboard_patreon_multiplier || '3.0'),
        decay_percentage: parseFloat(config.leaderboard_decay_percentage || '20'),
        decay_weeks: parseInt(config.leaderboard_decay_weeks || '4')
      }
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get config' });
  }
});

/**
 * POST /api/v1/admin/leaderboards/config
 * Update leaderboard multipliers and decay settings
 */
router.post('/admin/leaderboards/config', authMiddleware, adminOnly, async (req, res) => {
  const { free_multiplier, premium_multiplier, patreon_multiplier, decay_percentage, decay_weeks } = req.body;

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    const updates = [
      { key: 'leaderboard_free_multiplier', value: free_multiplier || '1.0' },
      { key: 'leaderboard_premium_multiplier', value: premium_multiplier || '2.0' },
      { key: 'leaderboard_patreon_multiplier', value: patreon_multiplier || '3.0' },
      { key: 'leaderboard_decay_percentage', value: decay_percentage || '20' },
      { key: 'leaderboard_decay_weeks', value: decay_weeks || '4' }
    ];

    for (const update of updates) {
      await client.query(
        `INSERT INTO admin_config (key, value) VALUES ($1, $2)
         ON CONFLICT (key) DO UPDATE SET value = $2, updated_at = now()`,
        [update.key, update.value]
      );
    }

    // Log config change
    await client.query(
      `INSERT INTO admin_actions (admin_id, action, target_type, details)
       VALUES ($1, 'config_update', 'leaderboard', $2)`,
      [req.user.id, JSON.stringify({ free_multiplier, premium_multiplier, patreon_multiplier, decay_percentage, decay_weeks })]
    );

    await client.query('COMMIT');

    res.json({
      success: true,
      message: 'Configuration updated',
      data: {
        free_multiplier,
        premium_multiplier,
        patreon_multiplier,
        decay_percentage,
        decay_weeks
      }
    });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error(err);
    res.status(500).json({ error: 'Failed to update config' });
  } finally {
    client.release();
  }
});

/**
 * POST /api/v1/admin/leaderboards/regenerate
 * Manually trigger leaderboard regeneration
 */
router.post('/admin/leaderboards/regenerate', authMiddleware, adminOnly, async (req, res) => {
  try {
    const client = await pool.connect();
    try {
      await client.query('BEGIN');

      // Get today's date
      const today = new Date();
      today.setUTCHours(0, 0, 0, 0);
      const dayString = today.toISOString().split('T')[0];

      // Clear existing rankings for today
      await client.query(
        `DELETE FROM book_rankings WHERE day = $1`,
        [dayString]
      );

      // Recalculate for all periods
      const periods = [
        { name: 'daily', filter: 'AND bs.created_at >= now() - interval \'1 day\'' },
        { name: 'weekly', filter: 'AND bs.created_at >= now() - interval \'7 days\'' },
        { name: 'monthly', filter: 'AND bs.created_at >= now() - interval \'30 days\'' },
        { name: 'all_time', filter: '' }
      ];

      let totalRecords = 0;

      for (const period of periods) {
        const rankingsResult = await client.query(
          `SELECT 
             b.id as book_id,
             SUM(bs.effective_points) as total_support_points,
             COUNT(DISTINCT bs.user_id) as supporter_count
           FROM book_support bs
           JOIN books b ON bs.book_id = b.id
           WHERE b.status = 'published' AND bs.reversed = false
             ${period.filter}
           GROUP BY b.id
           ORDER BY total_support_points DESC`
        );

        let rank = 1;
        for (const ranking of rankingsResult.rows) {
          await client.query(
            `INSERT INTO book_rankings (day, period, book_id, total_support_points, supporter_count, rank_position)
             VALUES ($1, $2, $3, $4, $5, $6)`,
            [dayString, period.name, ranking.book_id, ranking.total_support_points, ranking.supporter_count, rank]
          );
          rank++;
          totalRecords++;
        }
      }

      // Log admin action
      await client.query(
        `INSERT INTO admin_actions (admin_id, action, target_type, details)
         VALUES ($1, 'leaderboards_regenerate', 'rankings', $2)`,
        [req.user.id, JSON.stringify({ records_generated: totalRecords })]
      );

      await client.query('COMMIT');

      res.json({
        success: true,
        message: `Regenerated ${totalRecords} ranking records`,
        data: { records: totalRecords }
      });
    } catch (err) {
      await client.query('ROLLBACK');
      throw err;
    } finally {
      client.release();
    }
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to regenerate leaderboards' });
  }
});

module.exports = router;
