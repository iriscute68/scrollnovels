<?php
// scripts/check_achievements.php
require_once dirname(__DIR__) . '/config/db.php';
$out = [];
try {
    $cnt = $pdo->query('SELECT COUNT(*) as c FROM achievements')->fetch(PDO::FETCH_ASSOC)['c'];
    $out['achievements_count'] = (int)$cnt;
    $sample = $pdo->query('SELECT id, title, description FROM achievements ORDER BY id LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    $out['sample'] = $sample;
} catch (Exception $e) {
    $out['error'] = $e->getMessage();
}
file_put_contents(__DIR__ . '/../repairs/check_achievements.json', json_encode($out, JSON_PRETTY_PRINT));
echo "Checked achievements table. Report written to repairs/check_achievements.json\n";
return 0;
