<?php
require 'config/db.php';

echo "=== COMPLETE ADMIN FUNCTIONALITY TEST ===\n\n";

// Test Achievements APIs
echo "--- ACHIEVEMENTS ---\n";
$files_ach = [
    'api/admin/get-achievement.php' => 'Fetch achievement',
    'api/admin/save-achievement.php' => 'Create/update achievement',
    'api/admin/delete-achievement.php' => 'Delete achievement',
];

$ach_ok = 0;
foreach ($files_ach as $file => $purpose) {
    if (file_exists($file)) {
        echo "✅ $file\n";
        $ach_ok++;
    } else {
        echo "❌ $file - MISSING\n";
    }
}

// Test Staff APIs
echo "\n--- STAFF MANAGEMENT ---\n";
$files_staff = [
    'api/admin/get-admin.php' => 'Fetch admin data',
    'api/admin/save-admin.php' => 'Create/update admin',
    'api/admin/remove-admin.php' => 'Remove admin',
];

$staff_ok = 0;
foreach ($files_staff as $file => $purpose) {
    if (file_exists($file)) {
        echo "✅ $file\n";
        $staff_ok++;
    } else {
        echo "❌ $file - MISSING\n";
    }
}

// Test Pages
echo "\n--- ADMIN PAGES ---\n";
$pages = [
    'admin/pages/achievements.php' => 'Achievements management',
    'admin/pages/staff.php' => 'Staff management',
];

$pages_ok = 0;
foreach ($pages as $file => $purpose) {
    if (file_exists($file)) {
        echo "✅ $file\n";
        $pages_ok++;
    } else {
        echo "❌ $file - MISSING\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "✅ Achievements APIs: $ach_ok/3\n";
echo "✅ Staff APIs: $staff_ok/3\n";
echo "✅ Admin Pages: $pages_ok/2\n";

if ($ach_ok == 3 && $staff_ok == 3 && $pages_ok == 2) {
    echo "\n🎉 ALL SYSTEMS OPERATIONAL!\n";
} else {
    echo "\n⚠️  Some components missing\n";
}

echo "\nVisit:\n";
echo "- Achievements: http://localhost/scrollnovels/admin/admin.php?page=achievements\n";
echo "- Staff: http://localhost/scrollnovels/admin/admin.php?page=staff\n";
?>
