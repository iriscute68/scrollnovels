<?php
// Run migrations
require_once __DIR__ . '/config/db.php';

$migration_file = __DIR__ . '/migrations/add_admin_donations.sql';

if (!file_exists($migration_file)) {
  die("Migration file not found: $migration_file\n");
}

$sql = file_get_contents($migration_file);
$statements = array_filter(array_map('trim', explode(';', $sql)));

$errors = [];
foreach ($statements as $statement) {
  if (empty($statement)) continue;
  try {
    $pdo->exec($statement);
    echo "[✓] Executed: " . substr($statement, 0, 50) . "...\n";
  } catch (Exception $e) {
    $errors[] = $e->getMessage();
    echo "[✗] Error: " . $e->getMessage() . "\n";
  }
}

if (empty($errors)) {
  echo "\n[✓] All migrations completed successfully!\n";
} else {
  echo "\n[✗] Some migrations failed.\n";
  foreach ($errors as $err) {
    echo "  - $err\n";
  }
}
?>

