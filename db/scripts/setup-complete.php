<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Scroll Novels - Complete Database Setup & Fix</h1>";

// Step 1: Check MySQL connection
echo "<hr><h2>Step 1: Testing MySQL Connection</h2>";
try {
    // First connect without database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "✓ Connected to MySQL<br>";
    
    // Step 2: Create database
    echo "<hr><h2>Step 2: Creating Database</h2>";
    $pdo->exec("DROP DATABASE IF EXISTS scroll_novels");
    echo "✓ Dropped old database (if existed)<br>";
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS scroll_novels DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Created database 'scroll_novels'<br>";
    
    // Step 3: Switch to new database
    $pdo->exec("USE scroll_novels");
    echo "✓ Switched to scroll_novels database<br>";
    
    // Step 4: Create all tables
    echo "<hr><h2>Step 3: Creating Tables</h2>";
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            bio TEXT,
            roles JSON DEFAULT '[\"reader\"]',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created users table<br>";
    
    // Roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL UNIQUE,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created roles table<br>";
    
    // User roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT UNSIGNED NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created user_roles table<br>";
    
    // Categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created categories table<br>";
    
    // Stories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            author_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            description TEXT,
            category VARCHAR(100),
            cover VARCHAR(255),
            status VARCHAR(50) DEFAULT 'draft',
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created stories table<br>";
    
    // Chapters table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chapters (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            story_id INT UNSIGNED NOT NULL,
            number INT NOT NULL,
            title VARCHAR(255),
            content LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created chapters table<br>";
    
    // Website rules table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS website_rules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'General',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created website_rules table<br>";
    
    // Interactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS interactions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL,
            target_id INT UNSIGNED,
            target_type VARCHAR(50),
            user_id INT UNSIGNED,
            value INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created interactions table<br>";
    
    // Reviews table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            story_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            rating INT,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created reviews table<br>";
    
    // Saved stories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saved_stories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            story_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_story (user_id, story_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created saved_stories table<br>";
    
    // Step 5: Seed initial data
    echo "<hr><h2>Step 4: Seeding Initial Data</h2>";
    
    // Insert roles
    $pdo->exec("INSERT IGNORE INTO roles (name) VALUES ('admin'), ('author'), ('user')");
    echo "✓ Inserted roles<br>";
    
    // Insert admin user
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (username, email, password_hash, roles, created_at) 
               VALUES ('admin', 'admin@scrollnovels.com', '$admin_pass', '[\"admin\"]', NOW())");
    echo "✓ Inserted admin user<br>";
    
    // Assign admin role
    $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) 
               SELECT u.id, r.id FROM users u, roles r WHERE u.username = 'admin' AND r.name = 'admin'");
    echo "✓ Assigned admin role<br>";
    
    // Insert sample stories
    $pdo->exec("INSERT IGNORE INTO stories (author_id, title, slug, description, category, status, created_at)
               VALUES 
               (1, 'The Dragon Prince', 'the-dragon-prince', 'A young hero discovers his destiny', 'fantasy', 'published', NOW()),
               (1, 'Love in the City', 'love-in-the-city', 'A modern romance tale', 'romance', 'published', NOW()),
               (1, 'Mystery of the Lost Temple', 'mystery-lost-temple', 'An adventure to find ancient treasures', 'adventure', 'published', NOW())");
    echo "✓ Inserted sample stories<br>";
    
    // Insert website rules
    $pdo->exec("INSERT IGNORE INTO website_rules (title, description, category, created_at)
               VALUES 
               ('No Spam', 'Do not post spam or repetitive content.', 'General', NOW()),
               ('Be Respectful', 'Treat others with kindness and respect.', 'Community', NOW()),
               ('No Plagiarism', 'Original content only. Respect intellectual property.', 'Content', NOW()),
               ('No Explicit Content', 'Keep content appropriate for general audiences.', 'Content', NOW())");
    echo "✓ Inserted website rules<br>";
    
    // Step 6: Verify
    echo "<hr><h2>Step 5: Verification</h2>";
    $tables_result = $pdo->query("SHOW TABLES");
    $tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Total tables created: " . count($tables) . "<br>";
    
    $users_result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $users_count = $users_result->fetch()['count'];
    echo "✓ Users in database: " . $users_count . "<br>";
    
    $stories_result = $pdo->query("SELECT COUNT(*) as count FROM stories");
    $stories_count = $stories_result->fetch()['count'];
    echo "✓ Stories in database: " . $stories_count . "<br>";
    
    echo "<hr>";
    echo "<h2 style='color: green;'>✅ Database Setup Complete!</h2>";
    echo "<p><strong>Admin Account:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <code>admin</code></li>";
    echo "<li>Password: <code>admin123</code></li>";
    echo "<li>Email: <code>admin@scrollnovels.com</code></li>";
    echo "</ul>";
    echo "<p><strong>Sample Data:</strong></p>";
    echo "<ul>";
    echo "<li>" . $users_count . " user(s)</li>";
    echo "<li>" . $stories_count . " story/stories</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<p><a href='http://localhost/scrollnovels/index.php' style='font-size: 18px; padding: 10px 20px; background: green; color: white; text-decoration: none; border-radius: 5px;'>→ Go to Homepage</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ ERROR:</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Make sure MySQL/MariaDB is running in XAMPP</p>";
}
?>

