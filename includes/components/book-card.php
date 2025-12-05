<?php
// includes/components/book-card.php - Reusable card component
function render_book_card($story, $show_actions = true, $grid_style = false) {
    if (!is_array($story)) return '';
    
    $id = $story['id'] ?? 0;
    $title = htmlspecialchars($story['title'] ?? 'Untitled');
    $author = htmlspecialchars($story['author_name'] ?? 'Unknown');
    $rating = $story['rating'] ?? '0.0';
    $reviews = (int)($story['reviews_count'] ?? 0);
    $views = format_number($story['views'] ?? 0);
    $cover = $story['cover_image'] ?? $story['cover'] ?? '';
    
    if ($grid_style) {
        // Grid/compact style (as requested in homepage)
        // Ensure ID is valid
        if (!$id || $id <= 0) {
            return '<!-- Book card error: missing or invalid ID for story: ' . htmlspecialchars($title) . ' -->';
        }
        // Prefer slug-based story route when available
        $slug = $story['slug'] ?? '';
        // Use direct numeric link to avoid slug mismatches that cause redirects to browse
        if (!function_exists('site_url')) {
            require_once __DIR__ . '/../functions.php';
        }
        $bookLink = site_url('/pages/book.php?id=' . $id);
        ?>
        <div class="group block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow hover:shadow-lg transition-all duration-200 hover:translate-y-[-2px]">
          <a href="<?= $bookLink ?>" data-story-href="<?= htmlspecialchars($bookLink) ?>" data-story-id="<?= htmlspecialchars($id) ?>" class="block">
            <div class="aspect-[3/4] overflow-hidden bg-emerald-50 dark:bg-emerald-900/30">
              <?php if ($cover): ?>
                <img src="<?= htmlspecialchars($cover) ?>" alt="<?= $title ?>" 
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-4xl">📚</div>
              <?php endif; ?>
            </div>
          </a>

          <div class="p-3 text-center">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 line-clamp-2 mb-1">
              <?= $title ?>
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">by <?= $author ?></p>

            <div class="text-xs text-gray-600 dark:text-gray-300 mb-2 space-y-1">
              <div>⭐ <?= htmlspecialchars($rating) ?> (<?= $reviews ?>)</div>
              <div>👁️ <?= $views ?> views</div>
            </div>

            <a href="<?= $bookLink ?>" class="inline-block w-full text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded transition font-medium text-center">
              Read
            </a>
          </div>
        </div>
        <?php
    } else {
        // Full card style (for other pages)
        $excerpt = substr($story['description'] ?? '', 0, 120) . '...';
        ?>
        <article class="bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-900 rounded-lg overflow-hidden hover:shadow-lg transition-shadow group">
            <div class="relative">
                <?php
                  // Use direct numeric link to avoid slug mismatches that cause redirects to browse
                  if (!function_exists('site_url')) {
                      require_once __DIR__ . '/../functions.php';
                  }
                  $fullLink = site_url('/pages/book.php?id=' . $id);
                ?>
                <?php if ($cover): ?>
                  <a href="<?= $fullLink ?>" data-story-href="<?= htmlspecialchars($fullLink) ?>" data-story-id="<?= htmlspecialchars($id) ?>">
                    <img src="<?= htmlspecialchars($cover) ?>" alt="<?= $title ?>" class="w-full h-36 md:h-44 lg:h-48 object-cover">
                  </a>
                <?php else: ?>
                  <a href="<?= $fullLink ?>">
                    <div class="w-full h-36 md:h-44 lg:h-48 bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-3xl">📚</div>
                  </a>
                <?php endif; ?>
                <?php if (!empty($story['isSponsored'])): ?>
                    <span class="ad-badge">AD</span>
                <?php endif; ?>
            </div>
            <div class="p-4">
                <h3 class="text-lg font-semibold mb-1">
                  <a href="<?= $fullLink ?>" class="text-emerald-700 dark:text-emerald-300 hover:text-emerald-400 group-hover:underline"><?= $title ?></a>
                </h3>
                <p class="text-sm text-emerald-600 dark:text-emerald-300 mb-2">by <?= $author ?></p>
                <p class="text-sm text-gray-700 dark:text-gray-200 mb-3 line-clamp-2"><?= htmlspecialchars($excerpt) ?></p>
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-emerald-600">⭐ <?= htmlspecialchars($rating) ?></span>
                        <span class="text-gray-400">(<?= $reviews ?>)</span>
                    </div>
                    <div class="text-gray-400">👁️ <?= $views ?></div>
                </div>
            <?php if ($show_actions): ?>
                <div class="mt-3 flex justify-end">
                    <a href="<?= $fullLink ?>" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium">Read</a>
                </div>
            <?php endif; ?>
            </div>
        </article>
        <?php
    }
}
?>