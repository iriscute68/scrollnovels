// server/jobs/rankings.js - Daily Rankings Aggregation

const { pool } = require('../db');

/**
 * Aggregate book rankings for all periods
 * Pre-calculates rankings to enable fast leaderboard queries
 */
async function aggregateBookRankings() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    const today = new Date();
    today.setUTCHours(0, 0, 0, 0);
    const dayString = today.toISOString().split('T')[0];

    const periods = [
      { name: 'daily', filter: 'AND bs.created_at >= now() - interval \'1 day\'' },
      { name: 'weekly', filter: 'AND bs.created_at >= now() - interval \'7 days\'' },
      { name: 'monthly', filter: 'AND bs.created_at >= now() - interval \'30 days\'' },
      { name: 'all_time', filter: '' }
    ];

    let totalAggregated = 0;

    for (const period of periods) {
      // Get top books for period
      const rankingsResult = await client.query(
        `SELECT 
           b.id as book_id,
           SUM(bs.effective_points) as total_support_points,
           COUNT(DISTINCT bs.user_id) as supporter_count
         FROM book_support bs
         JOIN books b ON bs.book_id = b.id
         WHERE b.status = 'published'
           ${period.filter}
         GROUP BY b.id
         ORDER BY total_support_points DESC`
      );

      // Insert or update rankings
      let rank = 1;
      for (const ranking of rankingsResult.rows) {
        const { book_id, total_support_points, supporter_count } = ranking;

        await client.query(
          `INSERT INTO book_rankings 
           (day, period, book_id, total_support_points, supporter_count, rank_position)
           VALUES ($1, $2, $3, $4, $5, $6)
           ON CONFLICT (day, period, book_id) DO UPDATE SET
             total_support_points = $4,
             supporter_count = $5,
             rank_position = $6`,
          [dayString, period.name, book_id, total_support_points, supporter_count, rank]
        );

        rank++;
        totalAggregated++;
      }
    }

    await client.query('COMMIT');

    console.log(`Rankings aggregated for ${dayString}: ${totalAggregated} entries`);
    return totalAggregated;
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Error aggregating rankings:', err);
    throw err;
  } finally {
    client.release();
  }
}

/**
 * Get rankings for a specific period
 * This is called from API to retrieve pre-aggregated data
 */
async function getRankings(period = 'weekly', limit = 50) {
  try {
    const today = new Date();
    today.setUTCHours(0, 0, 0, 0);
    const dayString = today.toISOString().split('T')[0];

    const result = await pool.query(
      `SELECT 
         br.rank_position as rank,
         b.id,
         b.title,
         b.slug,
         b.cover_url,
         u.id as author_id,
         u.username as author,
         br.supporter_count,
         br.total_support_points
       FROM book_rankings br
       JOIN books b ON br.book_id = b.id
       LEFT JOIN users u ON b.author_id = u.id
       WHERE br.day = $1 AND br.period = $2
       ORDER BY br.rank_position ASC
       LIMIT $3`,
      [dayString, period, limit]
    );

    return result.rows;
  } catch (err) {
    console.error('Error getting rankings:', err);
    return [];
  }
}

module.exports = { aggregateBookRankings, getRankings };
