// server/index.js - Express Server Setup

const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const session = require('express-session');
const path = require('path');
const cron = require('node-cron');

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// ============= Middleware Setup =============

// CORS
app.use(cors({
  origin: process.env.FRONTEND_URL || 'http://localhost:3000',
  credentials: true
}));

// Body parsing
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));

// Session setup
app.use(session({
  secret: process.env.SESSION_SECRET || 'dev-secret-change-in-production',
  resave: false,
  saveUninitialized: true,
  cookie: {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax'
  }
}));

// Static files
app.use(express.static(path.join(__dirname, '../public')));

// ============= Routes =============

// Import route handlers
const pointsRouter = require('./routes/points');
const oauthRouter = require('./routes/oauth');
const guidesRouter = require('./routes/guides');
const { handlePatreonWebhook } = require('./webhooks/patreon');

// Mount routes
app.use('/api/v1', pointsRouter);
app.use('/api/v1', oauthRouter);
app.use('/api/v1', guidesRouter);

// Webhook endpoint (no auth needed for webhook receiver)
app.post('/webhooks/patreon', express.raw({ type: 'application/json' }), async (req, res) => {
  try {
    // Convert raw body to JSON for verification
    const payload = JSON.parse(req.body);
    const signature = req.headers['x-patreon-signature'];
    
    await handlePatreonWebhook({ body: payload, headers: { 'x-patreon-signature': signature } }, res);
  } catch (err) {
    console.error('Webhook error:', err);
    res.status(500).json({ error: 'Webhook processing failed' });
  }
});

// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// Error handler
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ 
    error: process.env.NODE_ENV === 'production' 
      ? 'Internal server error' 
      : err.message 
  });
});

// ============= Scheduled Jobs =============

const { processPendingRewards } = require('./webhooks/patreon');
const { processPointDecay } = require('./jobs/points-decay');

// Run pending rewards daily at 12:00 AM UTC
cron.schedule('0 0 * * *', async () => {
  console.log('[Cron] Running daily Patreon reward processing...');
  try {
    await processPendingRewards();
  } catch (err) {
    console.error('[Cron] Error processing rewards:', err);
  }
});

// Run point decay weekly (Monday at 12:00 AM UTC)
cron.schedule('0 0 * * 1', async () => {
  console.log('[Cron] Running weekly point decay...');
  try {
    await processPointDecay();
  } catch (err) {
    console.error('[Cron] Error processing decay:', err);
  }
});

// Run rankings aggregation daily (1:00 AM UTC)
cron.schedule('0 1 * * *', async () => {
  console.log('[Cron] Aggregating book rankings...');
  try {
    await aggregateBookRankings();
  } catch (err) {
    console.error('[Cron] Error aggregating rankings:', err);
  }
});

// ============= Server Start =============

async function startServer() {
  try {
    // Test database connection
    const { pool } = require('./db');
    const result = await pool.query('SELECT NOW()');
    console.log('✓ Database connected:', result.rows[0]);

    app.listen(PORT, () => {
      console.log(`✓ Server running on port ${PORT}`);
      console.log(`✓ Environment: ${process.env.NODE_ENV || 'development'}`);
    });
  } catch (err) {
    console.error('✗ Failed to start server:', err);
    process.exit(1);
  }
}

startServer();

module.exports = app;
