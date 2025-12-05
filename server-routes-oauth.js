// server/routes/oauth.js - Patreon OAuth Integration

const express = require('express');
const router = express.Router();
const axios = require('axios');
const { pool } = require('../db');
const { authMiddleware } = require('../middleware/auth');

const PATREON_CLIENT_ID = process.env.PATREON_CLIENT_ID;
const PATREON_CLIENT_SECRET = process.env.PATREON_CLIENT_SECRET;
const PATREON_REDIRECT_URI = process.env.PATREON_REDIRECT_URI || 'http://localhost:3000/api/v1/oauth/patreon/callback';

/**
 * GET /api/v1/oauth/patreon/url
 * Return the Patreon OAuth authorization URL
 */
router.get('/oauth/patreon/url', (req, res) => {
  const state = require('crypto').randomBytes(32).toString('hex');
  
  // Store state in session/cache for CSRF validation
  req.session = req.session || {};
  req.session.oauthState = state;

  const url = new URL('https://www.patreon.com/oauth2/authorize');
  url.searchParams.append('response_type', 'code');
  url.searchParams.append('client_id', PATREON_CLIENT_ID);
  url.searchParams.append('redirect_uri', PATREON_REDIRECT_URI);
  url.searchParams.append('scope', 'identity identity.memberships');
  url.searchParams.append('state', state);

  res.json({
    success: true,
    url: url.toString()
  });
});

/**
 * POST /api/v1/oauth/patreon/callback
 * Handle Patreon OAuth callback and link account
 */
router.post('/oauth/patreon/callback', authMiddleware, async (req, res) => {
  const { code, state } = req.body;

  // Validate state
  if (!req.session?.oauthState || req.session.oauthState !== state) {
    return res.status(400).json({ error: 'Invalid state - possible CSRF attack' });
  }

  try {
    // Exchange code for access token
    const tokenResponse = await axios.post('https://www.patreon.com/api/oauth2/token', {
      client_id: PATREON_CLIENT_ID,
      client_secret: PATREON_CLIENT_SECRET,
      code,
      grant_type: 'authorization_code',
      redirect_uri: PATREON_REDIRECT_URI
    });

    const { access_token, refresh_token, expires_in } = tokenResponse.data;
    const token_expires_at = new Date(Date.now() + (expires_in * 1000));

    // Fetch user identity
    const identityResponse = await axios.get('https://www.patreon.com/api/v2/identity', {
      headers: { Authorization: `Bearer ${access_token}` },
      params: { 'include': 'memberships', 'fields[user]': 'email,full_name' }
    });

    const patreonUser = identityResponse.data.data;
    const memberships = identityResponse.data.included
      ?.filter(item => item.type === 'member')
      ?.map(m => m.attributes) || [];

    // Get highest tier
    const highestTier = memberships.reduce((max, m) => 
      (!max || m.patron_status === 'active_patron' && m.pledge_amount_cents > max.pledge_amount_cents) ? m : max
    , null);

    const client = await pool.connect();
    try {
      await client.query('BEGIN');

      const patreonLink = {
        user_id: req.user.id,
        patreon_user_id: patreonUser.id,
        email: patreonUser.attributes.email,
        full_name: patreonUser.attributes.full_name,
        access_token,
        refresh_token,
        token_expires_at,
        tier_id: highestTier?.tier_id || null,
        tier_name: highestTier?.tier_title || 'None',
        pledge_amount_cents: highestTier?.pledge_amount_cents || 0,
        patron_status: highestTier?.patron_status || 'not_patron',
        active: highestTier?.patron_status === 'active_patron',
        next_charge_date: highestTier?.next_charge_date || null
      };

      // Upsert patreon_links
      const result = await client.query(
        `INSERT INTO patreon_links 
         (user_id, patreon_user_id, email, full_name, access_token, refresh_token, 
          token_expires_at, tier_id, tier_name, pledge_amount_cents, patron_status, active, next_charge_date)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)
         ON CONFLICT (user_id) DO UPDATE SET
           patreon_user_id = $2,
           email = $3,
           full_name = $4,
           access_token = $5,
           refresh_token = $6,
           token_expires_at = $7,
           tier_id = $8,
           tier_name = $9,
           pledge_amount_cents = $10,
           patron_status = $11,
           active = $12,
           next_charge_date = $13,
           updated_at = now()
         RETURNING *`,
        [
          patreonLink.user_id,
          patreonLink.patreon_user_id,
          patreonLink.email,
          patreonLink.full_name,
          patreonLink.access_token,
          patreonLink.refresh_token,
          patreonLink.token_expires_at,
          patreonLink.tier_id,
          patreonLink.tier_name,
          patreonLink.pledge_amount_cents,
          patreonLink.patron_status,
          patreonLink.active,
          patreonLink.next_charge_date
        ]
      );

      // If new active patron, award initial points
      if (highestTier && highestTier.patron_status === 'active_patron') {
        const tierResult = await client.query(
          `SELECT monthly_points FROM patreon_tier_config WHERE tier_id = $1`,
          [highestTier.tier_id]
        );

        if (tierResult.rows[0]) {
          const points = tierResult.rows[0].monthly_points;

          // Update balance
          await client.query(
            `INSERT INTO user_points_balance (user_id, patreon_points, total_points)
             VALUES ($1, $2, $3)
             ON CONFLICT (user_id) DO UPDATE SET
               patreon_points = patreon_points + $2,
               total_points = total_points + $3`,
            [req.user.id, points, points]
          );

          // Log transaction
          await client.query(
            `INSERT INTO points_transactions 
             (user_id, delta, type, source, metadata, balance_after)
             VALUES ($1, $2, 'patreon_reward', $3, $4, $2)`,
            [req.user.id, points, `patreon:${highestTier.tier_id}`, JSON.stringify({ tier: highestTier.tier_title })]
          );
        }
      }

      await client.query('COMMIT');

      res.json({
        success: true,
        message: 'Patreon account linked successfully',
        data: result.rows[0]
      });
    } catch (err) {
      await client.query('ROLLBACK');
      throw err;
    } finally {
      client.release();
    }
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to link Patreon account' });
  }
});

/**
 * GET /api/v1/me/patreon
 * Get current user's Patreon link info
 */
router.get('/me/patreon', authMiddleware, async (req, res) => {
  try {
    const result = await pool.query(
      `SELECT patreon_user_id, tier_name, pledge_amount_cents, patron_status, active, 
              last_reward_date, next_reward_date, created_at, updated_at
       FROM patreon_links
       WHERE user_id = $1`,
      [req.user.id]
    );

    if (result.rows.length === 0) {
      return res.json({
        success: true,
        linked: false
      });
    }

    res.json({
      success: true,
      linked: true,
      data: result.rows[0]
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to get Patreon info' });
  }
});

/**
 * DELETE /api/v1/me/patreon
 * Unlink Patreon account
 */
router.delete('/me/patreon', authMiddleware, async (req, res) => {
  try {
    await pool.query(
      `UPDATE patreon_links
       SET active = false, updated_at = now()
       WHERE user_id = $1`,
      [req.user.id]
    );

    res.json({
      success: true,
      message: 'Patreon account unlinked'
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Failed to unlink Patreon' });
  }
});

/**
 * POST /api/v1/oauth/patreon/refresh-token
 * Refresh Patreon access token (called automatically when expired)
 */
async function refreshPatreonToken(userId) {
  try {
    const linkResult = await pool.query(
      `SELECT refresh_token FROM patreon_links WHERE user_id = $1`,
      [userId]
    );

    if (linkResult.rows.length === 0) return false;

    const { refresh_token } = linkResult.rows[0];

    const tokenResponse = await axios.post('https://www.patreon.com/api/oauth2/token', {
      client_id: PATREON_CLIENT_ID,
      client_secret: PATREON_CLIENT_SECRET,
      refresh_token,
      grant_type: 'refresh_token'
    });

    const { access_token, refresh_token: newRefreshToken, expires_in } = tokenResponse.data;
    const token_expires_at = new Date(Date.now() + (expires_in * 1000));

    await pool.query(
      `UPDATE patreon_links
       SET access_token = $1, refresh_token = $2, token_expires_at = $3
       WHERE user_id = $4`,
      [access_token, newRefreshToken, token_expires_at, userId]
    );

    return access_token;
  } catch (err) {
    console.error('Token refresh failed:', err);
    return false;
  }
}

module.exports = router;
module.exports.refreshPatreonToken = refreshPatreonToken;
