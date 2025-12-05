<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

// Create proclamations table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        images JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table already exists or error
}

// Create proclamation_likes table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (proclamation_id, user_id),
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table already exists or error
}

// Create proclamation_replies table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_proclamation (proclamation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table already exists or error
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Accept content from the form; map to existing DB columns (title, body)
$content = trim($_POST['content'] ?? '');
if (!$content) {
    echo json_encode(['success' => false, 'error' => 'Content is required']);
    exit;
}

// Build a title from the first sentence if not provided
$title = trim($_POST['title'] ?? '');
if (!$title) {
    $title = substr(strip_tags($content), 0, 120);
    if (strlen($title) >= 120) $title .= '...';
}

// Handle image uploads
$images = [];
if (!empty($_FILES['images'])) {
    $uploadDir = dirname(__DIR__) . '/uploads/proclamations/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if (is_uploaded_file($tmp_name)) {
            $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
            $filename = 'proc_' . $_SESSION['user_id'] . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $path = $uploadDir . $filename;
            
            if (move_uploaded_file($tmp_name, $path)) {
                $images[] = '/scrollnovels/uploads/proclamations/' . $filename;
            }
        }
    }
}

try {
    // Insert into existing table columns: title, body
    $stmt = $pdo->prepare("INSERT INTO proclamations (user_id, title, body, created_at) VALUES (?, ?, ?, NOW())");
    $ok = $stmt->execute([$_SESSION['user_id'], $title, $content]);
    
    if ($ok) {
        $procId = $pdo->lastInsertId();
        
        // Notify followers
        $stmt = $pdo->prepare("SELECT follower_id FROM followers WHERE following_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $followers = $stmt->fetchAll();
        
        foreach ($followers as $follower) {
                 notify($pdo, $follower['follower_id'], $_SESSION['user_id'], 'proclamation', 
                     "posted a new proclamation: " . substr($content, 0, 50),
                     "/pages/proclamations.php?id=" . $procId);
        }
        
        echo json_encode(['success' => true, 'proclamation_id' => $procId]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to post proclamation']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

