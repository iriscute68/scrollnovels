<?php
// sql/run_migration.php
// Runs a single SQL migration file using the app's PDO config.
require_once __DIR__ . '/../config/db.php';

$filename = __DIR__ . '/migrations/20251204_add_competition_statuses.sql';
if (!file_exists($filename)) {
    echo "Migration file not found: $filename\n";
    exit(1);
}

$content = file_get_contents($filename);

// Extract the first ALTER TABLE statement to execute safely
$alter = null;
if (preg_match('/ALTER\s+TABLE[\s\S]*?;/', $content, $m)) {
    $alter = $m[0];
}

if (empty($alter)) {
    echo "No ALTER TABLE statement found in migration file. Nothing to run.\n";
    exit(1);
}

try {
    // Execute single statement (avoid multi-statement execution issues)
    $pdo->exec($alter);
    echo "Migration applied successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>