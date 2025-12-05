<?php
// scripts/follow_donate_book.php
$url = 'http://localhost/scrollnovels/pages/donate.php?book_id=1';
$outDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'browser_checks_followed';
@mkdir($outDir, 0777, true);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$content = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
$savePath = $outDir . DIRECTORY_SEPARATOR . 'pages_donate_book1.html';
file_put_contents($savePath, $content === false ? '' : $content);
file_put_contents($outDir . DIRECTORY_SEPARATOR . 'follow_donate_book_report.json', json_encode($info, JSON_PRETTY_PRINT));
echo "Saved donate?book_id=1 to repairs/browser_checks_followed/pages_donate_book1.html (http_code: " . ($info['http_code'] ?? 0) . ")\n";
return 0;
