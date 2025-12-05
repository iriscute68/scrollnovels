<?php
require 'config/db.php';

echo "=== Applying Moderation Migrations ===\n\n";

try {
    // Add suspension_until column if it doesn't exist
    $pdo->exec("ALTER TABLE users ADD COLUMN suspension_until DATETIME NULL DEFAULT NULL AFTER status");
    echo "✓ Added suspension_until column to users table\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✓ suspension_until column already exists\n";
    } else {
        echo "✗ Error adding suspension_until: " . $e->getMessage() . "\n";
    }
}

try {
    // Create user_mutes table (without moderator_id FK to avoid constraint issues)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_mutes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            moderator_id INT,
            reason VARCHAR(255),
            muted_until DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created user_mutes table\n";
} catch (Exception $e) {
    echo "✗ Error creating user_mutes: " . $e->getMessage() . "\n";
}

try {
    // Create user_moderation_log table (without moderator_id FK to avoid constraint issues)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_moderation_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            moderator_id INT,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            reason VARCHAR(255),
            duration_days INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created user_moderation_log table\n";
} catch (Exception $e) {
    echo "✗ Error creating user_moderation_log: " . $e->getMessage() . "\n";
}

try {
    // Create indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_mutes_muted_until ON user_mutes(muted_until)");
    echo "✓ Created index on user_mutes.muted_until\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate key') !== false) {
        echo "✓ Index on user_mutes.muted_until already exists\n";
    } else {
        echo "✗ Error creating index: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_moderation_log_user ON user_moderation_log(user_id)");
    echo "✓ Created index on user_moderation_log.user_id\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate key') !== false) {
        echo "✓ Index on user_moderation_log.user_id already exists\n";
    } else {
        echo "✗ Error creating index: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migration Complete ===\n";
?>
