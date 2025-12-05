<?php
// Verify and fix table schemas for admin
require_once __DIR__ . '/config/db.php';

try {
    echo "=== Verifying Admin Tables ===\n\n";

    // 1. Check and fix verification_requests table
    $check = $pdo->query("SHOW TABLES LIKE 'verification_requests'");
    if ($check->rowCount() > 0) {
        $columns = $pdo->query("DESCRIBE verification_requests")->fetchAll();
        $colNames = array_column($columns, 'Field');
        
        if (!in_array('status', $colNames)) {
            $pdo->exec("ALTER TABLE verification_requests ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
            echo "✓ Added status column to verification_requests\n";
        } else {
            echo "✓ verification_requests has status column\n";
        }
    } else {
        echo "✗ verification_requests table not found\n";
    }

    // 2. Check donations table
    $check = $pdo->query("SHOW TABLES LIKE 'donations'");
    if ($check->rowCount() > 0) {
        $columns = $pdo->query("DESCRIBE donations")->fetchAll();
        $colNames = array_column($columns, 'Field');
        
        if (!in_array('status', $colNames)) {
            $pdo->exec("ALTER TABLE donations ADD COLUMN status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending'");
            echo "✓ Added status column to donations\n";
        } else {
            echo "✓ donations has status column\n";
        }
    } else {
        echo "✗ donations table not found\n";
    }

    // 3. Check ads table
    $check = $pdo->query("SHOW TABLES LIKE 'ads'");
    if ($check->rowCount() > 0) {
        $columns = $pdo->query("DESCRIBE ads")->fetchAll();
        $colNames = array_column($columns, 'Field');
        
        if (!in_array('status', $colNames)) {
            $pdo->exec("ALTER TABLE ads ADD COLUMN status ENUM('active', 'inactive', 'expired', 'pending') DEFAULT 'pending'");
            echo "✓ Added status column to ads\n";
        } else {
            echo "✓ ads has status column\n";
        }
    } else {
        echo "✗ ads table not found\n";
    }

    // 4. Test the queries from admin.php
    echo "\n=== Testing Admin Queries ===\n";
    
    $queries = [
        'pending_verification' => "SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'",
        'total_donations' => "SELECT COALESCE(SUM(amount),0) FROM donations WHERE status = 'completed'",
        'active_ads' => "SELECT COUNT(*) FROM ads WHERE status = 'active'",
    ];

    foreach ($queries as $name => $query) {
        try {
            $result = $pdo->query($query)->fetchColumn();
            echo "✓ $name: $result\n";
        } catch (Exception $e) {
            echo "✗ $name: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== All checks complete ===\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
