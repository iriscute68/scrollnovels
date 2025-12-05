<?php
// Migrate author_supporters table to fix story_id and UNIQUE KEY

require_once __DIR__ . '/config/db.php';

echo "<h1>Migrating author_supporters table...</h1>";

try {
    // Check if the table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'author_supporters'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "<p>Table doesn't exist yet - will be created on first use</p>";
        exit;
    }
    
    echo "<h2>Step 1: Backup existing data</h2>";
    
    // First, get all records to consolidate by (author_id, supporter_id)
    $stmt = $pdo->query("SELECT author_id, supporter_id, SUM(points_total) as total_points, MAX(last_supported_at) as last_supported_at FROM author_supporters GROUP BY author_id, supporter_id");
    $consolidated = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($consolidated) . " unique supporter relationships</p>";
    
    echo "<h2>Step 2: Drop old unique constraint</h2>";
    try {
        $pdo->exec("ALTER TABLE author_supporters DROP KEY unique_support");
        echo "<p>Dropped old UNIQUE constraint</p>";
    } catch (Exception $e) {
        echo "<p>Could not drop constraint (might not exist): " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>Step 3: Update story_id to 0 where NULL</h2>";
    $pdo->exec("UPDATE author_supporters SET story_id = 0 WHERE story_id IS NULL");
    echo "<p>Updated story_id values</p>";
    
    echo "<h2>Step 4: Consolidate records</h2>";
    // Delete duplicates keeping only one per author_id, supporter_id
    $pdo->exec("DELETE FROM author_supporters WHERE id NOT IN (
        SELECT id FROM (
            SELECT MIN(id) as id FROM author_supporters GROUP BY author_id, supporter_id
        ) t
    )");
    echo "<p>Deleted duplicate records</p>";
    
    echo "<h2>Step 5: Add new UNIQUE KEY</h2>";
    $pdo->exec("ALTER TABLE author_supporters ADD UNIQUE KEY unique_support (author_id, supporter_id)");
    echo "<p>Added new UNIQUE constraint on (author_id, supporter_id)</p>";
    
    echo "<h2>Success!</h2>";
    echo "<p>author_supporters table has been migrated successfully</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>";
    var_dump($e);
    echo "</pre>";
}
?>
