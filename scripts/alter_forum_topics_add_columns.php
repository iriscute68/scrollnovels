<?php
// scripts/alter_forum_topics_add_columns.php
require_once dirname(__DIR__) . '/config/db.php';
$existing = [];
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM forum_topics');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) $existing[$c['Field']] = true;

    $queries = [];
    if (!isset($existing['category_id'])) $queries[] = "ALTER TABLE forum_topics ADD COLUMN category_id INT NULL";
    if (!isset($existing['category'])) $queries[] = "ALTER TABLE forum_topics ADD COLUMN category VARCHAR(100) DEFAULT 'General Chat'";
    if (!isset($existing['pinned'])) $queries[] = "ALTER TABLE forum_topics ADD COLUMN pinned TINYINT(1) DEFAULT 0";
    if (!isset($existing['views'])) $queries[] = "ALTER TABLE forum_topics ADD COLUMN views INT DEFAULT 0";
    if (!isset($existing['image'])) $queries[] = "ALTER TABLE forum_topics ADD COLUMN image VARCHAR(255) DEFAULT NULL";

    foreach ($queries as $q) {
        $pdo->exec($q);
    }
    echo "Altered forum_topics: added " . count($queries) . " columns\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
return 0;
