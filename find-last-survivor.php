<?php
$pdo = new PDO("mysql:host=localhost;dbname=scroll_novels", "root", "");
$stmt = $pdo->query("SELECT id, author_id, title FROM stories WHERE title LIKE '%Last Survivor%'");
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Books matching 'Last Survivor':\n";
var_dump($books);

if (!empty($books)) {
    $book = $books[0];
    echo "\n\nTesting API for book ID " . $book['id'] . " (author_id=" . $book['author_id'] . "):\n";
    $url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=" . $book['author_id'] . "&limit=200";
    echo "URL: " . $url . "\n";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    echo "\nAPI Response:\n";
    var_dump($data);
}
