<?php
require_once 'config/db.php';

echo "=== Stories Table Structure ===\n";
$cols = $pdo->query("DESCRIBE stories")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== Fanfic Stories ===\n";
try {
    $stmt = $pdo->query("SELECT id, title, content_type, is_fanfiction, genre, tags FROM stories WHERE content_type = 'fanfic' OR is_fanfiction = 1 LIMIT 10");
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($stories)) {
        echo "No stories with content_type='fanfic' or is_fanfiction=1 found\n";
    } else {
        foreach ($stories as $s) {
            echo "ID: {$s['id']}, Title: {$s['title']}, content_type: " . ($s['content_type'] ?? 'null') . ", is_fanfiction: " . ($s['is_fanfiction'] ?? 'null') . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== All Stories content_type values ===\n";
try {
    $stmt = $pdo->query("SELECT DISTINCT content_type FROM stories");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . ($row['content_type'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
