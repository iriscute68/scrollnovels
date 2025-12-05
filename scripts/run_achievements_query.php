<?php
require_once dirname(__DIR__) . '/config/db.php';
try {
    $sql = "SELECT id, COALESCE(name, title) as name, COALESCE(description, summary, '') as description, COALESCE(icon, '🏅') as icon, COALESCE(category, 'Other') as category, COALESCE(total, points, 0) as total FROM achievements ORDER BY category, total ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($rows) . "\n";
    foreach (array_slice($rows,0,5) as $r) {
        echo "- " . ($r['id'] ?? '') . " | " . ($r['name'] ?? '') . " | " . ($r['description'] ?? '') . " | total=" . ($r['total'] ?? '') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
