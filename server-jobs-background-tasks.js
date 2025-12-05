// server/jobs/background-tasks.js - BullMQ Worker for Background Jobs

const Queue = require('bull');
const axios = require('axios');
const { pool } = require('../db');
const { refreshPatreonToken } = require('../routes/oauth');

// Create job queues
const patreonRewardQueue = new Queue('patreon-rewards', process.env.REDIS_URL || 'redis://127.0.0.1:6379');
const leaderboardQueue = new Queue('leaderboards', process.env.REDIS_URL || 'redis://127.0.0.1:6379');
const pointDecayQueue = new Queue('point-decay', process.env.REDIS_URL || 'redis://127.0.0.1:6379');
const webhookDedupeQueue = new Queue('webhook-dedupe', process.env.REDIS_URL || 'redis://127.0.0.1:6379');

/**
 * Patreon Reward Reconciliation Worker
 * Verifies Patreon status and grants monthly points
 */
patreonRewardQueue.process(async (job) => {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    console.log(`[Patreon Rewards] Processing job: ${job.id}`);

    const now = new Date();
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

    // Find all active Patreon links that haven't been rewarded this month
    const linksResult = await client.query(
      `SELECT pl.user_id, pl.patreon_user_id, pl.tier_id, pl.access_token,
              u.email, u.username
       FROM patreon_links pl
       JOIN users u ON pl.user_id = u.id
       WHERE pl.active = true 
         AND (pl.last_reward_date IS NULL OR pl.last_reward_date < $1)
       ORDER BY pl.created_at DESC`,
      [monthStart]
    );

    let rewardsProcessed = 0;
    let errors = [];

    for (const link of linksResult.rows) {
      try {
        // Verify Patreon status via API (requires token refresh if expired)
        let accessToken = link.access_token;
        
        // Check if token is expired, refresh if needed
        try {
          const membershipResponse = await axios.get('https://www.patreon.com/api/v2/identity', {
            headers: { Authorization: `Bearer ${accessToken}` },
            params: { 'include': 'memberships' }
          });

          // Get highest active patron tier
          const memberships = membershipResponse.data.included
            ?.filter(item => item.type === 'member' && item.attributes.patron_status === 'active_patron')
            ?.sort((a, b) => b.attributes.pledge_amount_cents - a.attributes.pledge_amount_cents) || [];

          if (memberships.length === 0) {
            // No active patron found - deactivate link
            await client.query(
              `UPDATE patreon_links SET active = false WHERE user_id = $1`,
              [link.user_id]
            );
            continue;
          }

          // Get tier config for points
          const tierConfig = await client.query(
            `SELECT monthly_points FROM patreon_tier_config WHERE tier_id = $1`,
            [link.tier_id]
          );

          if (tierConfig.rows.length === 0) {
            errors.push(`User ${link.username}: Tier config not found`);
            continue;
          }

          const monthlyPoints = tierConfig.rows[0].monthly_points;

          // Award points
          await client.query(
            `INSERT INTO user_points_balance (user_id, patreon_points, total_points)
             VALUES ($1, $2, $3)
             ON CONFLICT (user_id) DO UPDATE SET
               patreon_points = patreon_points + $2,
               total_points = total_points + $3`,
            [link.user_id, monthlyPoints, monthlyPoints]
          );

          // Record transaction
          await client.query(
            `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
             VALUES ($1, $2, 'patreon_reward', $3, $4)`,
            [link.user_id, monthlyPoints, `patreon:${link.tier_id}`, 
             JSON.stringify({ reconciliation_job: true })]
          );

          // Update last_reward_date and next_reward_date
          const nextReward = new Date(monthStart);
          nextReward.setMonth(nextReward.getMonth() + 1);

          await client.query(
            `UPDATE patreon_links
             SET last_reward_date = now(), next_reward_date = $1
             WHERE user_id = $2`,
            [nextReward, link.user_id]
          );

          rewardsProcessed++;
          console.log(`[Patreon Rewards] Awarded ${monthlyPoints} points to ${link.username}`);

        } catch (apiErr) {
          if (apiErr.response?.status === 401) {
            // Token expired, attempt refresh
            const newToken = await refreshPatreonToken(link.user_id);
            if (!newToken) {
              errors.push(`User ${link.username}: Token refresh failed`);
            }
          } else {
            throw apiErr;
          }
        }

      } catch (userErr) {
        errors.push(`User ${link.username}: ${userErr.message}`);
        console.error(`[Patreon Rewards] Error processing user:`, userErr);
      }
    }

    await client.query('COMMIT');

    console.log(`[Patreon Rewards] Processed ${rewardsProcessed} rewards`);
    if (errors.length > 0) {
      console.log('[Patreon Rewards] Errors:', errors);
    }

    return {
      processed: rewardsProcessed,
      errors: errors
    };

  } catch (err) {
    await client.query('ROLLBACK');
    console.error('[Patreon Rewards] Job failed:', err);
    throw err;
  } finally {
    client.release();
  }
});

/**
 * Leaderboard Aggregation Worker
 * Generates pre-computed rankings for all periods
 */
leaderboardQueue.process(async (job) => {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    console.log(`[Leaderboards] Processing job: ${job.id}`);

    const today = new Date();
    today.setUTCHours(0, 0, 0, 0);
    const dayString = today.toISOString().split('T')[0];

    // Delete old rankings for today
    await client.query(`DELETE FROM book_rankings WHERE day = $1`, [dayString]);

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

      console.log(`[Leaderboards] Generated ${rank - 1} rankings for ${period.name}`);
    }

    await client.query('COMMIT');

    console.log(`[Leaderboards] Total records generated: ${totalRecords}`);

    return { records_generated: totalRecords };

  } catch (err) {
    await client.query('ROLLBACK');
    console.error('[Leaderboards] Job failed:', err);
    throw err;
  } finally {
    client.release();
  }
});

/**
 * Point Decay Worker
 * Applies weekly decay and marks expirations
 */
pointDecayQueue.process(async (job) => {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    console.log(`[Point Decay] Processing job: ${job.id}`);

    const now = new Date();
    let totalDecayed = 0;
    let totalExpired = 0;

    // Find all unexpired points
    const pointsResult = await client.query(
      `SELECT id, user_id, points, expires_at, decay_percentage, created_at
       FROM point_expiry_schedule
       WHERE expires_at > now()`
    );

    for (const point of pointsResult.rows) {
      const weeksSinceCreation = Math.floor((now - new Date(point.created_at)) / (7 * 24 * 60 * 60 * 1000));
      const decayMultiplier = Math.pow(0.8, weeksSinceCreation);
      const newPoints = Math.round(point.original_points * decayMultiplier);
      const pointsLost = point.points - newPoints;

      if (weeksSinceCreation >= 4) {
        // Expire points
        await client.query(
          `UPDATE user_points_balance
           SET free_points = CASE WHEN $1 > free_points THEN 0 ELSE free_points - $1 END,
               total_points = CASE WHEN $1 > total_points THEN 0 ELSE total_points - $1 END
           WHERE user_id = $2`,
          [point.points, point.user_id]
        );

        await client.query(
          `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
           VALUES ($1, $2, 'expired', 'decay', $3)`,
          [point.user_id, -point.points, JSON.stringify({ schedule_id: point.id })]
        );

        await client.query(`DELETE FROM point_expiry_schedule WHERE id = $1`, [point.id]);
        totalExpired += point.points;

      } else if (pointsLost > 0) {
        // Apply decay
        await client.query(
          `UPDATE user_points_balance
           SET free_points = CASE WHEN $1 > free_points THEN 0 ELSE free_points - $1 END,
               total_points = CASE WHEN $1 > total_points THEN 0 ELSE total_points - $1 END
           WHERE user_id = $2`,
          [pointsLost, point.user_id]
        );

        await client.query(
          `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
           VALUES ($1, $2, 'decayed', 'decay', $3)`,
          [point.user_id, -pointsLost, JSON.stringify({ schedule_id: point.id, week: weeksSinceCreation + 1 })]
        );

        await client.query(
          `UPDATE point_expiry_schedule SET points = $1, decay_percentage = $2 WHERE id = $3`,
          [newPoints, Math.round((1 - decayMultiplier) * 100), point.id]
        );

        totalDecayed += pointsLost;
      }
    }

    await client.query('COMMIT');

    console.log(`[Point Decay] Decayed: ${totalDecayed}, Expired: ${totalExpired}`);

    return { decayed: totalDecayed, expired: totalExpired };

  } catch (err) {
    await client.query('ROLLBACK');
    console.error('[Point Decay] Job failed:', err);
    throw err;
  } finally {
    client.release();
  }
});

/**
 * Webhook Deduplication Cleaner
 * Removes old processed webhooks from deduplication cache
 */
webhookDedupeQueue.process(async (job) => {
  const client = await pool.connect();
  try {
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);

    const result = await client.query(
      `DELETE FROM patreon_webhook_events 
       WHERE processed = true AND created_at < $1`,
      [thirtyDaysAgo]
    );

    console.log(`[Webhook Dedupe] Cleaned ${result.rowCount} old webhook records`);

    return { cleaned: result.rowCount };

  } catch (err) {
    console.error('[Webhook Dedupe] Cleanup failed:', err);
    throw err;
  } finally {
    client.release();
  }
});

/**
 * Schedule recurring jobs
 */
function scheduleRecurringJobs() {
  // Patreon reconciliation: daily at 12:00 AM UTC
  patreonRewardQueue.add({}, {
    repeat: { cron: '0 0 * * *' },
    removeOnComplete: true,
    removeOnFail: true
  });

  // Leaderboard aggregation: daily at 1:00 AM UTC
  leaderboardQueue.add({}, {
    repeat: { cron: '0 1 * * *' },
    removeOnComplete: true,
    removeOnFail: true
  });

  // Point decay: weekly on Monday at 12:00 AM UTC
  pointDecayQueue.add({}, {
    repeat: { cron: '0 0 * * 1' },
    removeOnComplete: true,
    removeOnFail: true
  });

  // Webhook cleanup: daily at 2:00 AM UTC
  webhookDedupeQueue.add({}, {
    repeat: { cron: '0 2 * * *' },
    removeOnComplete: true,
    removeOnFail: true
  });

  console.log('[Background Jobs] Recurring jobs scheduled');
}

/**
 * Queue event handlers
 */
patreonRewardQueue.on('completed', (job, result) => {
  console.log(`[Patreon Rewards] Job ${job.id} completed:`, result);
});

patreonRewardQueue.on('failed', (job, err) => {
  console.error(`[Patreon Rewards] Job ${job.id} failed:`, err.message);
});

leaderboardQueue.on('completed', (job, result) => {
  console.log(`[Leaderboards] Job ${job.id} completed:`, result);
});

leaderboardQueue.on('failed', (job, err) => {
  console.error(`[Leaderboards] Job ${job.id} failed:`, err.message);
});

module.exports = {
  patreonRewardQueue,
  leaderboardQueue,
  pointDecayQueue,
  webhookDedupeQueue,
  scheduleRecurringJobs
};
