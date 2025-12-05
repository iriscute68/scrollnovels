#!/usr/bin/env node

/**
 * Seed Script - Create test data for development
 * Usage: node scripts/seed.js
 */

const crypto = require('crypto');
const { pool } = require('../server/db');

// Simple password hash (use bcrypt in production!)
function hashPassword(password) {
  return crypto.createHash('sha256').update(password).digest('hex');
}

async function seed() {
  const client = await pool.connect();
  
  try {
    console.log('Seeding test data...\n');
    
    // Create test users
    const testUsers = [
      {
        email: 'alice@test.com',
        username: 'alice',
        password: 'alice123',
        role: 'user'
      },
      {
        email: 'bob@test.com',
        username: 'bob_author',
        password: 'bob123',
        role: 'author'
      },
      {
        email: 'admin@test.com',
        username: 'admin',
        password: 'admin123',
        role: 'admin'
      }
    ];
    
    const userIds = {};
    
    for (const user of testUsers) {
      const result = await client.query(
        `INSERT INTO users (email, username, password_hash, role)
         VALUES ($1, $2, $3, $4)
         ON CONFLICT (email) DO NOTHING
         RETURNING id`,
        [user.email, user.username, hashPassword(user.password), user.role]
      );
      
      if (result.rows.length > 0) {
        userIds[user.username] = result.rows[0].id;
        console.log(`✓ Created user: ${user.username}`);
      }
    }
    
    // Create test books
    const bobId = userIds['bob_author'];
    if (bobId) {
      const bookResult = await client.query(
        `INSERT INTO books (author_id, title, slug, description, status)
         VALUES ($1, $2, $3, $4, $5)
         ON CONFLICT (slug) DO NOTHING
         RETURNING id`,
        [bobId, 'The Epic Adventure', 'the-epic-adventure', 
         'An amazing story of adventure and discovery', 'published']
      );
      
      if (bookResult.rows.length > 0) {
        console.log(`✓ Created book: The Epic Adventure`);
      }
    }
    
    // Add initial points to test users
    const alice = userIds['alice'];
    if (alice) {
      await client.query(
        `INSERT INTO user_points_balance (user_id, free_points, premium_points, patreon_points, total_points)
         VALUES ($1, 1000, 500, 0, 1500)
         ON CONFLICT (user_id) DO NOTHING`,
        [alice]
      );
      
      // Log initial points
      await client.query(
        `INSERT INTO points_transactions (user_id, delta, type, source, balance_after)
         VALUES ($1, 1500, 'admin_adjust', 'seed_data', 1500)`,
        [alice]
      );
      
      console.log(`✓ Added 1500 points to alice`);
    }
    
    // Create test Patreon tier link
    const tierResult = await client.query(
      `SELECT tier_id FROM patreon_tier_config LIMIT 1`
    );
    
    if (tierResult.rows.length > 0 && alice) {
      const tierId = tierResult.rows[0].tier_id;
      
      await client.query(
        `INSERT INTO patreon_links 
         (user_id, patreon_user_id, email, access_token, tier_id, active)
         VALUES ($1, $2, $3, $4, $5, true)
         ON CONFLICT (user_id) DO NOTHING`,
        [alice, 'patreon_' + alice, 'alice@patreon.com', 'mock_token_' + alice, tierId]
      );
      
      console.log(`✓ Created Patreon link for alice`);
    }
    
    console.log('\n✓ Seed completed successfully!\n');
    
    console.log('Test credentials:');
    console.log('─'.repeat(40));
    console.log('User Account:');
    console.log('  Email: alice@test.com');
    console.log('  Password: alice123');
    console.log('  Points: 1500 (1000 free, 500 premium)');
    console.log('\nAuthor Account:');
    console.log('  Email: bob@test.com');
    console.log('  Password: bob123');
    console.log('\nAdmin Account:');
    console.log('  Email: admin@test.com');
    console.log('  Password: admin123');
    console.log('─'.repeat(40));
    console.log('\nTo log in, use your login endpoint to get JWT token');
    
  } catch (err) {
    console.error('✗ Seed failed:', err.message);
    process.exit(1);
  } finally {
    await client.release();
    await pool.end();
  }
}

// Run seed
seed();
