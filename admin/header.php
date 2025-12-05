<?php
// Admin header wrapper — loads Tailwind + site CSS + admin CSS
if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure roles helper available
if (file_exists(__DIR__ . '/../inc/roles_permissions.php')) {
    require_once __DIR__ . '/../inc/roles_permissions.php';
}

// Check for admin/inc/header.php compatibility
if (file_exists(__DIR__ . '/inc/header.php')) {
    require_once __DIR__ . '/inc/header.php';
    return;
}

// Fallback minimal header with full styling support
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="stylesheet" href="<?= rtrim(SITE_URL, '/') ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= rtrim(SITE_URL, '/') ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?= rtrim(SITE_URL, '/') ?>/admin/css/admin.css">
    <style>
        :root { --transition-base: 200ms ease-in-out; }
        body { transition: background-color var(--transition-base), color var(--transition-base); }
    </style>
</head>
<body class="admin-area bg-gray-900 text-gray-100">
<div class="admin-wrapper">
