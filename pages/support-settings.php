<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/auth/login.php'));
    exit;
}

$userId = $_SESSION['user_id'];
$currentPage = 'support-settings';

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ' . site_url('/pages/profile.php'));
        exit;
    }
    
    // Get author support links if they exist
    $supportLinks = ['kofi' => '', 'patreon' => '', 'paypal' => ''];
    
    try {
        $stmt = $pdo->prepare("
            SELECT link_type, link_url FROM author_links 
            WHERE author_id = ? AND is_verified = 1
            ORDER BY link_type
        ");
        $stmt->execute([$userId]);
        $links = $stmt->fetchAll();
        
        foreach ($links as $link) {
            $supportLinks[$link['link_type']] = $link['link_url'];
        }
    } catch (Exception $e) {}
    
} catch (Exception $e) {
    header('Location: ' . site_url('/'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Settings - <?php echo htmlspecialchars(APP_NAME ?? 'Scroll Novels'); ?></title>
    <link rel="stylesheet" href="<?= site_url('/assets/css/global.css') ?>">
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-12">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">💝 Support Settings</h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">Manage your Ko-fi, Patreon, and PayPal donation links</p>
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="hidden mb-6 p-4 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300 font-medium">
            ✓ Your support links have been updated successfully!
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="hidden mb-6 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 font-medium">
            ✗ <span id="errorText"></span>
        </div>

        <!-- Support Links Form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Add Your Support Links</h2>

            <form id="supportForm" class="space-y-6">
                <!-- Ko-fi -->
                <div class="p-6 border-2 border-red-200 dark:border-red-800 rounded-lg bg-red-50 dark:bg-red-900/10">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-2xl">❤️</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ko-fi Support</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Allow readers to support you with one-time donations and memberships on Ko-fi.
                    </p>
                    <input type="url" name="kofi_url" placeholder="https://ko-fi.com/your_username" 
                           value="<?= htmlspecialchars($supportLinks['kofi'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Example: https://ko-fi.com/myauthor</p>
                </div>

                <!-- Patreon -->
                <div class="p-6 border-2 border-red-700 dark:border-red-600 rounded-lg bg-red-900/5 dark:bg-red-900/20">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-2xl">🎉</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Patreon Support</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Set up monthly subscriptions for exclusive content and early access on Patreon.
                    </p>
                    <input type="url" name="patreon_url" placeholder="https://www.patreon.com/your_username"
                           value="<?= htmlspecialchars($supportLinks['patreon'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Example: https://www.patreon.com/myauthor</p>
                </div>

                <!-- PayPal -->
                <div class="p-6 border-2 border-blue-200 dark:border-blue-800 rounded-lg bg-blue-50 dark:bg-blue-900/10">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-2xl">💳</span>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">PayPal Support</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Accept donations directly via PayPal's donation system.
                    </p>
                    <input type="url" name="paypal_url" placeholder="https://www.paypal.com/paypalme/your_username"
                           value="<?= htmlspecialchars($supportLinks['paypal'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Example: https://www.paypal.com/paypalme/myauthor</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg transition">
                    💾 Save Support Links
                </button>
            </form>
        </div>

        <!-- Info Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="p-6 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-200 dark:border-blue-800">
                <h3 class="font-semibold text-blue-900 dark:text-blue-200 mb-2">💡 Pro Tip</h3>
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    You can add support links from multiple platforms! Readers can choose their preferred payment method.
                </p>
            </div>

            <div class="p-6 bg-purple-50 dark:bg-purple-900/10 rounded-lg border border-purple-200 dark:border-purple-800">
                <h3 class="font-semibold text-purple-900 dark:text-purple-200 mb-2">📊 Track Support</h3>
                <p class="text-sm text-purple-800 dark:text-purple-300">
                    Your support links will appear on your book pages so readers can easily find them!
                </p>
            </div>

            <div class="p-6 bg-orange-50 dark:bg-orange-900/10 rounded-lg border border-orange-200 dark:border-orange-800">
                <h3 class="font-semibold text-orange-900 dark:text-orange-200 mb-2">⚡ Get Started</h3>
                <p class="text-sm text-orange-800 dark:text-orange-300">
                    Set up your first support link above and start receiving donations from your readers!
                </p>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Preview</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-4">This is how your support links will appear on your book pages:</p>
            
            <div class="p-6 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
                <div id="previewContainer" class="space-y-3">
                    <!-- Preview will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function updatePreview() {
            const kofi = document.querySelector('input[name="kofi_url"]').value.trim();
            const patreon = document.querySelector('input[name="patreon_url"]').value.trim();
            const paypal = document.querySelector('input[name="paypal_url"]').value.trim();
            
            let html = '';
            
            if (kofi) {
                html += `<a href="${htmlEscape(kofi)}" target="_blank" class="block w-full px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg font-medium transition text-center">
                    ❤️ Support on Ko-fi
                </a>`;
            }
            
            if (patreon) {
                html += `<a href="${htmlEscape(patreon)}" target="_blank" class="block w-full px-4 py-3 bg-gradient-to-r from-red-800 to-red-900 hover:from-red-900 hover:to-black text-white rounded-lg font-medium transition text-center">
                    🎉 Join on Patreon
                </a>`;
            }
            
            if (paypal) {
                html += `<a href="${htmlEscape(paypal)}" target="_blank" class="block w-full px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg font-medium transition text-center">
                    💳 Donate via PayPal
                </a>`;
            }
            
            if (!html) {
                html = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">No support links added yet. Add one above to see a preview!</p>';
            }
            
            document.getElementById('previewContainer').innerHTML = html;
        }
        
        function htmlEscape(str) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(str).replace(/[&<>"']/g, m => map[m]);
        }
        
        // Handle form submission
        document.getElementById('supportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const kofi = document.querySelector('input[name="kofi_url"]').value.trim();
            const patreon = document.querySelector('input[name="patreon_url"]').value.trim();
            const paypal = document.querySelector('input[name="paypal_url"]').value.trim();
            
            // Reset messages
            document.getElementById('successMessage').classList.add('hidden');
            document.getElementById('errorMessage').classList.add('hidden');
            
            try {
                // Save each link
                const links = [
                    { type: 'kofi', url: kofi },
                    { type: 'patreon', url: patreon },
                    { type: 'paypal', url: paypal }
                ];
                
                for (const link of links) {
                    if (!link.url) continue; // Skip empty links
                    
                    const response = await fetch('<?= site_url('/api/supporters/add-support-link.php') ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            link_type: link.type,
                            link_url: link.url
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || `Failed to save ${link.type} link`);
                    }
                }
                
                // Show success message
                document.getElementById('successMessage').classList.remove('hidden');
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Clear message after 3 seconds
                setTimeout(() => {
                    document.getElementById('successMessage').classList.add('hidden');
                }, 3000);
                
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('errorText').textContent = error.message || 'An error occurred. Please try again.';
                document.getElementById('errorMessage').classList.remove('hidden');
            }
        });
        
        // Update preview as user types
        document.querySelectorAll('input[name*="_url"]').forEach(input => {
            input.addEventListener('input', updatePreview);
        });
        
        // Initial preview
        updatePreview();
    </script>
</body>
</html>
