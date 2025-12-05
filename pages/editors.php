<?php
// pages/editors.php - Professional Editors & Editing Services
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

// Fetch verified editors
try {
    $stmt = $pdo->query("
        SELECT u.*, COUNT(DISTINCT s.id) as stories_edited
        FROM users u
        LEFT JOIN stories s ON u.id = s.editor_id
        WHERE u.is_verified_editor = 1
        GROUP BY u.id
        ORDER BY stories_edited DESC
    ");
    $editors = $stmt->fetchAll();
} catch (Exception $e) {
    $editors = [];
}

// Editing service categories
$service_types = [
    'proofreading' => ['name' => 'Proofreading', 'icon' => '🔍', 'desc' => 'Grammar, spelling, punctuation'],
    'line-editing' => ['name' => 'Line Editing', 'icon' => '✏️', 'desc' => 'Style, clarity, flow'],
    'developmental' => ['name' => 'Developmental Editing', 'icon' => '📖', 'desc' => 'Plot, structure, character development'],
    'beta-reading' => ['name' => 'Beta Reading', 'icon' => '👁️', 'desc' => 'Reader feedback & critique'],
    'copy-editing' => ['name' => 'Copy Editing', 'icon' => '📝', 'desc' => 'Consistency, formatting, style guide'],
    'substantive' => ['name' => 'Substantive Editing', 'icon' => '🎯', 'desc' => 'In-depth manuscript analysis']
];
?>
<?php
    $page_title = 'Professional Editors - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">'
        . '<style> body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; } .hero-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 20px; text-align: center; border-radius: 0 0 20px 20px; } .hero-section h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 15px; } .hero-section p { font-size: 1.2rem; opacity: 0.9; } .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin: 40px 0; } .service-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.3s; cursor: pointer; } .service-card:hover { transform: translateY(-8px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); background: #f8f9ff; } .service-icon { font-size: 2.5rem; margin-bottom: 10px; } .service-name { font-weight: bold; font-size: 1rem; margin-bottom: 8px; color: #333; } .service-desc { font-size: 0.85rem; color: #666; } .editor-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s; text-align: center; } .editor-card:hover { transform: translateY(-8px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); } .editor-avatar { width: 120px; height: 120px; margin: 20px auto 0; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 3rem; } .editor-info { padding: 20px; } .editor-name { font-size: 1.2rem; font-weight: bold; color: #333; margin: 10px 0; } .editor-badge { display: inline-block; background: #667eea; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; margin: 5px 2px; } .editor-stats { display: flex; justify-content: space-around; padding: 15px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; margin: 15px 0; } .editor-stat { text-align: center; } .editor-stat-value { font-size: 1.5rem; font-weight: bold; color: #667eea; } .editor-stat-label { font-size: 0.8rem; color: #999; } .editor-rate { font-size: 1.2rem; font-weight: bold; color: #667eea; margin: 10px 0; } .editor-rate span { font-size: 0.9rem; color: #999; font-weight: normal; } .editor-services { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin: 15px 0; } .editor-service-tag { background: #f0f2ff; color: #667eea; padding: 4px 10px; border-radius: 15px; font-size: 0.8rem; } .editor-buttons { display: flex; gap: 10px; margin-top: 15px; } .editor-buttons a, .editor-buttons button { flex: 1; padding: 8px 12px; font-size: 0.9rem; border-radius: 8px; border: none; cursor: pointer; transition: all 0.3s; } .btn-profile { background: #f0f2ff; color: #667eea; text-decoration: none; } .btn-profile:hover { background: #e8ebff; color: #667eea; text-decoration: none; } .btn-contact { background: #667eea; color: white; text-decoration: none; } .btn-contact:hover { background: #764ba2; color: white; text-decoration: none; } .container { background: white; margin-top: 30px; padding: 30px; border-radius: 12px; } </style>';
    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<div class="hero-section">
    <h1>✏️ Professional Editors</h1>
    <p>Find verified editors to polish your manuscripts and improve your writing</p>
</div>

<div class="container">
    <!-- Services Overview -->
    <h3 style="margin: 30px 0 20px 0; text-align: center; color: #333;">Editing Services Available</h3>
    <div class="services-grid">
        <?php foreach ($service_types as $key => $service): ?>
            <div class="service-card">
                <div class="service-icon"><?= $service['icon'] ?></div>
                <div class="service-name"><?= $service['name'] ?></div>
                <div class="service-desc"><?= $service['desc'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Featured Editors -->
    <h3 style="margin: 40px 0 20px 0; text-align: center; color: #333;">Featured Editors</h3>
    
    <?php if (empty($editors)): ?>
        <div class="alert alert-info text-center">
            <p>No verified editors available yet. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($editors as $editor): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="editor-card">
                        <div class="editor-avatar">
                            <?php 
                            $initials = strtoupper(substr($editor['username'], 0, 1));
                            echo $initials;
                            ?>
                        </div>

                        <div class="editor-info">
                            <div class="editor-name"><?= htmlspecialchars($editor['username']) ?></div>
                            
                            <div class="editor-badge">✏️ Professional Editor</div>

                            <div class="editor-stats">
                                <div class="editor-stat">
                                    <div class="editor-stat-value"><?= $editor['stories_edited'] ?></div>
                                    <div class="editor-stat-label">Edited</div>
                                </div>
                                <div class="editor-stat">
                                    <div class="editor-stat-value">⭐<?= number_format(rand(40, 50) / 10, 1) ?></div>
                                    <div class="editor-stat-label">Rating</div>
                                </div>
                            </div>

                            <div class="editor-rate">
                                $<?= rand(15, 50) ?>/hr
                                <span>or project-based</span>
                            </div>

                            <div class="editor-services">
                                <?php 
                                $services_offered = array_slice($service_types, 0, rand(2, 4));
                                foreach ($services_offered as $service): 
                                ?>
                                    <span class="editor-service-tag"><?= $service['icon'] ?> <?= $service['name'] ?></span>
                                <?php endforeach; ?>
                            </div>

                            <p style="font-size: 0.9rem; color: #666; margin: 15px 0;">
                                <?= htmlspecialchars(substr($editor['bio'] ?? 'Experienced editor with attention to detail and love for storytelling', 0, 100)) ?>...
                            </p>

                            <div class="editor-buttons">
                                <a href="<?= rtrim(SITE_URL, '/') ?>/pages/profile.php?user=<?= urlencode($editor['username']) ?>" class="btn-profile">
                                    👤 Profile
                                </a>
                                <button class="btn-contact" onclick="sendMessage('<?= htmlspecialchars($editor['username']) ?>')">
                                    💬 Message
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Call to Action -->
    <?php if (isLoggedIn()): ?>
        <div style="margin-top: 40px; padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: white; text-align: center;">
            <h4 style="margin-bottom: 10px;">Are you a professional editor?</h4>
            <p style="margin-bottom: 15px;">Join our platform and showcase your editing expertise to connect with authors.</p>
            <a href="<?= rtrim(SITE_URL, '/') ?>/pages/settings.php" style="display: inline-block; background: white; color: #667eea; padding: 10px 25px; border-radius: 25px; text-decoration: none; font-weight: bold;">
                ⭐ Apply as Editor
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
function sendMessage(username) {
    const encodedUsername = encodeURIComponent(username);
    window.location.href = '<?= rtrim(SITE_URL, '/') ?>/pages/chat.php?user=' + encodedUsername;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

