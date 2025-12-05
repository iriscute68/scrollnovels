<?php
// pages/artist.php - Artist/Editor commission showcase
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';


// Fetch verified artists and editors
try {
    $stmt = $pdo->query("
        SELECT u.*, COUNT(DISTINCT s.id) as stories_contributed
        FROM users u
        LEFT JOIN stories s ON u.id = s.author_id
        WHERE u.is_verified_artist = 1 OR u.is_verified_editor = 1
        GROUP BY u.id
        ORDER BY stories_contributed DESC
    ");
    $professionals = $stmt->fetchAll();
} catch (Exception $e) {
    $professionals = [];
}
?>
<?php
    $page_title = 'Professional Artists & Editors - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .professional-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s; text-align: center; } .professional-card:hover { transform: translateY(-8px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); } .pro-avatar { width: 120px; height: 120px; margin: 20px auto 0; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; } .pro-info { padding: 20px; } .pro-name { font-size: 1.3rem; font-weight: 700; margin: 15px 0 5px; color: #333; } .pro-badges { display: flex; gap: 8px; justify-content: center; margin: 10px 0; flex-wrap: wrap; } .badge-artist { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; } .badge-editor { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; } .pro-stats { display: flex; justify-content: space-around; padding: 15px 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; margin: 15px 0; } .pro-stat { text-align: center; } .pro-stat-value { font-size: 1.5rem; font-weight: 700; color: #667eea; } .pro-stat-label { font-size: 0.85rem; color: #999; margin-top: 5px; } .pro-description { color: #666; font-size: 0.95rem; margin: 10px 0; } .pro-buttons { display: flex; gap: 10px; margin-top: 15px; } .pro-buttons button, .pro-buttons a { flex: 1; } </style>';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid p-5">
    <div class="row">
        <div class="col-md-3">
            <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <h1 class="mb-2"><i class="fas fa-palette"></i> Professional Artists & Editors</h1>
            <p class="text-muted mb-5">Connect with verified professionals for commissions, collaborations, and more.</p>

            <?php if (empty($professionals)): ?>
                <div class="alert alert-info">
                    <p class="mb-0">No verified artists or editors yet. Be the first to apply for professional status!</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($professionals as $pro): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="professional-card">
                                <div class="pro-avatar">
                                    <?= isset($pro['avatar']) ? '' : '👤' ?>
                                </div>

                                <div class="pro-info">
                                    <div class="pro-name"><?= htmlspecialchars($pro['username']) ?></div>

                                    <div class="pro-badges">
                                        <?php if ($pro['is_verified_artist']): ?>
                                            <span class="badge-artist">🎨 Artist</span>
                                        <?php endif; ?>
                                        <?php if ($pro['is_verified_editor']): ?>
                                            <span class="badge-editor">✏️ Editor</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pro-stats">
                                        <div class="pro-stat">
                                            <div class="pro-stat-value"><?= $pro['stories_contributed'] ?></div>
                                            <div class="pro-stat-label">Works</div>
                                        </div>
                                        <div class="pro-stat">
                                            <div class="pro-stat-value">⭐<?= number_format(rand(40, 50) / 10, 1) ?></div>
                                            <div class="pro-stat-label">Rating</div>
                                        </div>
                                    </div>

                                    <p class="pro-description">
                                        <?= htmlspecialchars(substr($pro['bio'] ?? 'Professional writer and contributor', 0, 100)) ?>...
                                    </p>

                                    <div class="pro-buttons">
                                        <a href="<?= rtrim(SITE_URL, '/') ?>/pages/profile.php?user=<?= urlencode($pro['username']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-user"></i> Profile
                                        </a>
                                        <button class="btn btn-sm btn-primary" onclick="sendMessage('<?= $pro['username'] ?>')">
                                            <i class="fas fa-envelope"></i> Message
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
                <div class="mt-5 p-4 bg-light rounded">
                    <h5>Want to become a verified artist or editor?</h5>
                    <p class="text-muted">Apply for professional status to showcase your work and connect with authors.</p>
                    <a href="<?= rtrim(SITE_URL, '/') ?>/pages/settings.php" class="btn btn-primary">
                        <i class="fas fa-star"></i> Apply Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
function sendMessage(username) {
    window.location.href = '<?= rtrim(SITE_URL, '/') ?>/pages/chat.php?user=' + encodeURIComponent(username);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
