<?php
// scripts/repair_encoding.php
// Non-destructive scan for NUL bytes / invalid UTF-8 and write cleaned copies to repairs/
set_time_limit(0);
$root = realpath(__DIR__ . '/../');
if (!$root) {
    echo "Unable to determine project root\n";
    exit(1);
}
$excludePatterns = [
    '#(^|/)\.git(/|$)#',
    '#(^|/)backups(/|$)#',
    '#(^|/)repairs(/|$)#',
    '#(^|/)vendor(/|$)#',
    '#(^|/)node_modules(/|$)#',
];
$extensions = ['php','phtml','html','htm','css','js','txt','md','sql','json','xml','ini','sh','ps1','yml','yaml'];
$affected = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getRealPath();
    $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    $skip = false;
    foreach ($excludePatterns as $pat) {
        if (preg_match($pat, $rel)) { $skip = true; break; }
    }
    if ($skip) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) continue;
    $content = @file_get_contents($path);
    if ($content === false) continue;
    $hasNull = strpos($content, "\x00") !== false;
    $validUtf8 = function_exists('mb_check_encoding') ? mb_check_encoding($content, 'UTF-8') : (preg_match('//u', $content) === 1);
    if (!$hasNull && $validUtf8) continue; // nothing to do
    $fixed = $content;
    if ($hasNull) {
        $fixed = str_replace("\x00", '', $fixed);
    }
    // Ensure UTF-8: try iconv to strip invalid sequences
    $stillValid = function_exists('mb_check_encoding') ? mb_check_encoding($fixed, 'UTF-8') : (preg_match('//u', $fixed) === 1);
    if (!$stillValid) {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $fixed);
            if ($converted !== false) $fixed = $converted;
        }
        // final fallback: remove non-printable bytes
        $fixed = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]+/', '', $fixed);
    }
    $outPath = $root . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    @mkdir(dirname($outPath), 0777, true);
    file_put_contents($outPath, $fixed);
    $affected[] = [
        'file' => $rel,
        'has_null' => $hasNull,
        'was_valid_utf8' => $validUtf8,
        'orig_size' => filesize($path),
        'new_size' => strlen($fixed),
        'repair_path' => str_replace('\\', '/', substr($outPath, strlen($root)+1))
    ];
}
@mkdir($root . DIRECTORY_SEPARATOR . 'repairs', 0777, true);
file_put_contents($root . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'report.json', json_encode($affected, JSON_PRETTY_PRINT));
$txt = "Repair scan report\n\n";
foreach ($affected as $a) {
    $txt .= sprintf("%s  | null=%s  | utf8_before=%s  | orig=%d  | new=%d  | repaired=%s\n",
        $a['file'], $a['has_null'] ? 'YES' : 'NO', $a['was_valid_utf8'] ? 'YES' : 'NO', $a['orig_size'], $a['new_size'], $a['repair_path']);
}
if (empty($affected)) $txt .= "No affected files found.\n";
file_put_contents($root . DIRECTORY_SEPARATOR . 'repairs' . DIRECTORY_SEPARATOR . 'report.txt', $txt);
echo "Scan complete. Affected files: " . count($affected) . "\n";
echo "Report written to repairs/report.txt and repairs/report.json\n";

return 0;
