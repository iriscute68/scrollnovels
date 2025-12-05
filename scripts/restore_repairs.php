<?php
// scripts/restore_repairs.php
$root = realpath(__DIR__ . '/../');
$repairsDir = $root . DIRECTORY_SEPARATOR . 'repairs';
$reportJson = $repairsDir . DIRECTORY_SEPARATOR . 'report.json';
if (!file_exists($reportJson)) {
    echo "No repairs/report.json found. Run scripts/repair_encoding.php first.\n";
    exit(1);
}
$affected = json_decode(file_get_contents($reportJson), true);
$ts = date('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . "restore-$ts";
@mkdir($backupDir, 0777, true);
$log = [];
foreach ($affected as $a) {
    $rel = $a['file'];
    $origPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $repairPath = $repairsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    // ensure repair exists
    if (!file_exists($repairPath)) {
        $log[] = "SKIP $rel  -> repaired copy missing";
        continue;
    }

    // backup original if exists
    if (file_exists($origPath)) {
        @mkdir(dirname($backupPath), 0777, true);
        if (!copy($origPath, $backupPath)) {
            $log[] = "WARN could not backup original: $origPath";
            continue;
        }
        $log[] = "BACKED-UP $rel -> backups/restore-$ts/";
    } else {
        $log[] = "NO-ORIG $rel -> will create from repaired copy";
    }

    // ensure dest dir exists
    @mkdir(dirname($origPath), 0777, true);
    if (!copy($repairPath, $origPath)) {
        $log[] = "ERROR could not restore repaired copy for $rel";
        continue;
    }
    $log[] = "RESTORED $rel -> $origPath";
}
$logPath = $repairsDir . DIRECTORY_SEPARATOR . 'restore_log_' . $ts . '.txt';
file_put_contents($logPath, implode("\n", $log));
file_put_contents($repairsDir . DIRECTORY_SEPARATOR . 'last_restore.txt', "restore-$ts\n");
echo "Restore completed. Log: repairs/" . basename($logPath) . "\n";
return 0;
