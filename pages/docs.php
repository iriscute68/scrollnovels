<?php
// Documentation Index Generator
?>
<!DOCTYPE html>
<html>
<head>
    <title>ScrollNovels - Documentation Index</title>
    <!-- Tailwind CDN removed for production; using local styles -->
</head>
<body class="bg-gray-50 dark:bg-gray-900">
<div class="max-w-4xl mx-auto p-8">
    
    <h1 class="text-4xl font-bold mb-8 text-gray-900 dark:text-white">📚 ScrollNovels Documentation</h1>

    <!-- Quick Start -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-emerald-500">
        <h2 class="text-2xl font-bold mb-4 text-emerald-600 dark:text-emerald-400">🚀 Quick Start</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="/status.php" class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded hover:bg-emerald-100 transition">
                <strong>📊 Platform Status</strong><br>
                <small>Current system status & quick links</small>
            </a>
            <a href="/pages/login.php" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded hover:bg-blue-100 transition">
                <strong>🔐 User Login</strong><br>
                <small>testuser / testuser123</small>
            </a>
            <a href="/admin/" class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded hover:bg-purple-100 transition">
                <strong>👨‍💼 Admin Panel</strong><br>
                <small>admin / admin123</small>
            </a>
            <a href="/" class="p-4 bg-green-50 dark:bg-green-900/20 rounded hover:bg-green-100 transition">
                <strong>🏠 Homepage</strong><br>
                <small>See demo stories</small>
            </a>
        </div>
    </div>

    <!-- Features -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-blue-500">
        <h2 class="text-2xl font-bold mb-4 text-blue-600 dark:text-blue-400">✨ Feature Documentation</h2>
        <div class="space-y-3">
            <a href="/pages/achievements.php" class="block p-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition">
                <strong>🏆 Achievement System</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">30 achievements with progress tracking</p>
            </a>
            <a href="/pages/announcements.php" class="block p-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition">
                <strong>📢 Author Announcements</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create announcements for followers</p>
            </a>
            <div class="block p-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded">
                <strong>📬 Notifications API</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">Follow notifications & achievement unlocks</p>
            </div>
        </div>
    </div>

    <!-- Database -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-purple-500">
        <h2 class="text-2xl font-bold mb-4 text-purple-600 dark:text-purple-400">🗄️ Database Info</h2>
        <div class="space-y-3">
            <a href="/db-status.php" class="block p-3 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded transition">
                <strong>Database Status Check</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">View table counts & connection status</p>
            </a>
            <a href="/verify-rebuild.php" class="block p-3 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded transition">
                <strong>Database Rebuild Verification</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">Verify all tables are accessible</p>
            </a>
            <div class="block p-3 bg-purple-50 dark:bg-purple-900/20 rounded">
                <strong>📊 45 Tables Total</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">All InnoDB, UTF8MB4, fully operational</p>
            </div>
        </div>
    </div>

    <!-- Demo Data -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-green-500">
        <h2 class="text-2xl font-bold mb-4 text-green-600 dark:text-green-400">📚 Demo Data Setup</h2>
        <div class="space-y-3">
            <a href="/create-test-users.php" class="block p-3 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition">
                <strong>Create Test Users</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">Setup testuser & testauthor accounts</p>
            </a>
            <a href="/create-demo-stories.php" class="block p-3 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition">
                <strong>Generate Demo Stories</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create 6 sample books</p>
            </a>
            <a href="/create-demo-chapters.php" class="block p-3 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition">
                <strong>Generate Demo Chapters</strong>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create chapters for demo stories</p>
            </a>
        </div>
    </div>

    <!-- Credentials -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-red-500">
        <h2 class="text-2xl font-bold mb-4 text-red-600 dark:text-red-400">🔐 Test Credentials</h2>
        <div class="bg-gray-100 dark:bg-gray-700 rounded p-4 font-mono text-sm space-y-4">
            <div>
                <strong>Admin Account:</strong><br>
                URL: /admin/<br>
                Username: admin<br>
                Password: admin123
            </div>
            <div>
                <strong>User Account:</strong><br>
                URL: /pages/login.php<br>
                Username: testuser<br>
                Password: testuser123<br>
                Role: reader
            </div>
            <div>
                <strong>Author Account:</strong><br>
                URL: /pages/login.php<br>
                Username: testauthor<br>
                Password: author123<br>
                Role: author
            </div>
        </div>
    </div>

    <!-- Session Summary -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-yellow-500">
        <h2 class="text-2xl font-bold mb-4 text-yellow-600 dark:text-yellow-400">📋 Session Summary</h2>
        <ul class="space-y-2 text-sm">
            <li>✅ Fixed database corruption (InnoDB error 1932)</li>
            <li>✅ Restored all 45 tables to operational status</li>
            <li>✅ Implemented achievement system (30 achievements)</li>
            <li>✅ Created author announcements feature</li>
            <li>✅ Setup notification system framework</li>
            <li>✅ Generated demo data (6 stories, 12 chapters, 3 users)</li>
            <li>✅ Created comprehensive documentation</li>
        </ul>
    </div>

    <!-- API Reference -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 mb-6 border-l-4 border-indigo-500">
        <h2 class="text-2xl font-bold mb-4 text-indigo-600 dark:text-indigo-400">🔌 API Endpoints</h2>
        <div class="bg-gray-100 dark:bg-gray-700 rounded p-4 text-sm space-y-2">
            <div><strong>Achievements:</strong> /admin/ajax/manage_achievements.php</div>
            <div><strong>Notifications:</strong> /admin/ajax/notifications.php</div>
            <div><strong>40+ More endpoints</strong> available in /admin/ajax/ directory</div>
        </div>
    </div>

    <!-- Help & Support -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-l-4 border-pink-500">
        <h2 class="text-2xl font-bold mb-4 text-pink-600 dark:text-pink-400">❓ Help & Support</h2>
        <ul class="space-y-2">
            <li>📊 <a href="/db-status.php" class="text-blue-500 hover:underline">Database Status</a> - Check all tables</li>
            <li>🔧 <a href="/verify-rebuild.php" class="text-blue-500 hover:underline">Rebuild Verification</a> - Verify database</li>
            <li>📖 Check /PLATFORM_STATUS.md for detailed info</li>
            <li>📝 Check /QUICK_START.md for getting started guide</li>
        </ul>
    </div>

</div>
</body>
</html>

