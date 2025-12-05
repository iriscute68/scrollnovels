<?php
// admin/dashboard.php - Redirect to new admin interface
session_start();

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

if (!in_array($_SESSION['admin_role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
    session_destroy();
    header('Location: login.php', true, 302);
    exit;
}

header('Location: admin.php?page=dashboard', true, 302);
exit;
