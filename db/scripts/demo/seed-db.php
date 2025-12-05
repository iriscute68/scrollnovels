<?php
/**
 * Seed basic data into the database
 */
try {
    $pdo = new PDO("mysql:host=localhost;dbname=scroll_novels", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Seeding Database...</h2>";
    
    // 1. Insert roles
    echo "<p><strong>1. Creating roles...</strong></p>";
    $roles = ['admin', 'author', 'user'];
    foreach ($roles as $role) {
        $pdo->exec("INSERT IGNORE INTO roles (name) VALUES ('$role')");
    }
    echo "✓ Roles created<br>";
    
    // 2. Insert sample user (admin)
    echo "<p><strong>2. Creating admin user...</strong></p>";
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (id, username, email, password_hash, avatar, created_at) 
               VALUES (1, 'admin', 'admin@scrollnovels.com', '$admin_pass', NULL, NOW())");
    echo "✓ Admin user created (username: admin, password: admin123)<br>";
    
    // 3. Assign admin role to admin user
    echo "<p><strong>3. Assigning roles...</strong></p>";
    $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) 
               SELECT 1, id FROM roles WHERE name = 'admin'");
    echo "✓ Admin role assigned<br>";
    
    // 4. Create sample stories
    echo "<p><strong>4. Creating sample stories...</strong></p>";
    $stories = [
        ['The Dragon Prince', 'A young hero discovers his destiny', 'fantasy', 'published'],
        ['Love in the City', 'A modern romance tale', 'romance', 'published'],
        ['Mystery of the Lost Temple', 'An adventure to find ancient treasures', 'adventure', 'published'],
    ];
    
    foreach ($stories as $idx => [$title, $desc, $cat, $status]) {
        $slug = strtolower(str_replace(' ', '-', $title));
        $pdo->exec("INSERT IGNORE INTO stories (author_id, title, slug, description, category, status, cover, views, created_at) 
                   VALUES (1, '$title', '$slug', '$desc', '$cat', '$status', NULL, 0, NOW())");
    }
    echo "✓ Sample stories created<br>";
    
    // 5. Create website rules
    echo "<p><strong>5. Creating website rules...</strong></p>";
    $rules = [
        ['No Spam', 'Do not post spam or repetitive content.', 'General'],
        ['Be Respectful', 'Treat others with kindness and respect.', 'Community'],
        ['No Plagiarism', 'Original content only. Respect intellectual property.', 'Content'],
        ['No Explicit Content', 'Keep content appropriate for general audiences.', 'Content'],
    ];
    
    foreach ($rules as [$title, $desc, $cat]) {
        $desc_esc = addslashes($desc);
        $pdo->exec("INSERT IGNORE INTO website_rules (title, description, category, created_at) 
                   VALUES ('$title', '$desc_esc', '$cat', NOW())");
    }
    echo "✓ Website rules created<br>";
    
    echo "<hr>";
    echo "<p style='color: green; font-size: 18px;'><strong>✓ Database seeded successfully!</strong></p>";
    echo "<p><strong>Admin Account:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <code>admin</code></li>";
    echo "<li>Password: <code>admin123</code></li>";
    echo "<li>Email: <code>admin@scrollnovels.com</code></li>";
    echo "</ul>";
    echo "<p><a href='index.php'>Go to Homepage</a> | <a href='pages/login.php'>Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Error:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><a href='check-db.php'>Back to DB Check</a></p>";
}
?>

