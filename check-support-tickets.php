<?php
require_once 'config/db.php';

echo "=== Checking Support Tickets Setup ===\n\n";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'support_tickets'");
    if ($stmt->rowCount() > 0) {
        echo "✓ support_tickets table exists\n";
        
        // Show structure
        echo "\nTable structure:\n";
        $cols = $pdo->query("DESCRIBE support_tickets")->fetchAll();
        foreach ($cols as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        
        // Count
        $count = $pdo->query("SELECT COUNT(*) FROM support_tickets")->fetchColumn();
        echo "\nTotal tickets: $count\n";
    } else {
        echo "✗ support_tickets table does NOT exist\n";
        echo "Creating it now...\n";
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'other',
            priority VARCHAR(20) DEFAULT 'medium',
            status VARCHAR(20) DEFAULT 'open',
            assigned_admin_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (status),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "✓ Created support_tickets table\n";
    }
    
    // Check ticket_replies too
    $stmt = $pdo->query("SHOW TABLES LIKE 'ticket_replies'");
    if ($stmt->rowCount() > 0) {
        echo "\n✓ ticket_replies table exists\n";
    } else {
        echo "\n✗ ticket_replies table does NOT exist\n";
        echo "Creating it now...\n";
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_admin_reply TINYINT DEFAULT 0,
            sender_type ENUM('User', 'Admin') DEFAULT 'User',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "✓ Created ticket_replies table\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
