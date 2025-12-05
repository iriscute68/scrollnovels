<?php
// pages/supporter-setup.php - Initialize supporter system tables
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';

// Create supporters table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS supporters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supporter_id INT NOT NULL,
        author_id INT NOT NULL,
        tip_amount DECIMAL(10, 2) DEFAULT 0,
        patreon_tier VARCHAR(100),
        kofi_reference VARCHAR(255),
        patreon_pledge_id VARCHAR(255),
        status ENUM('active', 'cancelled', 'pending') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_support (supporter_id, author_id),
        INDEX idx_author (author_id),
        INDEX idx_supporter (supporter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo json_encode(['success' => true, 'message' => 'Supporters table created']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Create author_links table for Ko-fi and Patreon
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS author_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_id INT NOT NULL,
        link_type ENUM('kofi', 'patreon', 'paypal') NOT NULL,
        link_url VARCHAR(500) NOT NULL,
        patreon_access_token VARCHAR(500),
        patreon_refresh_token VARCHAR(500),
        patreon_expires_at TIMESTAMP NULL,
        is_verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_author_type (author_id, link_type),
        INDEX idx_author (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo json_encode(['success' => true, 'message' => 'Author links table created']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Create patreon_webhooks table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS patreon_webhooks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(255) UNIQUE NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        webhook_data LONGTEXT,
        processed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo json_encode(['success' => true, 'message' => 'Patreon webhooks table created']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Create top_supporters view (materialized with a table)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS top_supporters_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_id INT NOT NULL,
        supporter_id INT NOT NULL,
        supporter_name VARCHAR(100),
        tip_amount DECIMAL(10, 2),
        patreon_tier VARCHAR(100),
        supporter_avatar VARCHAR(500),
        rank INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_author_rank (author_id, rank)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo json_encode(['success' => true, 'message' => 'Top supporters cache table created']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

echo "All supporter system tables initialized successfully!";
?>
