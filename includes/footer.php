<?php
// Footer fallbacks: ensure helpers exist so footer doesn't fatal when included standalone
if (!function_exists('site_url')) {
    function site_url($path = '') {
        if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
        if (empty($path)) return rtrim(SITE_URL, '/');
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
}
?>
<!-- Footer Component - Minimal (no closing tags so pages can include scripts after) -->
<footer class="bg-gray-800 dark:bg-gray-900 text-gray-300 py-6 border-t border-gray-700 dark:border-gray-800 mt-auto">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Footer Links & Donate Button -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <!-- Support Button -->
            <div class="w-full md:w-auto flex justify-center md:justify-start">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button onclick="openDonatePicker()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-full transition">
                        💝 Support the Website
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Footer Text & Links -->
            <div class="text-center md:text-right text-xs md:text-sm space-y-2 md:space-y-0">
                <div class="flex flex-wrap justify-center md:justify-end gap-2 md:gap-3 mb-2 md:mb-0">
                    <a href="https://discord.gg/nkN7U7wPbx" target="_blank" rel="noopener" class="text-gray-300 hover:text-white transition" title="Join our Discord">
                        💬 Discord
                    </a>
                    <span class="text-gray-600 hidden md:inline">·</span>
                    <a href="<?= site_url('/pages/privacy.php') ?>" class="text-gray-300 hover:text-white transition">Privacy</a>
                    <span class="text-gray-600 hidden md:inline">·</span>
                    <a href="<?= site_url('/pages/terms.php') ?>" class="text-gray-300 hover:text-white transition">Terms</a>
                    <span class="text-gray-600 hidden md:inline">·</span>
                    <a href="<?= site_url('/pages/content-policy.php') ?>" class="text-gray-300 hover:text-white transition">Content Policy</a>
                    <span class="text-gray-600 hidden md:inline">·</span>
                    <a href="<?= site_url('/pages/dmca.php') ?>" class="text-gray-300 hover:text-white transition">DMCA</a>
                    <span class="text-gray-600 hidden md:inline">·</span>
                    <a href="<?= site_url('/pages/contact.php') ?>" class="text-gray-300 hover:text-white transition">Contact</a>
                </div>
                <div class="text-gray-400">© 2025 Scroll Novels. All rights reserved.</div>
            </div>
        </div>
    </div>
</footer>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
// Cute donate button functionality
function openDonatePicker() {
    document.getElementById('donate-picker-modal').classList.remove('hidden');
}

function closeDonatePicker() {
    document.getElementById('donate-picker-modal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('donate-picker-modal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDonatePicker();
});
</script>

<script>
// Fix: if a story link redirects to /pages/browse.php (slug mismatch), fallback to book.php?id=ID
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('a[data-story-href]').forEach(function(anchor){
        anchor.addEventListener('click', async function(e){
            // allow new-tab / modifier clicks
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            // Only intercept left click
            if (e.button && e.button !== 0) return;
            const href = anchor.getAttribute('data-story-href');
            const id = anchor.getAttribute('data-story-id');
            if (!href) return;
            e.preventDefault();
            try {
                const res = await fetch(href, { method: 'GET', credentials: 'same-origin', redirect: 'follow' });
                // Browser follows redirects; res.url is the final URL
                const final = res.url || href;
                if (final.indexOf('/pages/browse.php') !== -1 || final.indexOf('browse.php') !== -1) {
                    if (id) {
                        window.location.href = (window.SITE_URL ?? '') + '/pages/book.php?id=' + id;
                        return;
                    }
                }
                // otherwise navigate to final link
                window.location.href = final;
            } catch (err) {
                // On fetch failure, fall back to id (numeric) or direct link
                if (id) {
                    window.location.href = (window.SITE_URL ?? '') + '/pages/book.php?id=' + id;
                } else {
                    window.location.href = href;
                }
            }
        });
    });
});
</script>

<!-- Donate Picker Modal -->
<div id="donate-picker-modal" class="hidden fixed inset-0 bg-black bg-opacity-30 z-40 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 max-w-sm w-full text-center">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">💝 Support the Website</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4 text-sm">Choose how you'd like to support:</p>
        <div class="space-y-2">
            <a href="https://www.patreon.com/Zakielvtuber" target="_blank" rel="noopener" class="block px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-medium transition">
                🌟 Support the Website
            </a>
            <button onclick="closeDonatePicker()" class="w-full px-4 py-2 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- NOTE: This file intentionally does NOT close </body> or </html> so pages may add page-specific scripts before closing. -->

