<?php
// Simple index page fallback — includes header and footer
if (file_exists(__DIR__ . '/../includes/header.php')) include __DIR__ . '/../includes/header.php';

?>
<div class="max-w-5xl mx-auto px-4 py-12">
	<section class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
		<h2 class="text-2xl font-bold mb-2">Welcome to Scroll Novels</h2>
		<p class="text-sm text-emerald-700 dark:text-emerald-300 mb-4">Browse original stories, fanfics, and webtoons. Use the navigation to find Fanfic category.</p>
		<a href="<?= site_url('/pages/browse.php?category=fanfic') ?>" class="inline-block px-4 py-2 bg-emerald-600 text-white rounded-md">Browse Fanfic</a>
	</section>

	<section class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
		<div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">Latest Stories</div>
		<div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">Top Authors</div>
		<div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
			<h3 class="text-lg font-bold mb-2">Announcements & Blog</h3>
			<?php
			// Merge blog posts and announcements for homepage
			$blog_announcements = [];
			try {
				$stmt = $pdo->query("SELECT id, title, slug, excerpt, cover_image, category, views, created_at, 'blog' as type FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 5");
				$blog_announcements = $stmt->fetchAll();
				$stmt2 = $pdo->query("SELECT id, title, slug, content as excerpt, NULL as cover_image, 'Announcement' as category, views, created_at, 'announcement' as type FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
				$blog_announcements = array_merge($blog_announcements, $stmt2->fetchAll());
				usort($blog_announcements, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
			} catch (Exception $e) {}
			?>
			<?php foreach ($blog_announcements as $item): ?>
				<div class="mb-4 border-b pb-2">
					<a href="<?= $item['type'] === 'blog' ? site_url('/pages/blog_post.php?slug=' . urlencode($item['slug'])) : '#' ?>" class="font-semibold text-emerald-700 dark:text-emerald-300">
						<?= htmlspecialchars($item['title']) ?>
					</a>
					<span class="text-xs text-gray-500 ml-2">(<?= htmlspecialchars($item['category']) ?>)</span>
					<p class="text-xs text-gray-600 dark:text-gray-400 mt-1"><?= htmlspecialchars(substr(strip_tags($item['excerpt']),0,120)) ?>...</p>
					<span class="text-xs text-gray-400"><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
</div>

<?php if (file_exists(__DIR__ . '/../includes/footer.php')) include __DIR__ . '/../includes/footer.php'; ?>


