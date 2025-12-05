<?php
// cron/pay-milestones.php
require_once '../config/db.php';

$contracts = $pdo->query("SELECT * FROM contracts WHERE status = 'signed'")->fetchAll();
foreach ($contracts as $c) {
    $author_id = $c['author_id'];
    $milestones = json_decode($c['milestones'], true);
    $chapters = $pdo->query("SELECT COUNT(*) FROM chapters WHERE story_id IN (SELECT id FROM stories WHERE author_id = $author_id)")->fetchColumn();

    foreach ($milestones as $m) {
        if ($chapters >= $m && !in_array($m, json_decode($c['paid_milestones'] ?? '[]', true))) {
            $amount = 50; // $50 per milestone
            $pdo->prepare("INSERT INTO payments (contract_id, amount, milestone) VALUES (?, ?, ?)")
                ->execute([$c['id'], $amount, $m]);
            $pdo->prepare("UPDATE contracts SET paid_milestones = JSON_MERGE_PATCH(paid_milestones, ?) WHERE id = ?")
                ->execute("[{$m}]", $c['id']);

            // Notify
            require_once '../includes/functions.php';
            notify($pdo, $author_id, null, 'payment', "You earned $$amount for reaching $m chapters!", "/contracts.php");
        }
    }
}
?>
