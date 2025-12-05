<?php
// create-dummy-data.php - Generate test book with chapters and reviews
require_once __DIR__ . '/config/db.php';

echo "🔄 Creating dummy test data...\n\n";

try {
    // Get first author
    $stmt = $pdo->query("SELECT id, username FROM users LIMIT 1");
    $author = $stmt->fetch();
    
    if (!$author) {
        // Create a test author
        $hashedPassword = password_hash('Test123!', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)")
            ->execute(['testauthor', 'author@test.com', $hashedPassword]);
        
        $author = ['id' => $pdo->lastInsertId(), 'username' => 'testauthor'];
        echo "✅ Created test author: {$author['username']}\n";
    } else {
        echo "ℹ️  Using existing author: {$author['username']}\n";
    }

    // Create dummy book
    $coverPath = '/uploads/covers/test-book-cover.jpg';
    
    // Check if test book already exists
    $existingBook = $pdo->query("SELECT id FROM stories WHERE slug = 'the-emerald-crown' LIMIT 1")->fetch();
    if ($existingBook) {
        $bookId = $existingBook['id'];
        echo "ℹ️  Test book already exists (ID: {$bookId})\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO stories 
            (title, slug, description, author_id, cover, category_id, tags, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW(), NOW())
        ");
        
        $stmt->execute([
            'The Emerald Crown',
            'the-emerald-crown',
            'A captivating tale of magic, mystery, and adventure. Follow Princess Elena as she discovers an ancient emerald crown that grants her extraordinary powers. But with great power comes great danger, and she must navigate political intrigue, dark magic, and a hidden prophecy to save her kingdom.',
            $author['id'],
            $coverPath,
            1, // Fiction category
            null // tags can be null
        ]);

        $bookId = $pdo->lastInsertId();
        echo "✅ Created test book: The Emerald Crown (ID: {$bookId})\n";
    }

    // Create dummy chapters
    $chapters = [
        ['title' => 'The Prophecy Awakens', 'content' => 'The stars aligned on the night of Elena\'s birth. An ancient prophecy, whispered for centuries, finally began to unfold. In the depths of the royal treasury, a crown of pure emerald gleamed with an otherworldly light...'],
        ['title' => 'Secrets of the Kingdom', 'content' => 'Elena discovered that not everyone in the kingdom could be trusted. Hidden alliances and ancient grudges threatened everything she held dear. She had to learn quickly who her true allies were.'],
        ['title' => 'The Dark Forest', 'content' => 'Deep within the forbidden forest, Elena found the source of the crown\'s power. But she was not alone. A mysterious figure emerged from the shadows, claiming to know the truth about her past...'],
        ['title' => 'The Final Battle', 'content' => 'All roads led to this moment. Elena stood face to face with the darkness that threatened her kingdom. With the emerald crown\'s power coursing through her veins, she made her final choice that would change everything forever...'],
    ];

    foreach ($chapters as $i => $chapter) {
        // Check if chapter already exists
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND number = ?");
        $stmt->execute([$bookId, $i + 1]);
        if (!$stmt->fetch()) {
            $pdo->prepare("
                INSERT INTO chapters (story_id, number, title, content, status, created_at)
                VALUES (?, ?, ?, ?, 'published', NOW())
            ")->execute([$bookId, $i + 1, $chapter['title'], $chapter['content']]);
        }
    }
    
    echo "✅ Test chapters verified\n";

    // Create dummy reviews
    $reviews = [
        ['rating' => 5, 'review' => 'Absolutely amazing! I couldn\'t put it down. The world-building is incredible and the characters feel so real.'],
        ['rating' => 5, 'review' => 'One of the best stories I\'ve read on this platform. The plot twists were shocking and satisfying.'],
        ['rating' => 4, 'review' => 'Great story with compelling characters. The magic system is unique and well-explained.'],
        ['rating' => 4, 'review' => 'Loved the main character\'s journey. Looking forward to more books in this series!'],
        ['rating' => 3, 'review' => 'Good story overall, but some chapters felt rushed. Still worth reading though.'],
    ];

    $preparedStmt = $pdo->prepare("SELECT id FROM users WHERE email != ? ORDER BY id ASC LIMIT 1");
    $preparedStmt->execute([$author['email'] ?? 'author@test.com']);
    $reviewer = $preparedStmt->fetch();
    
    if ($reviewer) {
        foreach ($reviews as $i => $review) {
            // Check if review already exists
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE story_id = ? AND user_id = ?");
            $stmt->execute([$bookId, $reviewer['id']]);
            if (!$stmt->fetch()) {
                $pdo->prepare("
                    INSERT INTO reviews (story_id, user_id, rating, content, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ")->execute([$bookId, $reviewer['id'], $review['rating'], $review['review']]);
            }
        }
        echo "✅ Test reviews verified\n";
    }

    // Update book stats
    $pdo->prepare("UPDATE stories SET views = 2847 WHERE id = ?")->execute([$bookId]);
    echo "✅ Added view stats to book\n";

    echo "\n✅ All dummy data created successfully!\n";
    echo "📖 Test Book ID: {$bookId}\n";
    echo "👤 Test Author: {$author['username']}\n";
    echo "📍 Visit: /pages/book.php?id={$bookId}\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

