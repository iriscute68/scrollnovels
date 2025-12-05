<?php
// admin/inc/header.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Use main site database connection for config only
require_once __DIR__ . '/config.php';
$config = require __DIR__ . '/config.php';
$site = $config['site'] ?? ['name' => 'Scroll Novels'];

if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
  if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php', true, 302);
    exit;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($site['name'] ?? 'Admin') ?> — Admin Panel</title>
  
  <!-- Tailwind CDN for quick usage -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Chart.js for analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Custom Styles -->
  <link rel="stylesheet" href="/admin/css/custom.css">
  <!-- Site theme fallback to keep admin UI consistent with main site -->
  <link rel="stylesheet" href="/assets/css/theme.css">
  <style>
    body {
      background: #0b0d10;
      color: #eceff1;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
    }
  </style>
</head>
<body class="bg-[#0b0d10] text-[#eceff1]">
<div class="flex">
