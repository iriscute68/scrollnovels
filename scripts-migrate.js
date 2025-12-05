#!/usr/bin/env node

/**
 * Migration Script - Initialize Point System
 * Usage: node scripts/migrate.js
 */

const fs = require('fs');
const path = require('path');
const { pool } = require('../server/db');

async function migrate() {
  const client = await pool.connect();
  
  try {
    console.log('Starting migration...');
    
    // Read schema file
    const schemaPath = path.join(__dirname, '../postgres-schema.sql');
    const schema = fs.readFileSync(schemaPath, 'utf8');
    
    // Execute schema
    await client.query(schema);
    
    console.log('✓ Schema created successfully');
    
    // Verify all tables exist
    const tables = [
      'users', 'books', 'points_transactions', 'user_points_balance',
      'book_support', 'patreon_links', 'patreon_tier_config', 
      'patreon_webhook_events', 'point_expiry_schedule', 'book_rankings',
      'guide_pages', 'guide_sections', 'guide_images'
    ];
    
    for (const table of tables) {
      const result = await client.query(
        `SELECT EXISTS (SELECT FROM information_schema.tables 
         WHERE table_name = $1)`,
        [table]
      );
      
      if (result.rows[0].exists) {
        console.log(`✓ Table '${table}' created`);
      } else {
        throw new Error(`Table '${table}' not found after migration`);
      }
    }
    
    // Count pre-populated records
    const tierResult = await client.query(
      `SELECT COUNT(*) as count FROM patreon_tier_config`
    );
    const guideResult = await client.query(
      `SELECT COUNT(*) as count FROM guide_pages`
    );
    
    console.log(`✓ Pre-populated ${tierResult.rows[0].count} Patreon tiers`);
    console.log(`✓ Pre-populated ${guideResult.rows[0].count} guide pages`);
    
    console.log('\n✓ Migration completed successfully!\n');
    
    // Display next steps
    console.log('Next steps:');
    console.log('1. Create an admin user:');
    console.log('   - INSERT INTO users (email, username, password_hash, role)');
    console.log('   - VALUES (\'admin@example.com\', \'admin\', \'hashed_password\', \'admin\');');
    console.log('\n2. Start the server:');
    console.log('   - npm start\n');
    console.log('3. Test API:');
    console.log('   - curl http://localhost:3000/health\n');
    
  } catch (err) {
    console.error('✗ Migration failed:', err.message);
    process.exit(1);
  } finally {
    await client.release();
    await pool.end();
  }
}

// Run migration
migrate();
