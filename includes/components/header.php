<?php
// includes/header.php - DOCTYPE + Nav (merged variants; Tailwind CDN for now)
if (!defined('SITE_URL')) die('No direct access.');

// Ensure helpers are available when header is included directly
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';
?>
<!DOCTYPE html>
<html lang="en" class="dark">  <!-- Dark mode default -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Scroll Novels') ?></title>
    <link rel="stylesheet" href="https://cdn.tailwindcss.com">  <!-- Dev; swap to compiled -->
    <link rel="stylesheet" href="<?= asset_url('css/global.css') ?>">
    <script src="<?= asset_url('js/app.js') ?>" defer></script>  <!-- Theme toggle etc. -->
    <script>
        // Expose SITE_URL to client-side scripts
        window.SITE_URL = '<?= rtrim(SITE_URL, '/') ?>';
    </script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <header class="bg-gray-800 p-4 shadow-md">
        <nav class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="<?= rtrim(SITE_URL, '/') ?>" class="text-2xl font-bold text-emerald-400">Scroll Novels</a>
            <div class="flex items-center space-x-6">
                <ul class="flex space-x-4">
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>" class="hover:text-emerald-400">Home</a></li>
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/webtoon.php" class="hover:text-emerald-400">Webtoon</a></li>
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/fanfic.php" class="hover:text-emerald-400">Fanfic</a></li>
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/artist.php" class="hover:text-emerald-400">Artists</a></li>
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/editor.php" class="hover:text-emerald-400">Editors</a></li>
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/website-rules.php" class="hover:text-emerald-400">Rules</a></li>
                    <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/community.php" class="hover:text-emerald-400">Community</a></li>
                </ul>

                <div class="flex items-center space-x-3">
                    <button id="theme-toggle" class="px-2 py-1 bg-gray-700 rounded">🌗</button>
                    <?php if (isLoggedIn()): ?>
                        <a class="nav-profile hover:text-emerald-400" href="<?= rtrim(SITE_URL, '/') ?>/pages/profile.php">My Account</a>
                        <?php if (isRole('admin')): ?><a href="<?= rtrim(SITE_URL, '/') ?>/admin/" class="text-red-400">Admin</a><?php endif; ?>
                        <a href="<?= rtrim(SITE_URL, '/') ?>/pages/logout.php" class="hover:text-emerald-400">Logout (<?= htmlspecialchars($_SESSION['username'] ?? '') ?>)</a>
                    <?php else: ?>
                        <a href="<?= rtrim(SITE_URL, '/') ?>/pages/login.php" class="hover:text-emerald-400">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    <main class="container mx-auto p-4">
    <script>
    (function(){
        const btn = document.getElementById('theme-toggle');
        const apply = (t)=>{
            if (t === 'dark') document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        };
        const saved = localStorage.getItem('site_theme') || 'dark';
        apply(saved);
        btn && btn.addEventListener('click', ()=>{
            const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            apply(next); localStorage.setItem('site_theme', next);
        });
    })();
    </script>