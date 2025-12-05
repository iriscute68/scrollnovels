<?php
require_once __DIR__ . '/config/db.php';

// Get list of ALL books
$stmt = $pdo->prepare("SELECT s.id, s.title, s.author_id, u.username FROM stories s LEFT JOIN users u ON s.author_id = u.id ORDER BY s.id");
$stmt->execute();
$books = $stmt->fetchAll();

echo "=== ALL BOOKS IN DATABASE ===\n\n";

foreach ($books as $book) {
    $bid = $book['id'];
    $title = $book['title'];
    $author_id = $book['author_id'];
    $author_name = $book['username'] ?? 'NO AUTHOR';
    
    // Count supporters for this author
    $supp_count = 0;
    if ($author_id) {
        $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT supporter_id) as cnt FROM author_supporters WHERE author_id = ?");
        $stmt2->execute([$author_id]);
        $supp_count = $stmt2->fetch()['cnt'];
    }
    
    $marker = ($supp_count > 0) ? "✓ HAS SUPPORTERS" : "  ";
    echo "Book $bid: $title\n";
    echo "  Author: $author_name (ID: $author_id) | Supporters: $supp_count $marker\n";
    echo "  URL: http://localhost/scrollnovels/pages/book.php?id=$bid\n";
    echo "\n";
}
