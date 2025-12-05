<?php
$url = 'http://localhost/scrollnovels/pages/achievements.php';
$outDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'browser_checks_followed';
@mkdir($outDir, 0777, true);
$content = @file_get_contents($url);
$savePath = $outDir . DIRECTORY_SEPARATOR . 'pages_achievements.html';
file_put_contents($savePath, $content === false ? '' : $content);
echo "Saved achievements page to repairs/browser_checks_followed/pages_achievements.html\n";
return 0;
