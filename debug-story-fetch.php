<?php
require_once __DIR__ . '/config/db.php';

// Fetch story details the same way book.php does
$bookId = 1;
$stmt = $pdo->prepare("
    SELECT s.*, u.id as author_id, u.username as author_name, u.profile_image
    FROM stories s 
    LEFT JOIN users u ON s.author_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$bookId]);
$story = $stmt->fetch();

echo "Story ID: " . $bookId . "\n";
echo "Story data:\n";
echo "  s.author_id (from stories table): " . $story['author_id'] . "\n";
echo "  u.id (from users table): " . $story['author_id'] . "\n";
echo "  author_name: " . $story['author_name'] . "\n\n";

echo "Full story array:\n";
foreach ($story as $key => $val) {
    echo "  $key: $val\n";
}

echo "\n\n=== What JavaScript will see ===\n";
echo "const authorId = " . ($story['author_id'] ?? 'null') . ";\n";
