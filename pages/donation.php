<?php
// donations.php
require_once dirname(__DIR__) . '/config/db.php';
$top = $pdo->query("
    SELECT u.username, SUM(d.amount) as total
    FROM donations d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 'completed'
    GROUP BY d.user_id
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();
?>
<?php
    $page_title = 'Top Donors - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1 max-w-4xl mx-auto px-4 py-12 w-full">
    <h1 class="text-3xl font-bold mb-4">Top Supporters</h1>
    <ol class="list-decimal list-inside space-y-2">
        <?php foreach ($top as $t): ?>
            <li><strong><?= htmlspecialchars($t['username']) ?></strong> - $<?= number_format($t['total'], 2) ?></li>
        <?php endforeach; ?>
    </ol>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
