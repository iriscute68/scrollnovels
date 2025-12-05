<?php
require 'config/db.php';

echo "=== Checking Support Links Setup ===\n\n";

// Check if author_links table exists
echo "1. Checking author_links table:\n";
$result = $pdo->query("SHOW TABLES LIKE 'author_links'")->fetch();
if ($result) {
    echo "  ✓ author_links table exists\n";
    $columns = $pdo->query("DESCRIBE author_links")->fetchAll();
    echo "  Columns:\n";
    foreach ($columns as $col) {
        echo "    - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} else {
    echo "  ✗ author_links table NOT FOUND\n";
}

// Check users table columns
echo "\n2. Checking users table for support columns:\n";
$result = $pdo->query("SHOW COLUMNS FROM users LIKE 'patreon'")->fetch();
if ($result) {
    echo "  ✓ patreon column exists\n";
} else {
    echo "  ✗ patreon column not found\n";
}

$result = $pdo->query("SHOW COLUMNS FROM users LIKE 'kofi'")->fetch();
if ($result) {
    echo "  ✓ kofi column exists\n";
} else {
    echo "  ✗ kofi column not found\n";
}

// Check if any authors have support links
echo "\n3. Checking for existing support links:\n";
$links = $pdo->query("SELECT author_id, link_type, link_url FROM author_links LIMIT 10")->fetchAll();
if (count($links) > 0) {
    echo "  Found " . count($links) . " support links:\n";
    foreach ($links as $link) {
        echo "    - Author " . $link['author_id'] . ": " . $link['link_type'] . " → " . $link['link_url'] . "\n";
    }
} else {
    echo "  No support links found in database\n";
}

// Check user Patreon/Ko-fi fields
echo "\n4. Checking users with support info:\n";
$users = $pdo->query("SELECT id, username, patreon, kofi FROM users WHERE patreon IS NOT NULL OR kofi IS NOT NULL LIMIT 5")->fetchAll();
if (count($users) > 0) {
    echo "  Found " . count($users) . " users with support info:\n";
    foreach ($users as $user) {
        echo "    - " . $user['username'] . ": patreon=" . ($user['patreon'] ? "✓" : "✗") . ", kofi=" . ($user['kofi'] ? "✓" : "✗") . "\n";
    }
} else {
    echo "  No users with support info found\n";
}

// Check for author 5 (Zakiel admin)
echo "\n5. Checking Zakiel admin (ID 5) support links:\n";
$user = $pdo->query("SELECT id, username, patreon, kofi FROM users WHERE id = 5")->fetch();
if ($user) {
    echo "  Username: " . $user['username'] . "\n";
    echo "  Patreon: " . ($user['patreon'] ?: "NOT SET") . "\n";
    echo "  Ko-fi: " . ($user['kofi'] ?: "NOT SET") . "\n";
}

$links = $pdo->query("SELECT link_type, link_url FROM author_links WHERE author_id = 5")->fetchAll();
echo "  Author links table: " . count($links) . " links\n";
foreach ($links as $link) {
    echo "    - " . $link['link_type'] . ": " . $link['link_url'] . "\n";
}
?>
