<?php
// scripts/follow_and_save.php
$urls = [
    '/pages/story.php?slug=test-slug',
    '/pages/donate.php',
];
$base = 'http://localhost/scrollnovels';
$outDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'browser_checks_followed';
@mkdir($outDir, 0777, true);
foreach ($urls as $path) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $content = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $fname = preg_replace('/[^a-z0-9_.-]/i', '_', ltrim($path, '/'));
    $savePath = $outDir . DIRECTORY_SEPARATOR . $fname;
    file_put_contents($savePath, $content === false ? '' : $content);
    $summary[] = [
        'path' => $path,
        'url' => $url,
        'http_code' => $info['http_code'] ?? 0,
        'final_url' => $info['url'] ?? $url,
        'bytes' => $content === false ? 0 : strlen($content),
        'saved_to' => str_replace(realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR, '', $savePath)
    ];
}
file_put_contents($outDir . DIRECTORY_SEPARATOR . 'follow_report.json', json_encode($summary, JSON_PRETTY_PRINT));
echo "Follow-and-save completed. Reports in repairs/browser_checks_followed/\n";
return 0;
