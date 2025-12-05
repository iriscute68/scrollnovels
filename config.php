<?php
// Root config loader — keep minimal. DB connection and helpers live in config/db.php and includes/functions.php
// Load DB connection
require_once __DIR__ . '/config/db.php';

// Site constants
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
if (!defined('SITE_NAME')) define('SITE_NAME', 'Scroll Novels');
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/uploads/');

// Include shared helpers (time_ago, asset_url, notify, etc.) if available
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
}

// Run lightweight DB migration guards early
if (file_exists(__DIR__ . '/includes/db_migrations.php')) {
    require_once __DIR__ . '/includes/db_migrations.php';
}
