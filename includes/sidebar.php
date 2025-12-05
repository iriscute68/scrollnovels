<?php
// Minimal sidebar include — kept intentionally simple to avoid accidental large logic.
// If the main header already renders the sidebar, this file is a harmless duplicate.
if (!function_exists('site_url')) {
	function site_url($path = '') { return '/scrollnovels' . ($path ? '/' . ltrim($path, '/') : ''); }
}
?>
<aside class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
	<h4 class="font-bold text-emerald-600 mb-2">Quick Links</h4>
	<nav class="flex flex-col gap-2 text-sm">
		<!-- Removed Browse and Fanfic per request -->
		<a href="<?= site_url('/pages/website-rules.php') ?>">Rules</a>
		<a href="<?= site_url('/pages/contact-support.php') ?>">Support</a>
	</nav>
</aside>
