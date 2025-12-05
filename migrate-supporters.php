<?php
// Migrate existing story_support data to author_supporters
require 'config/db.php';

echo "=== Migrating story_support to author_supporters ===\n\n";

try {
    // Get all unique story_support records (author, supporter) combinations
    $stmt = $pdo->query("
        SELECT ss.author_id, ss.supporter_id, ss.story_id, 
               SUM(ss.points_amount) as total_points,
               MAX(ss.created_at) as created_at
        FROM story_support ss
        GROUP BY ss.author_id, ss.supporter_id
    ");
    
    $records = $stmt->fetchAll();
    echo "Found " . count($records) . " story_support records to migrate\n\n";
    
    $migrated = 0;
    foreach ($records as $record) {
        try {
            // Check if already exists
            $check = $pdo->prepare("
                SELECT id FROM author_supporters 
                WHERE author_id = ? AND supporter_id = ?
            ");
            $check->execute([$record['author_id'], $record['supporter_id']]);
            
            if ($check->fetch()) {
                // Already exists, skip
                continue;
            }
            
            // Insert the record
            $insert = $pdo->prepare("
                INSERT INTO author_supporters 
                (author_id, supporter_id, story_id, points_total, last_supported_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $record['author_id'],
                $record['supporter_id'],
                $record['story_id'],
                $record['total_points'],
                $record['created_at'],
                $record['created_at']
            ]);
            
            $migrated++;
            echo "✓ Migrated: Author {$record['author_id']}, Supporter {$record['supporter_id']}, Points {$record['total_points']}\n";
        } catch (Exception $e) {
            echo "✗ Error migrating record: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Migration complete! $migrated records migrated.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
