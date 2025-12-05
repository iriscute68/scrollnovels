<?php
require_once __DIR__ . '/../config/db.php';
try {
    $user_id = 1; // Change to a valid user id in your DB
    $subject = 'Test ticket from CLI';
    $description = 'This is a test ticket created by a script to validate support_tickets table.';
    $category = 'bug';
    $priority = 'medium';

    // Determine whether description or message column
    $cols = $pdo->query("SHOW COLUMNS FROM support_tickets")->fetchAll(PDO::FETCH_COLUMN);
    $descCol = in_array('description', $cols) ? 'description' : (in_array('message', $cols) ? 'message' : 'description');

    $sql = "INSERT INTO support_tickets (user_id, subject, {$descCol}, category, priority, status) VALUES (?, ?, ?, ?, ?, 'open')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $subject, $description, $category, $priority]);
    echo "Inserted ticket id: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>