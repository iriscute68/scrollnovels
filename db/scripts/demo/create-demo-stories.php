<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain');

echo "=== CREATING DEMO STORIES ===\n\n";

// Get testauthor user ID
$author = $pdo->query("SELECT id FROM users WHERE username = 'testauthor'")->fetch();
if (!$author) {
    echo "❌ testauthor not found. Run /create-test-users.php first\n";
    exit;
}

$author_id = $author['id'];

// Sample stories
$stories = [
    [
        'title' => 'The Lost Kingdom',
        'slug' => 'the-lost-kingdom',
        'description' => 'An epic fantasy adventure through magical realms and forgotten kingdoms.',
        'genre' => 'Fantasy',
        'status' => 'published'
    ],
    [
        'title' => 'Midnight Chronicles',
        'slug' => 'midnight-chronicles',
        'description' => 'A thrilling mystery that unravels secrets hidden in the shadows.',
        'genre' => 'Mystery',
        'status' => 'published'
    ],
    [
        'title' => 'Love in the City',
        'slug' => 'love-in-the-city',
        'description' => 'A modern romance tale set against the backdrop of a bustling metropolis.',
        'genre' => 'Romance',
        'status' => 'published'
    ],
    [
        'title' => 'Digital Dreams',
        'slug' => 'digital-dreams',
        'description' => 'A science fiction exploration of technology, AI, and humanity\'s future.',
        'genre' => 'Science Fiction',
        'status' => 'published'
    ],
    [
        'title' => 'Whispers of the Forest',
        'slug' => 'whispers-of-the-forest',
        'description' => 'A magical adventure in an enchanted forest filled with wonder.',
        'genre' => 'Fantasy',
        'status' => 'published'
    ],
    [
        'title' => 'The Forgotten Past',
        'slug' => 'the-forgotten-past',
        'description' => 'A historical saga spanning generations and revealing untold truths.',
        'genre' => 'Historical',
        'status' => 'published'
    ]
];

foreach ($stories as $story) {
    try {
        // Check if story already exists
        $check = $pdo->prepare("SELECT id FROM stories WHERE slug = ?");
        $check->execute([$story['slug']]);
        $existing = $check->fetch();
        
        if ($existing) {
            echo "⚠️  {$story['title']} already exists\n";
        } else {
            // Create new story
            $insert = $pdo->prepare("
                INSERT INTO stories (author_id, title, slug, description, genre, status, views, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
            ");
            $insert->execute([
                $author_id,
                $story['title'],
                $story['slug'],
                $story['description'],
                $story['genre'],
                $story['status']
            ]);
            echo "✅ Created: {$story['title']}\n";
        }
    } catch (Exception $e) {
        echo "❌ Error creating {$story['title']}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== DEMO STORIES CREATED ===\n";
$count = $pdo->query("SELECT COUNT(*) as cnt FROM stories")->fetch()['cnt'];
echo "Total stories: $count\n";
echo "\n✅ Visit http://localhost/ to see stories on homepage\n";
?>

