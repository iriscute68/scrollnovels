<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/../includes/header.php';

function get_content($pdo, $k) {
    $s = $pdo->prepare('SELECT v FROM site_contents WHERE k = ? LIMIT 1');
    $s->execute([$k]);
    return $s->fetchColumn();
}

$content = get_content($pdo, 'content_policy') ?: '<h2>Content Policy</h2><p>No content policy set yet.</p>';
?>
<main class="max-w-4xl mx-auto p-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
        <?= $content ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
