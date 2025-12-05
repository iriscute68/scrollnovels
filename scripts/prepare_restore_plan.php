<?php
// scripts/prepare_restore_plan.php
$root = realpath(__DIR__ . '/../');
$repairsDir = $root . DIRECTORY_SEPARATOR . 'repairs';
$backupRoot = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . '20251117_080809';
$reportJson = $repairsDir . DIRECTORY_SEPARATOR . 'report.json';
$planPath = $repairsDir . DIRECTORY_SEPARATOR . 'restore_plan.txt';
if (!file_exists($reportJson)) {
    echo "No repairs/report.json found. Run the repair scan first.\n";
    exit(1);
}
$affected = json_decode(file_get_contents($reportJson), true);
$lines = [];
$lines[] = "Restore Plan generated: " . date('c');
$lines[] = "";
foreach ($affected as $a) {
    $rel = $a['file'];
    $repairPath = $repairsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $backupPath = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $origPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    $lines[] = "File: $rel";
    $lines[] = "  - original: $origPath";
    $lines[] = "  - repaired copy: " . (file_exists($repairPath) ? $repairPath . ' ('.filesize($repairPath).' bytes)' : 'MISSING');
    $lines[] = "  - backup copy: " . (file_exists($backupPath) ? $backupPath . ' ('.filesize($backupPath).' bytes)' : 'NONE');

    if (file_exists($backupPath) && filesize($backupPath) > 0) {
        $lines[] = "  => Recommendation: review backup first (backups/20251117_080809). Consider restoring backup to a new backup folder before overwriting live file.";
    } elseif (file_exists($repairPath) && filesize($repairPath) > 0) {
        $lines[] = "  => Recommendation: no intact backup found; review repaired copy in repairs/ and verify contents before restoring.";
    } else {
        $lines[] = "  => Recommendation: no usable backup or repair copy found; manual recovery required.";
    }
    $lines[] = "";
}
file_put_contents($planPath, implode("\n", $lines));
echo "Restore plan written to repairs/restore_plan.txt\n";
return 0;
