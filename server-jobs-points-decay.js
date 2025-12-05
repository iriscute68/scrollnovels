// server/jobs/points-decay.js - Weekly Point Decay Processing

const { pool } = require('../db');

/**
 * Process point decay (20% per week, expires after 4 weeks)
 * Runs weekly on Monday
 */
async function processPointDecay() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    const now = new Date();
    let totalDecayed = 0;
    let totalExpired = 0;

    // Find all unexpired points
    const pointsResult = await client.query(
      `SELECT id, user_id, points, expires_at, decay_percentage
       FROM point_expiry_schedule
       WHERE expires_at > now()`
    );

    for (const point of pointsResult.rows) {
      const { id, user_id, points, expires_at, decay_percentage } = point;

      // Calculate weeks since creation
      const decayRecord = await client.query(
        `SELECT original_points, created_at FROM point_expiry_schedule WHERE id = $1`,
        [id]
      );

      if (decayRecord.rows.length === 0) continue;

      const { original_points, created_at } = decayRecord.rows[0];
      const createdDate = new Date(created_at);
      const weeksSinceCreation = Math.floor((now - createdDate) / (7 * 24 * 60 * 60 * 1000));

      // Apply 20% decay per week
      const decayMultiplier = Math.pow(0.8, weeksSinceCreation);
      const newPoints = Math.round(original_points * decayMultiplier);
      const pointsLost = points - newPoints;

      if (weeksSinceCreation >= 4) {
        // Points expired - remove from balance
        await client.query(
          `UPDATE user_points_balance
           SET free_points = CASE WHEN $1 > free_points THEN 0 ELSE free_points - $1 END,
               total_points = CASE WHEN $1 > total_points THEN 0 ELSE total_points - $1 END
           WHERE user_id = $2`,
          [points, user_id]
        );

        // Log expiration transaction
        await client.query(
          `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
           VALUES ($1, $2, 'expired', 'decay', $3)`,
          [user_id, -points, JSON.stringify({ schedule_id: id, reason: 'point_expiration' })]
        );

        // Delete from schedule
        await client.query(
          `DELETE FROM point_expiry_schedule WHERE id = $1`,
          [id]
        );

        totalExpired += points;
      } else if (pointsLost > 0) {
        // Apply decay
        await client.query(
          `UPDATE user_points_balance
           SET free_points = CASE WHEN $1 > free_points THEN 0 ELSE free_points - $1 END,
               total_points = CASE WHEN $1 > total_points THEN 0 ELSE total_points - $1 END
           WHERE user_id = $2`,
          [pointsLost, user_id]
        );

        // Log decay transaction
        await client.query(
          `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
           VALUES ($1, $2, 'decayed', 'decay', $3)`,
          [user_id, -pointsLost, JSON.stringify({ 
            schedule_id: id, 
            original_points, 
            week: weeksSinceCreation + 1,
            decay_percentage: Math.round((pointsLost / points) * 100) 
          })]
        );

        // Update schedule
        await client.query(
          `UPDATE point_expiry_schedule
           SET points = $1, decay_percentage = $2
           WHERE id = $3`,
          [newPoints, Math.round((1 - decayMultiplier) * 100), id]
        );

        totalDecayed += pointsLost;
      }
    }

    await client.query('COMMIT');

    console.log(`Point decay processed: ${totalDecayed} decayed, ${totalExpired} expired`);
    return { decayed: totalDecayed, expired: totalExpired };
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Error processing point decay:', err);
    throw err;
  } finally {
    client.release();
  }
}

module.exports = { processPointDecay };
