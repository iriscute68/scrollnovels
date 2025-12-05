<?php
// inc/header.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Load latest announcements (limit 5)
$announcements_stmt = $pdo->query("SELECT id, title, summary, created_at FROM announcements ORDER BY created_at DESC LIMIT 5");
$announcements = $announcements_stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Scroll Novels</title>
  <link rel="stylesheet" href="/assets/css/theme.css">
  <script defer src="/assets/js/main.js"></script>
</head>
<body class="royal-bg">
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/"><span class="logo">📖</span> <span class="brand-title">Scroll Novels</span></a>

    <nav class="primary-nav">
      <a href="/fiction.php">Fiction</a>
      <a href="/rankings.php">Rankings</a>
      <a href="/competitions/">Contests</a>
      <a href="/forums.php">Forums</a>
      <a href="/community.php">Community</a>
      <a href="/blog.php">Blog</a>
    </nav>

    <div class="header-actions">
      <button id="site-search-btn" class="icon-btn" title="Search" aria-label="Search">🔍</button>

      <div class="ann-wrapper">
        <button id="ann-toggle" class="icon-btn" aria-label="Announcements" title="Announcements">🔔
          <span id="ann-count" class="badge"><?= count($announcements) ?></span>
        </button>

        <div id="ann-panel" class="ann-panel" aria-hidden="true">
          <h4>Proclamations</h4>
          <ul>
            <?php foreach ($announcements as $ann): ?>
              <li>
                <strong><?= htmlspecialchars($ann['title']) ?></strong>
                <div class="muted"><?= date('M j, Y', strtotime($ann['created_at'])) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <a href="/blog.php" class="small-link">See all posts</a>
        </div>
      </div>

      <?php if (is_admin()): ?>
        <a href="/admin/blog_create.php" class="btn btn-gold">Create</a>
      <?php endif; ?>

    </div>
  </div>

  <!-- announcement ticker below header -->
  <div class="announcement-ticker">
    <div class="ticker-inner">
      📜 The Winter Crusade begins in 3 days – Prepare your tales!
    </div>
  </div>
</header>

<main id="page-main" class="page-main page-with-sidebar">
  <aside class="site-sidebar">
    <div class="sidebar-section">
      <h4>📚 Navigation</h4>
      <ul>
        <li><a href="/fiction.php">All Stories</a></li>
        <li><a href="/rankings.php">Rankings</a></li>
        <li><a href="/competitions/">Active Contests</a></li>
      </ul>
    </div>

    <div class="sidebar-section">
      <h4>🎯 Quick Links</h4>
      <ul>
        <li><a href="/blog.php">Latest Blog Posts</a></li>
        <li><a href="/community.php">Community</a></li>
        <li><a href="/forums.php">Forums</a></li>
      </ul>
    </div>

    <?php if (is_admin()): ?>
    <div class="sidebar-section">
      <h4>⚙️ Admin</h4>
      <ul>
        <li><a href="/admin/">Dashboard</a></li>
        <li><a href="/admin/competitions.php">Manage Contests</a></li>
        <li><a href="/admin/competition_judging.php">Judging</a></li>
        <li><a href="/admin/competition_payouts.php">Payouts</a></li>
      </ul>
    </div>
    <?php endif; ?>
  </aside>

  <div class="page-content">
<!-- page-specific content begins after this include -->
