-- PostgreSQL DDL Schema for Complete Point System
-- Run this to initialize the database

-- Users table (simplified)
CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email varchar(320) UNIQUE NOT NULL,
  username varchar(64) UNIQUE NOT NULL,
  password_hash varchar(256),
  role varchar(32) DEFAULT 'user', -- 'user', 'author', 'admin'
  profile_image text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

-- Books table
CREATE TABLE IF NOT EXISTS books (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  author_id uuid REFERENCES users(id) ON DELETE SET NULL,
  title text NOT NULL,
  slug text UNIQUE NOT NULL,
  cover_url text,
  status varchar(32) DEFAULT 'draft', -- 'draft', 'published', 'completed'
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE INDEX idx_books_author_id ON books(author_id);
CREATE INDEX idx_books_slug ON books(slug);

-- Points transactions (immutable ledger - source of truth)
CREATE TABLE IF NOT EXISTS points_transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  delta integer NOT NULL, -- positive (earn) or negative (spend)
  balance_after integer, -- snapshot of balance after transaction
  type varchar(32) NOT NULL, -- 'purchase', 'patreon_reward', 'free_reward', 'spent', 'admin_adjust'
  source varchar(128), -- 'patreon:tier_xyz', 'coin_package_100', 'daily_login', 'book_support'
  reference_id uuid, -- links to book_support.id or other entity
  metadata jsonb, -- extra data (patreon tier info, etc)
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_points_user_created ON points_transactions(user_id, created_at DESC);
CREATE INDEX idx_points_type ON points_transactions(type);

-- User points balance (denormalized for fast reads)
CREATE TABLE IF NOT EXISTS user_points_balance (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  free_points bigint DEFAULT 0,
  premium_points bigint DEFAULT 0,
  patreon_points bigint DEFAULT 0,
  total_points bigint DEFAULT 0, -- computed field
  updated_at timestamptz DEFAULT now()
);

-- Book support (when a user supports a book)
CREATE TABLE IF NOT EXISTS book_support (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  book_id uuid NOT NULL REFERENCES books(id) ON DELETE CASCADE,
  points integer NOT NULL,
  point_type varchar(32) NOT NULL, -- 'free', 'premium', 'patreon'
  multiplier numeric(5,2) DEFAULT 1.0, -- stored for historical accuracy
  effective_points integer, -- points * multiplier (computed)
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_book_support_book_created ON book_support(book_id, created_at DESC);
CREATE INDEX idx_book_support_user_id ON book_support(user_id);

-- Patreon links
CREATE TABLE IF NOT EXISTS patreon_links (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  patreon_user_id varchar(128) NOT NULL UNIQUE,
  tier_id varchar(128) NOT NULL,
  tier_name varchar(255),
  amount_cents integer,
  active boolean DEFAULT true,
  access_token text,
  refresh_token text,
  token_expires_at timestamptz,
  last_reward_date timestamptz,
  next_reward_date timestamptz,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE INDEX idx_patreon_user_id ON patreon_links(user_id);

-- Patreon webhook events (audit trail)
CREATE TABLE IF NOT EXISTS patreon_webhook_events (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  event_type varchar(128),
  payload jsonb,
  idempotency_key varchar(255), -- to prevent duplicate processing
  processed boolean DEFAULT false,
  error_message text,
  received_at timestamptz DEFAULT now()
);

CREATE INDEX idx_webhook_idempotency ON patreon_webhook_events(idempotency_key);
CREATE INDEX idx_webhook_processed ON patreon_webhook_events(processed);

-- Point expiry schedule (for decay system)
CREATE TABLE IF NOT EXISTS point_expiry_schedule (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  source_tx_id uuid REFERENCES points_transactions(id) ON DELETE SET NULL,
  points integer NOT NULL,
  original_points integer NOT NULL,
  expires_at timestamptz NOT NULL,
  decay_percentage numeric(5,2) DEFAULT 0,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_expiry_schedule_user ON point_expiry_schedule(user_id);
CREATE INDEX idx_expiry_schedule_expires ON point_expiry_schedule(expires_at);

-- Book rankings (pre-aggregated for fast leaderboards)
CREATE TABLE IF NOT EXISTS book_rankings (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  day date NOT NULL,
  period varchar(32) NOT NULL, -- 'daily', 'weekly', 'monthly', 'all_time'
  book_id uuid NOT NULL REFERENCES books(id) ON DELETE CASCADE,
  total_support_points numeric NOT NULL,
  supporter_count integer DEFAULT 0,
  rank_position integer,
  created_at timestamptz DEFAULT now()
);

CREATE UNIQUE INDEX idx_book_rankings_unique ON book_rankings(day, period, book_id);
CREATE INDEX idx_book_rankings_period ON book_rankings(period, day DESC, total_support_points DESC);

-- Patreon tier configuration
CREATE TABLE IF NOT EXISTS patreon_tier_config (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tier_id varchar(128) NOT NULL UNIQUE,
  tier_name varchar(255) NOT NULL,
  monthly_points integer NOT NULL,
  rank_multiplier numeric(5,2) DEFAULT 3.0,
  features jsonb, -- ['no_ads', 'early_chapters', 'vip_badge', etc]
  price_cents integer,
  active boolean DEFAULT true,
  created_at timestamptz DEFAULT now()
);

-- Admin guide pages (editable by admins)
CREATE TABLE IF NOT EXISTS guide_pages (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  slug text UNIQUE NOT NULL,
  title text NOT NULL,
  description text,
  content text, -- main content (can use markdown)
  order_index integer DEFAULT 0,
  published boolean DEFAULT false,
  created_by uuid REFERENCES users(id) ON DELETE SET NULL,
  updated_by uuid REFERENCES users(id) ON DELETE SET NULL,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE INDEX idx_guide_pages_slug ON guide_pages(slug);
CREATE INDEX idx_guide_pages_published ON guide_pages(published);

-- Guide page sections (for multi-section guides)
CREATE TABLE IF NOT EXISTS guide_sections (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  guide_id uuid NOT NULL REFERENCES guide_pages(id) ON DELETE CASCADE,
  title text NOT NULL,
  content text,
  order_index integer DEFAULT 0,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE INDEX idx_guide_sections_guide_id ON guide_sections(guide_id, order_index);

-- Guide images (for uploading images to guide pages)
CREATE TABLE IF NOT EXISTS guide_images (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  guide_id uuid NOT NULL REFERENCES guide_pages(id) ON DELETE CASCADE,
  image_url text NOT NULL,
  caption text,
  alt_text text,
  order_index integer DEFAULT 0,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX idx_guide_images_guide_id ON guide_images(guide_id, order_index);

-- Insert default Patreon tier configurations
INSERT INTO patreon_tier_config (tier_id, tier_name, monthly_points, rank_multiplier, features, price_cents, active)
VALUES
  ('bronze_tier', 'Bronze Supporter', 500, 2.0, '["vip_badge"]', 500, true),
  ('silver_tier', 'Silver Supporter', 1200, 2.5, '["vip_badge", "early_chapters"]', 1000, true),
  ('gold_tier', 'Gold Supporter', 3000, 3.0, '["no_ads", "early_chapters", "vip_badge"]', 2000, true),
  ('diamond_tier', 'Diamond Supporter', 10000, 3.0, '["no_ads", "early_chapters", "vip_badge", "boost_support"]', 5000, true)
ON CONFLICT (tier_id) DO NOTHING;

-- Insert default guide pages
INSERT INTO guide_pages (slug, title, description, published, order_index)
VALUES
  ('how-points-work', 'How Points Work', 'Learn about the point system and how to earn rewards', true, 1),
  ('supporting-books', 'Supporting Your Favorite Books', 'How to use points to support authors', true, 2),
  ('patreon-integration', 'Patreon Integration', 'Connect your Patreon account for exclusive benefits', true, 3),
  ('rankings-system', 'Understanding Rankings', 'How book rankings are calculated', true, 4)
ON CONFLICT (slug) DO NOTHING;
