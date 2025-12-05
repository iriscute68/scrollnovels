<?php
// scripts/save_thread.php
$id = $argv[1] ?? 1;
$url = "http://localhost/scrollnovels/pages/thread.php?id={$id}";
$content = @file_get_contents($url);
$outDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'browser_checks_followed';
@mkdir($outDir, 0777, true);
$fname = "thread_{$id}.html";
file_put_contents($outDir . DIRECTORY_SEPARATOR . $fname, $content === false ? '' : $content);
echo "Saved thread page to repairs/browser_checks_followed/{$fname}\n";
return 0;
