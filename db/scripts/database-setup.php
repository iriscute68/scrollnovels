<?php
// ═══════════════════════════════════════════════════════════════════════════════
// ScrollNovels - Database Import & Setup Guide
// ═══════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html>
<head>
    <title>ScrollNovels - Database Setup Guide</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
<div class="max-w-4xl mx-auto">

    <h1 class="text-4xl font-bold mb-8 text-emerald-400">🗄️ ScrollNovels Database Setup</h1>

    <!-- Quick Start -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border-l-4 border-emerald-500">
        <h2 class="text-2xl font-bold mb-4">⚡ Quick Start (3 Steps)</h2>
        <div class="space-y-4">
            <div class="flex gap-4">
                <div class="bg-emerald-500 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0 font-bold">1</div>
                <div>
                    <h3 class="font-bold mb-2">Drop Old Database</h3>
                    <code class="bg-gray-700 p-2 rounded block">mysql -u root -e "DROP DATABASE IF EXISTS scroll_novels;"</code>
                </div>
            </div>
            <div class="flex gap-4">
                <div class="bg-emerald-500 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0 font-bold">2</div>
                <div>
                    <h3 class="font-bold mb-2">Create Fresh Database</h3>
                    <code class="bg-gray-700 p-2 rounded block">mysql -u root -e "CREATE DATABASE scroll_novels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"</code>
                </div>
            </div>
            <div class="flex gap-4">
                <div class="bg-emerald-500 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0 font-bold">3</div>
                <div>
                    <h3 class="font-bold mb-2">Import Schema & Data</h3>
                    <code class="bg-gray-700 p-2 rounded block">mysql -u root scroll_novels &lt; all.sql</code>
                </div>
            </div>
        </div>
    </div>

    <!-- What's in all.sql -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border-l-4 border-blue-500">
        <h2 class="text-2xl font-bold mb-4">📦 What's in all.sql File</h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-700 p-4 rounded">
                <h4 class="font-bold text-emerald-400 mb-2">✓ 20+ Tables</h4>
                <ul class="text-sm space-y-1">
                    <li>• users (platform users)</li>
                    <li>• admins (admin accounts)</li>
                    <li>• stories (novels/books)</li>
                    <li>• chapters (story chapters)</li>
                    <li>• blogs (blog posts)</li>
                    <li>• competitions (contests)</li>
                    <li>• And 14 more...</li>
                </ul>
            </div>
            <div class="bg-gray-700 p-4 rounded">
                <h4 class="font-bold text-emerald-400 mb-2">✓ Test Data</h4>
                <ul class="text-sm space-y-1">
                    <li>• 2 admin accounts</li>
                    <li>• 4 user accounts</li>
                    <li>• All passwords: admin123</li>
                    <li>• Properly hashed (bcrypt)</li>
                    <li>• Ready to use</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Using phpMyAdmin -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border-l-4 border-purple-500">
        <h2 class="text-2xl font-bold mb-4">🌐 Using phpMyAdmin (GUI Method)</h2>
        <ol class="space-y-3 text-sm">
            <li class="flex gap-3">
                <span class="font-bold text-purple-400">1.</span>
                <span>Go to: <code class="bg-gray-700 px-2 py-1 rounded">http://localhost/phpmyadmin</code></span>
            </li>
            <li class="flex gap-3">
                <span class="font-bold text-purple-400">2.</span>
                <span>Click "Databases" tab → Create database "scroll_novels" with UTF8MB4 charset</span>
            </li>
            <li class="flex gap-3">
                <span class="font-bold text-purple-400">3.</span>
                <span>Select the new database</span>
            </li>
            <li class="flex gap-3">
                <span class="font-bold text-purple-400">4.</span>
                <span>Click "Import" tab</span>
            </li>
            <li class="flex gap-3">
                <span class="font-bold text-purple-400">5.</span>
                <span>Choose file: <code class="bg-gray-700 px-2 py-1 rounded">all.sql</code></span>
            </li>
            <li class="flex gap-3">
                <span class="font-bold text-purple-400">6.</span>
                <span>Click "Go"</span>
            </li>
        </ol>
    </div>

    <!-- Using Command Line -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border-l-4 border-green-500">
        <h2 class="text-2xl font-bold mb-4">💻 Using Command Line (Terminal Method)</h2>
        <div class="bg-gray-700 p-4 rounded mb-4 font-mono text-sm">
            <div># Navigate to MySQL bin directory</div>
            <div>cd C:\xampp\mysql\bin</div>
            <div class="mt-2"># Create database</div>
            <div>.\mysql -u root -e "CREATE DATABASE scroll_novels CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"</div>
            <div class="mt-2"># Import all.sql file</div>
            <div>Get-Content "C:\xampp\htdocs\scrollnovels\all.sql" | .\mysql -u root scroll_novels</div>
        </div>
    </div>

    <!-- Test Credentials -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border-l-4 border-yellow-500">
        <h2 class="text-2xl font-bold mb-4">🔑 Test Credentials After Import</h2>
        
        <h3 class="font-bold text-yellow-400 mb-3">Admin Login</h3>
        <div class="bg-gray-700 p-3 rounded mb-4 text-sm">
            <div><strong>URL:</strong> http://localhost/admin/</div>
            <div><strong>Username:</strong> admin</div>
            <div><strong>Password:</strong> admin123</div>
        </div>

        <h3 class="font-bold text-yellow-400 mb-3">User Login</h3>
        <div class="bg-gray-700 p-3 rounded text-sm">
            <div><strong>URL:</strong> http://localhost/pages/login.php</div>
            <div><strong>Options:</strong></div>
            <div class="ml-4 mt-2">
                <div>• testuser / admin123 (reader)</div>
                <div>• testauthor / admin123 (author)</div>
                <div>• testeditor / admin123 (editor)</div>
                <div>• testwriter / admin123 (writer)</div>
            </div>
        </div>
    </div>

    <!-- Troubleshooting -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8 border-l-4 border-red-500">
        <h2 class="text-2xl font-bold mb-4">🔧 Troubleshooting</h2>
        <div class="space-y-4 text-sm">
            <div>
                <h4 class="font-bold text-red-400 mb-2">❌ "Access Denied"</h4>
                <p>Solution: Make sure MySQL is running. Check Windows Services or use XAMPP Control Panel.</p>
            </div>
            <div>
                <h4 class="font-bold text-red-400 mb-2">❌ "Table already exists"</h4>
                <p>Solution: Drop the database first: <code class="bg-gray-700 px-2 py-1 rounded">DROP DATABASE scroll_novels;</code></p>
            </div>
            <div>
                <h4 class="font-bold text-red-400 mb-2">❌ Login not working</h4>
                <p>Solution: Verify all.sql imported successfully. Check: <code class="bg-gray-700 px-2 py-1 rounded">http://localhost/db-status.php</code></p>
            </div>
            <div>
                <h4 class="font-bold text-red-400 mb-2">❌ "Database doesn't exist"</h4>
                <p>Solution: Create it with step 2 above, then import with step 3.</p>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="bg-emerald-900 rounded-lg p-6 border-l-4 border-emerald-400">
        <h2 class="text-2xl font-bold mb-4">✅ After Successful Import</h2>
        <ul class="space-y-2">
            <li>✓ 20+ database tables created</li>
            <li>✓ 2 admin accounts ready</li>
            <li>✓ 4 user test accounts ready</li>
            <li>✓ All passwords hashed with bcrypt</li>
            <li>✓ Database connected to scroll_novels</li>
            <li>✓ Ready to login and use platform</li>
        </ul>
    </div>

</div>
</body>
</html>

