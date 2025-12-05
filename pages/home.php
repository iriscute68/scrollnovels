<?php
// index.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

// Stats
$stats = [
    'users_online' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn(),
    'total_stories' => $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'published'")->fetchColumn(),
    'total_views' => $pdo->query("SELECT SUM(views) FROM stories")->fetchColumn(),
];

// Featured (top 5 by views)
try {
    $featured = $pdo->query("
        SELECT s.id, s.title, s.slug, s.cover, s.description, u.username
        FROM stories s
        JOIN users u ON s.author_id = u.id
        WHERE s.status = 'published'
        ORDER BY s.views DESC, s.created_at DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    $featured = [];
}

// Trending (recently viewed / recently published)
try {
    $trending = $pdo->query("
        SELECT s.id, s.title, s.slug, s.cover, u.username
        FROM stories s
        JOIN users u ON s.author_id = u.id
        WHERE s.status = 'published'
        ORDER BY s.updated_at DESC, s.views DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $trending = [];
}

// New releases
try {
    $new = $pdo->query("
        SELECT s.id, s.title, s.slug, s.cover, u.username, s.genre
        FROM stories s
        JOIN users u ON s.author_id = u.id
        WHERE s.status = 'published'
        ORDER BY s.created_at DESC
        LIMIT 12
    ")->fetchAll();
} catch (Exception $e) {
    $new = [];
}

// Genres
try {
    $genres = $pdo->query("SELECT id, name FROM categories LIMIT 12")->fetchAll();
} catch (Exception $e) {
    $genres = [];
    // Fallback genres if categories table doesn't exist
    if (empty($genres)) {
        $genres = [
            ['id' => 1, 'name' => 'Fantasy'],
            ['id' => 2, 'name' => 'Romance'],
            ['id' => 3, 'name' => 'Sci-Fi'],
            ['id' => 4, 'name' => 'Mystery'],
        ];
    }
}

// Active announcement (latest)
$announcement = $pdo->query("SELECT id, title, slug, content, link FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1")->fetch();
?>
<?php
    $page_title = 'Scroll Novels - Read Web Novels & Webtoons';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">'
        . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">'
        . '<style> .story-card { transition: transform 0.3s; } .story-card:hover { transform: translateY(-5px); } .genre-card { height: 120px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; text-shadow: 0 0 5px rgba(0,0,0,0.5); } .stats-bar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; } .swiper-button-next, .swiper-button-prev { color: white; } </style>';
    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Carousel -->
<div class="swiper featured-swiper">
    <div class="swiper-wrapper">
        <?php foreach ($featured as $f): ?>
            <div class="swiper-slide position-relative">
                <img src="<?= htmlspecialchars($f['cover'] ?? '/assets/default-cover.jpg') ?>" class="w-100" style="height: 500px; object-fit: cover;" loading="lazy">
                <div class="position-absolute bottom-0 start-0 p-4 text-white" style="background: rgba(0,0,0,0.6); width: 100%;">
                    <h2><?= htmlspecialchars($f['title']) ?></h2>
                    <p class="mb-1">by <strong><?= htmlspecialchars($f['username']) ?></strong></p>
                    <p><?= substr(htmlspecialchars($f['description']), 0, 150) ?>...</p>
                    <a href="<?= rtrim(SITE_URL, '/') ?>/pages/story.php?slug=<?= urlencode($f['slug']) ?>" class="btn btn-primary">Read Now</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-pagination"></div>
</div>

<!-- Sponsored / Featured Ad (from ads table) -->
<?php
    // showAds helper expects placement and optional story_id
    if (file_exists(__DIR__ . '/../includes/components/ad-display.php')) {
        include __DIR__ . '/../includes/components/ad-display.php';
        // show a 'featured' placement ad (admin creates with placement 'featured')
        echo '<div class="container my-4">';
        showAds($pdo, 'featured');
        echo '</div>';
    }
?>

<!-- Stats Bar -->
<div class="stats-bar py-3">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3">
                <h4><?= number_format($stats['users_online']) ?></h4>
                <small>Online Now</small>
            </div>
            <div class="col-md-3">
                <h4><?= number_format($stats['total_stories']) ?></h4>
                <small>Stories</small>
            </div>
            <div class="col-md-3">
                <h4><?= number_format($stats['total_views']) ?></h4>
                <small>Total Views</small>
            </div>
            <div class="col-md-3">
                <h4><i class="fas fa-heart text-danger"></i> <?= $pdo->query("SELECT COUNT(*) FROM interactions WHERE type='like'")->fetchColumn() ?></h4>
                <small>Likes</small>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <?php if (!empty($announcement)): ?>
        <div class="alert alert-info">
            <h5><?= htmlspecialchars($announcement['title']) ?></h5>
            <p><?= nl2br(htmlspecialchars(substr($announcement['content'],0,300))) ?></p>
            <?php if (!empty($announcement['link'])): ?>
                <a href="<?= htmlspecialchars($announcement['link']) ?>" class="btn btn-sm btn-primary">Learn more</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Tabs: Trending / New / Hot -->
    <ul class="nav nav-tabs mb-4" id="storyTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#trending">Trending</a></li>
