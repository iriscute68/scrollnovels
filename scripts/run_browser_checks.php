<?php
// scripts/run_browser_checks.php
$root = realpath(__DIR__ . '/../');
$checks = [
    '/pages/browse.php?category=fanfic',
    '/pages/story.php?slug=test-slug',
    '/pages/community.php',
    '/pages/donate.php',
    '/assets/css/global.css'
];
$base = "http://localhost/scrollnovels";
$outDir = $root . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'browser_checks';
@mkdir($outDir, 0777, true);
$report = [];
foreach ($checks as $path) {
    $url = $base . $path;
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $content = @file_get_contents($url, false, $context);
    $headers = isset($http_response_header) ? $http_response_header : [];
    $status = 'unknown';
    foreach ($headers as $h) {
        if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) { $status = intval($m[1]); break; }
    }
    $hasNull = $content !== false && strpos($content, "\x00") !== false;
    $hasReplacement = $content !== false && (strpos($content, "\xEF\xBF\xBD") !== false || mb_strpos($content, "�") !== false);
    $fname = preg_replace('/[^a-z0-9_.-]/i', '_', ltrim($path, '/'));
    $savePath = $outDir . DIRECTORY_SEPARATOR . $fname;
    file_put_contents($savePath, $content === false ? "" : $content);
    $report[$path] = [
        'url' => $url,
        'status' => $status,
        'bytes' => $content === false ? 0 : strlen($content),
        'has_null' => $hasNull,
        'has_replacement_char' => $hasReplacement,
        'saved_to' => str_replace($root . DIRECTORY_SEPARATOR, '', $savePath)
    ];
}
file_put_contents($outDir . DIRECTORY_SEPARATOR . 'report.json', json_encode($report, JSON_PRETTY_PRINT));
file_put_contents($outDir . DIRECTORY_SEPARATOR . 'report.txt', print_r($report, true));
echo "Browser checks completed. Reports in repairs/browser_checks/\n";
return 0;
