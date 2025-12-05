<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain');

echo "=== CREATING DEMO CHAPTERS ===\n\n";

// Get all stories
$stories = $pdo->query("SELECT id, title FROM stories")->fetchAll();

$chapters_per_story = [
    'The Lost Kingdom' => [
        'Chapter 1: The Beginning',
        'Chapter 2: The Quest Begins',
        'Chapter 3: Allies and Enemies'
    ],
    'Midnight Chronicles' => [
        'Chapter 1: The Missing Clue',
        'Chapter 2: Secrets Unveiled',
        'Chapter 3: The Truth Emerges'
    ]
];

$count = 0;
foreach ($stories as $story) {
    $chapters = $chapters_per_story[$story['title']] ?? [];
    
    if (empty($chapters)) {
        $chapters = ['Chapter 1: Prologue', 'Chapter 2: The Adventure'];
    }
    
    foreach ($chapters as $index => $chapter_title) {
        try {
            // Check if chapter exists
            $check = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND title = ?");
            $check->execute([$story['id'], $chapter_title]);
            $existing = $check->fetch();
            
            if (!$existing) {
                $content = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. ";
                $content .= "This is sample content for $chapter_title in {$story['title']}. ";
                $content .= "Replace this with actual story content.";
                
                $insert = $pdo->prepare("
                    INSERT INTO chapters (story_id, title, content, chapter_number, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, 'published', NOW(), NOW())
                ");
                $insert->execute([
                    $story['id'],
                    $chapter_title,
                    $content,
                    $index + 1
                ]);
                $count++;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "✅ Created $count chapters\n";
echo "Visit http://localhost/ to view stories\n";
?>

