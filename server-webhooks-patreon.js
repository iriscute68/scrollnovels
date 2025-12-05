// server/webhooks/patreon.js - Patreon Webhook Handler

const crypto = require('crypto');
const { pool } = require('../db');

const PATREON_WEBHOOK_SECRET = process.env.PATREON_WEBHOOK_SECRET;

/**
 * Verify Patreon webhook signature
 * Patreon uses HMAC-SHA256 with header: X-Patreon-Signature
 */
function verifyPatreonSignature(payload, signature) {
  if (!PATREON_WEBHOOK_SECRET) {
    console.warn('PATREON_WEBHOOK_SECRET not configured');
    return false;
  }

  const hash = crypto
    .createHmac('sha256', PATREON_WEBHOOK_SECRET)
    .update(payload)
    .digest('hex');

  return crypto.timingSafeEqual(hash, signature);
}

/**
 * Handle Patreon webhook events
 * Called from: POST /webhooks/patreon
 */
async function handlePatreonWebhook(req, res) {
  const signature = req.headers['x-patreon-signature'];
  const payload = req.body;
  
  if (!signature) {
    return res.status(400).json({ error: 'Missing signature' });
  }

  // Verify signature
  const rawBody = JSON.stringify(payload);
  if (!verifyPatreonSignature(rawBody, signature)) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  const event = payload?.data?.type;
  const idempotencyKey = payload?.data?.id || `${event}-${Date.now()}`;

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    // Check for duplicate (idempotency)
    const existingEvent = await client.query(
      `SELECT id FROM patreon_webhook_events WHERE idempotency_key = $1`,
      [idempotencyKey]
    );

    if (existingEvent.rows.length > 0) {
      console.log(`Duplicate webhook event (idempotency_key: ${idempotencyKey}), skipping`);
      await client.query('ROLLBACK');
      return res.json({ success: true, message: 'Event already processed' });
    }

    // Log webhook event
    const eventResult = await client.query(
      `INSERT INTO patreon_webhook_events (event_type, payload, idempotency_key)
       VALUES ($1, $2, $3)
       RETURNING id`,
      [event, JSON.stringify(payload), idempotencyKey]
    );
    const webhookEventId = eventResult.rows[0].id;

    // Handle different event types
    switch (event) {
      case 'pledges:create':
      case 'pledges:update':
        await handlePledgeEvent(client, payload, webhookEventId);
        break;
      case 'pledges:delete':
        await handlePledgeDeleteEvent(client, payload, webhookEventId);
        break;
      default:
        console.log(`Unhandled event type: ${event}`);
    }

    // Mark as processed
    await client.query(
      `UPDATE patreon_webhook_events SET processed = true WHERE id = $1`,
      [webhookEventId]
    );

    await client.query('COMMIT');
    return res.json({ success: true, message: 'Event processed' });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Webhook processing error:', err);

    // Log error
    try {
      await pool.query(
        `INSERT INTO patreon_webhook_events (event_type, payload, idempotency_key, processed, error_message)
         VALUES ($1, $2, $3, false, $4)`,
        [event, JSON.stringify(payload), idempotencyKey, err.message]
      );
    } catch (logErr) {
      console.error('Failed to log webhook error:', logErr);
    }

    return res.status(500).json({ error: 'Failed to process webhook' });
  } finally {
    client.release();
  }
}

/**
 * Handle pledge creation/update
 * Award monthly points when patron pledges or renews
 */
async function handlePledgeEvent(client, payload, webhookEventId) {
  const pledge = payload.data?.attributes;
  const relationships = payload.data?.relationships;

  if (!pledge || pledge.patron_status !== 'active_patron') {
    return; // Skip inactive pledges
  }

  // Extract patron ID from relationships
  const patronData = payload.included?.find(
    item => item.type === 'user' && item.id === relationships?.patron?.data?.id
  );

  if (!patronData) {
    console.log('Patron data not found in webhook payload');
    return;
  }

  const patreonUserId = patronData.id;
  const patronEmail = patronData.attributes?.email;

  // Find user by patreon_user_id
  const userResult = await client.query(
    `SELECT user_id FROM patreon_links WHERE patreon_user_id = $1`,
    [patreonUserId]
  );

  if (userResult.rows.length === 0) {
    console.log(`User not found for Patreon ID: ${patreonUserId}`);
    return;
  }

  const userId = userResult.rows[0].user_id;

  // Check if already rewarded this month (prevent duplicate charges)
  const now = new Date();
  const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

  const lastRewardResult = await client.query(
    `SELECT last_reward_date FROM patreon_links WHERE user_id = $1`,
    [userId]
  );

  if (lastRewardResult.rows[0]?.last_reward_date) {
    const lastReward = new Date(lastRewardResult.rows[0].last_reward_date);
    if (lastReward >= monthStart) {
      console.log(`User ${userId} already rewarded this month`);
      return;
    }
  }

  // Get tier configuration
  const tierId = pledge.tier_id || relationships?.currently_entitled_tiers?.data?.[0]?.id;
  const tierResult = await client.query(
    `SELECT monthly_points, rank_multiplier FROM patreon_tier_config 
     WHERE tier_id = $1`,
    [tierId]
  );

  if (tierResult.rows.length === 0) {
    console.log(`Tier configuration not found: ${tierId}`);
    return;
  }

  const { monthly_points, rank_multiplier } = tierResult.rows[0];
  const pointsToAward = monthly_points;

  // Award points
  await client.query(
    `INSERT INTO user_points_balance (user_id, patreon_points, total_points)
     VALUES ($1, $2, $3)
     ON CONFLICT (user_id) DO UPDATE SET
       patreon_points = patreon_points + $2,
       total_points = total_points + $3`,
    [userId, pointsToAward, pointsToAward]
  );

  // Record transaction
  await client.query(
    `INSERT INTO points_transactions 
     (user_id, delta, type, source, reference_id, metadata)
     VALUES ($1, $2, 'patreon_reward', $3, $4, $5)`,
    [userId, pointsToAward, `patreon:${tierId}`, webhookEventId, 
     JSON.stringify({ tier_id: tierId, pledge_amount_cents: pledge.amount_cents })]
  );

  // Update last_reward_date and next_reward_date
  const nextReward = new Date(monthStart);
  nextReward.setMonth(nextReward.getMonth() + 1);

  await client.query(
    `UPDATE patreon_links
     SET last_reward_date = now(), next_reward_date = $1
     WHERE user_id = $2`,
    [nextReward, userId]
  );

  console.log(`Awarded ${pointsToAward} patreon points to user ${userId}`);
}

/**
 * Handle pledge deletion
 * Called when patron cancels membership
 */
async function handlePledgeDeleteEvent(client, payload, webhookEventId) {
  const relationships = payload.data?.relationships;
  const patronData = payload.included?.find(item => item.type === 'user');

  if (!patronData) {
    return;
  }

  const patreonUserId = patronData.id;

  // Find and deactivate user's Patreon link
  await client.query(
    `UPDATE patreon_links
     SET active = false, updated_at = now()
     WHERE patreon_user_id = $1`,
    [patreonUserId]
  );

  console.log(`Deactivated Patreon link for user: ${patreonUserId}`);
}

/**
 * Batch job to process pending rewards (runs daily)
 * Awards monthly points to active patrons
 */
async function processPendingRewards() {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    const now = new Date();
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

    // Find patrons who haven't been rewarded this month
    const patronsResult = await client.query(
      `SELECT pl.user_id, pl.tier_id, ptc.monthly_points
       FROM patreon_links pl
       JOIN patreon_tier_config ptc ON pl.tier_id = ptc.tier_id
       WHERE pl.active = true
         AND (pl.last_reward_date IS NULL OR pl.last_reward_date < $1)`,
      [monthStart]
    );

    let rewarded = 0;
    for (const patron of patronsResult.rows) {
      const { user_id, tier_id, monthly_points } = patron;

      // Award points
      await client.query(
        `INSERT INTO user_points_balance (user_id, patreon_points, total_points)
         VALUES ($1, $2, $3)
         ON CONFLICT (user_id) DO UPDATE SET
           patreon_points = patreon_points + $2,
           total_points = total_points + $3`,
        [user_id, monthly_points, monthly_points]
      );

      // Record transaction
      const nextReward = new Date(monthStart);
      nextReward.setMonth(nextReward.getMonth() + 1);

      await client.query(
        `INSERT INTO points_transactions 
         (user_id, delta, type, source, metadata)
         VALUES ($1, $2, 'patreon_reward', $3, $4)`,
        [user_id, monthly_points, `patreon:${tier_id}`, 
         JSON.stringify({ batch_job: true })]
      );

      await client.query(
        `UPDATE patreon_links
         SET last_reward_date = now(), next_reward_date = $1
         WHERE user_id = $2`,
        [nextReward, user_id]
      );

      rewarded++;
    }

    await client.query('COMMIT');
    console.log(`Processed ${rewarded} pending Patreon rewards`);

    return rewarded;
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Error processing pending rewards:', err);
    throw err;
  } finally {
    client.release();
  }
}

module.exports = {
  handlePatreonWebhook,
  processPendingRewards,
  verifyPatreonSignature
};
