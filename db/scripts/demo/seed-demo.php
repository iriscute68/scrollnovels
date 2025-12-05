<?php
// seed-demo.php - Create demo story with chapters for homepage
require_once __DIR__ . '/../../../config/db.php';

try {
    // Create or get demo author
    $demo_user = [
        'username' => 'demoauthor',
        'email' => 'demo@scrollnovels.com',
        'password' => password_hash('demo123', PASSWORD_BCRYPT),
        'role' => 'author'
    ];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$demo_user['username']]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(array_values($demo_user));
        $authorId = $pdo->lastInsertId();
        echo "✅ Created demo author (ID: $authorId)\n";
    } else {
        $authorId = $user['id'];
        echo "✅ Demo author exists (ID: $authorId)\n";
    }

    // Create demo story
    $storyData = [
        'author_id' => $authorId,
        'title' => 'The Chronical Chronicles: A Tale of Adventure',
        'slug' => 'chronical-chronicles-adventure',
        'synopsis' => 'Join Aria on an epic journey across mystical realms filled with dragons, magic, and untold secrets. Will she find her destiny?',
        'genre' => 'Fantasy',
        'cover_image' => '/assets/img/demo-cover.png'
    ];

            
    $story = $stmt->fetch();
            
    if (!$story) {
        $stmt = $pdo->prepare("
            INSERT INTO stories (author_id, title, slug, synopsis, genre, status, cover_image, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $storyData['author_id'],
            $storyData['title'],
            $storyData['slug'],
            $storyData['synopsis'],
            $storyData['genre'],
            $storyData['status'],
            $storyData['cover_image']
        ]);
        $storyId = $pdo->lastInsertId();
        echo "✅ Created demo story (ID: $storyId)\n";
    } else {
        $storyId = $story['id'];
        echo "✅ Demo story exists (ID: $storyId)\n";
    }

    // Prefer 'number' column if present (newer schema), otherwise fall back to 'sequence'
    $seqCol = 'sequence';
    try {
        $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chapters' AND COLUMN_NAME = 'number'");
        $existsStmt->execute();
        if ($existsStmt->fetchColumn() > 0) {
            $seqCol = 'number';
        }
    } catch (Exception $e) {
        // If the query fails for some reason, keep default 'sequence'
    }

    // Create demo chapters
    $chapters = [
        ['Chapter 1: The Beginning', 'Aria wakes up in a strange land with no memory of how she got there. A mysterious figure offers her a map.', 1],
        ['Chapter 2: The Forest of Echoes', 'Deep in the enchanted forest, Aria discovers talking animals and ancient ruins. She uncovers her first clue.', 2],
        ['Chapter 3: The Dragon\'s Lair', 'Facing her greatest fear, Aria confronts a legendary dragon. Their conversation changes everything.', 3],
    ];

    foreach ($chapters as $ch) {
        $title = $ch[0];
        $content = $ch[1];
        $seq = $ch[2];
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND $seqCol = ?");
        $stmt->execute([$storyId, $seq]);
        if (!$stmt->fetchColumn()) {
            $stmt = $pdo->prepare("
                INSERT INTO chapters (story_id, title, $seqCol, content, status, published_at, word_count)
                VALUES (?, ?, ?, ?, 'published', NOW(), ?)
            ");
            $wordCount = str_word_count($content);
            $stmt->execute([$storyId, $title, $seq, $content, $wordCount]);
            echo "✅ Created chapter: $title\n";
        }
    }

    // Update story stats
    $pdo->prepare("
        UPDATE story_meta 
        SET total_chapters = (SELECT COUNT(*) FROM chapters WHERE story_id = ?),
            total_words = (SELECT SUM(word_count) FROM chapters WHERE story_id = ?)
        WHERE story_id = ?
    ")->execute([$storyId, $storyId, $storyId]);

    echo "\n✅ Demo seed data created successfully!\n";
    echo "🌟 Visit http://localhost/ to see the demo story on the homepage\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

