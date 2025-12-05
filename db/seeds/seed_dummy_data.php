<?php
// Seed dummy authors, stories, chapters, and announcements for testing
require_once __DIR__ . '/../../inc/db.php';

function tableExists($pdo, $name) {
    try { $r = $pdo->query("SHOW TABLES LIKE '" . $name . "'")->fetch(); return (bool)$r; } catch (Exception $e) { return false; }
}

try {
    // create 5 demo users
    if (tableExists($pdo, 'users')) {
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $un = "demo_user_$i";
            $email = "demo{$i}@example.test";
            $hash = password_hash('password', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, 'author', NOW())");
            $stmt->execute([$un, $email, $hash]);
            $users[] = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM users WHERE username = '".$un."'")->fetchColumn();
        }
        echo "Inserted demo users\n";
    } else {
        echo "users table missing; skipping users seed\n";
    }

    // Insert demo stories into either 'stories' or 'books' depending on schema
    $storyTable = tableExists($pdo, 'stories') ? 'stories' : (tableExists($pdo, 'books') ? 'books' : null);
    if ($storyTable && !empty($users)) {
        for ($i = 1; $i <= 10; $i++) {
            $title = "Demo Story #$i";
            $syn = "A short synopsis for demo story $i";
            $author_id = $users[array_rand($users)];
            if ($storyTable === 'stories') {
                $ins = $pdo->prepare("INSERT INTO stories (author_id, title, slug, synopsis, cover, status, created_at) VALUES (?, ?, ?, ?, ?, 'published', NOW())");
                $ins->execute([$author_id, $title, 'demo-story-'.$i, $syn, '']);
            } else {
                $ins = $pdo->prepare("INSERT INTO books (user_id, title, synopsis, genre, cover_url, created_at) VALUES (?, ?, ?, '', '', NOW())");
                $ins->execute([$author_id, $title, $syn]);
            }
        }
        echo "Inserted demo stories into {$storyTable}\n";
    } else {
        echo "No story/book table found; skipping stories\n";
    }

    // Announcements sample
    if (tableExists($pdo, 'announcements')) {
        $a = $pdo->prepare("INSERT INTO announcements (title, summary, content, author_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $a->execute(['Welcome to Scroll Novels', 'Demo data loaded', 'This site was populated with demo stories and users for testing.', $users[0] ?? null]);
        echo "Inserted demo announcement\n";
    }

    // Insert chapters for stories if chapters table exists
    if ($storyTable && tableExists($pdo, 'chapters')) {
        $storiesIds = [];
        if ($storyTable === 'stories') {
            $r = $pdo->query("SELECT id FROM stories ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $r = $pdo->query("SELECT id FROM books ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
        }
        foreach ($r as $sid) {
            for ($c = 1; $c <= 3; $c++) {
                try {
                    if ($storyTable === 'stories') {
                        $insCh = $pdo->prepare("INSERT INTO chapters (story_id, title, body, created_at) VALUES (?, ?, ?, NOW())");
                        $insCh->execute([$sid, "Chapter $c", "This is the content for chapter $c of story $sid."]);                        
                    } else {
                        // books may use chapters table with book_id
                        $insCh = $pdo->prepare("INSERT INTO chapters (story_id, title, body, created_at) VALUES (?, ?, ?, NOW())");
                        $insCh->execute([$sid, "Chapter $c", "This is the content for chapter $c of story $sid."]);                        
                    }
                } catch (Exception $e) {
                    // ignore per-row failures
                }
            }
        }
        echo "Inserted sample chapters where possible\n";
    }

    // Create some demo artists and editors if users table exists
    if (tableExists($pdo, 'users')) {
        for ($i = 6; $i <= 8; $i++) {
            $un = "artist_{$i}";
            $email = "artist{$i}@example.test";
            $hash = password_hash('password', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$un, $email, $hash, 'artist']);
        }
        for ($i = 9; $i <= 10; $i++) {
            $un = "editor_{$i}";
            $email = "editor{$i}@example.test";
            $hash = password_hash('password', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$un, $email, $hash, 'editor']);
        }
        echo "Inserted demo artists and editors\n";
    }

    // Seed simple chat messages if table exists
    if (tableExists($pdo, 'messages') || tableExists($pdo, 'chat_messages')) {
        $msgTable = tableExists($pdo, 'messages') ? 'messages' : 'chat_messages';
        try {
            $insm = $pdo->prepare("INSERT INTO {$msgTable} (user_id, message, created_at) VALUES (?, ?, NOW())");
            $uid = $users[0] ?? null;
            if ($uid) {
                $insm->execute([$uid, 'Hello! This is a demo chat message.']);
                $insm->execute([$users[1] ?? $uid, 'Welcome to the demo chat!']);
            }
            echo "Seeded demo chat messages into {$msgTable}\n";
        } catch (Exception $e) {}
    }

    echo "Dummy seed complete.\n";
} catch (Exception $e) {
    echo "Seed error: " . $e->getMessage() . "\n";
}
