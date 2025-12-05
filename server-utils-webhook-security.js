// server/utils/webhook-security.js - Patreon Webhook Verification & Security

const crypto = require('crypto');

/**
 * Verify Patreon webhook signature
 * @param {string} body - Raw request body as string
 * @param {string} signature - X-Patreon-Signature header value
 * @returns {boolean}
 */
function verifyPatreonSignature(body, signature) {
  const secret = process.env.PATREON_WEBHOOK_SECRET;
  if (!secret || !signature) return false;

  // Patreon uses MD5 hash of (body + secret)
  const computedHash = crypto
    .createHmac('md5', secret)
    .update(body)
    .digest('hex');

  return computedHash === signature;
}

/**
 * Validate webhook payload structure
 * @param {object} data - Webhook data object
 * @returns {object} { valid: boolean, error?: string }
 */
function validateWebhookPayload(data) {
  if (!data || typeof data !== 'object') {
    return { valid: false, error: 'Invalid payload structure' };
  }

  const { type, data: payload, included } = data;

  if (!type) {
    return { valid: false, error: 'Missing event type' };
  }

  // Allowed event types
  const validTypes = [
    'members:pledge:create',
    'members:pledge:update',
    'members:pledge:delete'
  ];

  if (!validTypes.includes(type)) {
    return { valid: false, error: `Unknown event type: ${type}` };
  }

  if (!payload || typeof payload !== 'object') {
    return { valid: false, error: 'Missing event data payload' };
  }

  return { valid: true };
}

/**
 * Check for duplicate webhook events (idempotency)
 * @param {string} eventId - Unique event identifier
 * @param {object} client - PostgreSQL client
 * @returns {Promise<boolean>} true if already processed
 */
async function isWebhookProcessed(eventId, client) {
  const result = await client.query(
    `SELECT processed FROM patreon_webhook_events WHERE event_id = $1`,
    [eventId]
  );

  return result.rows.length > 0 && result.rows[0].processed;
}

/**
 * Mark webhook as processed (idempotency key)
 * @param {string} eventId - Unique event identifier
 * @param {object} client - PostgreSQL client
 */
async function markWebhookProcessed(eventId, client) {
  await client.query(
    `INSERT INTO patreon_webhook_events (event_id, processed)
     VALUES ($1, true)
     ON CONFLICT (event_id) DO UPDATE SET processed = true, updated_at = now()`,
    [eventId]
  );
}

/**
 * Edge Case Handlers
 */

/**
 * Handle fraud/chargeback: Reverse all points and mark as disputed
 * @param {string} userId - User ID
 * @param {object} client - PostgreSQL client
 * @param {string} reason - Dispute reason
 */
async function handleChargebackFraud(userId, client, reason) {
  const pointsResult = await client.query(
    `SELECT total_points FROM user_points_balance WHERE user_id = $1`,
    [userId]
  );

  if (pointsResult.rows.length === 0) return;

  const totalPoints = pointsResult.rows[0].total_points;

  // Reverse all points
  await client.query(
    `UPDATE user_points_balance SET total_points = 0, patreon_points = 0 WHERE user_id = $1`,
    [userId]
  );

  // Record reversal transaction
  await client.query(
    `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
     VALUES ($1, $2, 'fraud_reversal', 'system', $3)`,
    [userId, -totalPoints, JSON.stringify({ reason, timestamp: new Date() })]
  );

  // Deactivate all Patreon links
  await client.query(
    `UPDATE patreon_links SET active = false WHERE user_id = $1`,
    [userId]
  );

  // Mark user account for review
  await client.query(
    `INSERT INTO admin_actions (admin_user_id, action_type, target_type, target_id, metadata)
     VALUES (NULL, 'fraud_detected', 'user', $1, $2)`,
    [userId, JSON.stringify({ reason, points_reversed: totalPoints })]
  );

  console.log(`[Fraud Handler] Reversed ${totalPoints} points for user ${userId}`);
}

/**
 * Handle partial refund with prorating
 * @param {string} userId - User ID
 * @param {number} refundAmount - Amount to refund in cents
 * @param {object} client - PostgreSQL client
 */
async function handlePartialRefund(userId, refundAmount, client) {
  // Get original pledge amount from patreon_links
  const linkResult = await client.query(
    `SELECT id, tier_id FROM patreon_links WHERE user_id = $1 AND active = true`,
    [userId]
  );

  if (linkResult.rows.length === 0) return;

  const tierResult = await client.query(
    `SELECT monthly_points FROM patreon_tier_config WHERE tier_id = $1`,
    [linkResult.rows[0].tier_id]
  );

  if (tierResult.rows.length === 0) return;

  const monthlyPoints = tierResult.rows[0].monthly_points;
  const refundPercentage = refundAmount / (process.env.PATREON_BASE_MONTHLY_CENTS || 100);
  const refundPoints = Math.round(monthlyPoints * refundPercentage);

  if (refundPoints > 0) {
    // Refund points proportionally
    await client.query(
      `UPDATE user_points_balance
       SET total_points = CASE WHEN $1 > total_points THEN 0 ELSE total_points - $1 END,
           patreon_points = CASE WHEN $1 > patreon_points THEN 0 ELSE patreon_points - $1 END
       WHERE user_id = $2`,
      [refundPoints, userId]
    );

    await client.query(
      `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
       VALUES ($1, $2, 'partial_refund', 'patreon', $3)`,
      [userId, -refundPoints, JSON.stringify({ refund_cents: refundAmount, refund_percentage: refundPercentage })]
    );
  }
}

/**
 * Handle tier upgrade with proration
 * @param {string} userId - User ID
 * @param {string} newTierId - New tier ID
 * @param {object} client - PostgreSQL client
 */
async function handleTierUpgrade(userId, newTierId, client) {
  const linkResult = await client.query(
    `SELECT id, tier_id, next_reward_date FROM patreon_links WHERE user_id = $1 AND active = true`,
    [userId]
  );

  if (linkResult.rows.length === 0) return;

  const oldTierId = linkResult.rows[0].tier_id;
  const nextRewardDate = linkResult.rows[0].next_reward_date;

  // Get tier configs
  const tierConfigResult = await client.query(
    `SELECT tier_id, monthly_points FROM patreon_tier_config 
     WHERE tier_id IN ($1, $2)`,
    [oldTierId, newTierId]
  );

  const tierMap = {};
  tierConfigResult.rows.forEach(row => {
    tierMap[row.tier_id] = row.monthly_points;
  });

  if (!tierMap[oldTierId] || !tierMap[newTierId]) {
    console.error(`[Tier Upgrade] Missing tier config for ${oldTierId} or ${newTierId}`);
    return;
  }

  const oldPoints = tierMap[oldTierId];
  const newPoints = tierMap[newTierId];
  const pointDifference = newPoints - oldPoints;

  if (pointDifference > 0) {
    // Pro-rata bonus for partial month
    const daysUntilReward = Math.ceil((nextRewardDate - new Date()) / (24 * 60 * 60 * 1000));
    const daysInMonth = 30;
    const proRataBonus = Math.round((pointDifference * daysUntilReward) / daysInMonth);

    // Award pro-rata bonus
    await client.query(
      `UPDATE user_points_balance
       SET total_points = total_points + $1,
           patreon_points = patreon_points + $1
       WHERE user_id = $2`,
      [proRataBonus, userId]
    );

    await client.query(
      `INSERT INTO points_transactions (user_id, delta, type, source, metadata)
       VALUES ($1, $2, 'tier_upgrade', 'patreon', $3)`,
      [userId, proRataBonus, JSON.stringify({ 
        old_tier: oldTierId, 
        new_tier: newTierId, 
        pro_rata_days: daysUntilReward,
        monthly_increase: pointDifference
      })]
    );

    console.log(`[Tier Upgrade] Granted ${proRataBonus} pro-rata points to user ${userId}`);
  }

  // Update patreon_links tier
  await client.query(
    `UPDATE patreon_links SET tier_id = $1 WHERE user_id = $2`,
    [newTierId, userId]
  );
}

/**
 * Handle tier downgrade
 * @param {string} userId - User ID
 * @param {string} newTierId - New tier ID
 * @param {object} client - PostgreSQL client
 */
async function handleTierDowngrade(userId, newTierId, client) {
  const linkResult = await client.query(
    `SELECT id, tier_id FROM patreon_links WHERE user_id = $1 AND active = true`,
    [userId]
  );

  if (linkResult.rows.length === 0) return;

  // No pro-rata bonus on downgrade, just update tier for next month
  await client.query(
    `UPDATE patreon_links SET tier_id = $1 WHERE user_id = $2`,
    [newTierId, userId]
  );

  console.log(`[Tier Downgrade] Updated tier for user ${userId}`);
}

/**
 * Handle reactivation after cancellation
 * @param {string} userId - User ID
 * @param {string} tierId - Patreon tier ID
 * @param {object} client - PostgreSQL client
 */
async function handleReactivation(userId, tierId, client) {
  const linkResult = await client.query(
    `SELECT id FROM patreon_links WHERE user_id = $1 AND patreon_user_id = $2`,
    [userId, tierId]
  );

  if (linkResult.rows.length === 0) return;

  // Reactivate link
  await client.query(
    `UPDATE patreon_links 
     SET active = true, reactivated_at = now(), next_reward_date = now()
     WHERE user_id = $1`,
    [userId]
  );

  console.log(`[Reactivation] User ${userId} reactivated Patreon link`);
}

module.exports = {
  verifyPatreonSignature,
  validateWebhookPayload,
  isWebhookProcessed,
  markWebhookProcessed,
  handleChargebackFraud,
  handlePartialRefund,
  handleTierUpgrade,
  handleTierDowngrade,
  handleReactivation
};
