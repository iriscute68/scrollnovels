<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>📊 Diagnostic: Top Supporters Feature</h2>";

// List ALL books and show which ones have supporters
$stmt = $pdo->prepare("SELECT s.id, s.title, s.author_id, u.username FROM stories s LEFT JOIN users u ON s.author_id = u.id ORDER BY s.id");
$stmt->execute();
$books = $stmt->fetchAll();

echo "<table border='1' style='border-collapse:collapse; width:100%; margin-top:1rem;'>";
echo "<tr style='background:#f0f0f0;'><th style='padding:0.5rem;'>Book ID</th><th style='padding:0.5rem;'>Title</th><th style='padding:0.5rem;'>Author</th><th style='padding:0.5rem;'>Supporters</th><th style='padding:0.5rem;'>Action</th></tr>";

foreach ($books as $book) {
    $author_id = $book['author_id'];
    $supporters_count = 0;
    
    if ($author_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM author_supporters WHERE author_id = ? GROUP BY supporter_id");
        $stmt->execute([$author_id]);
        $rows = $stmt->fetchAll();
        $supporters_count = count($rows);
    }
    
    $row_color = $supporters_count > 0 ? '#e8f5e9' : '#fff3e0';
    echo "<tr style='background:$row_color;'>";
    echo "<td style='padding:0.5rem;'>" . $book['id'] . "</td>";
    echo "<td style='padding:0.5rem;'>" . htmlspecialchars(substr($book['title'], 0, 40)) . "</td>";
    echo "<td style='padding:0.5rem;'>" . ($book['username'] ?? 'No author') . "</td>";
    echo "<td style='padding:0.5rem;'><strong>" . $supporters_count . "</strong></td>";
    echo "<td style='padding:0.5rem;'><a href='pages/book.php?id=" . $book['id'] . "' target='_blank'>View</a></td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3 style='margin-top:2rem;'>📌 How to Test</h3>";
echo "<ol>";
echo "<li>Click \"View\" on a book with Supporters > 0</li>";
echo "<li>On the book page, click the '<strong>🏆 Top Supporters</strong>' tab</li>";
echo "<li>You should see the supporters load</li>";
echo "<li>If you see 'Loading supporters...' forever, check browser console (F12) for errors</li>";
echo "</ol>";

