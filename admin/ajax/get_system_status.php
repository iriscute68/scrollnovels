<?php
// /admin/ajax/get_system_status.php - Server health
require_once __DIR__ . '/../../includes/functions.php';

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['error' => 'Forbidden'])); }

header('Content-Type: application/json');

$cpu = round((function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? 0) : 0) * 10, 1);

$mem_total = null;
$mem_used = null;
$mem_pct = 0;

if (stristr(PHP_OS, 'linux')) {
  $data = @file_get_contents('/proc/meminfo');
  if ($data) {
    preg_match('/MemTotal:\s+(\d+)/', $data, $m1);
    preg_match('/MemAvailable:\s+(\d+)/', $data, $m2);
    if (isset($m1[1]) && isset($m2[1])) {
      $mem_total = round($m1[1] / 1024);
      $mem_available = round($m2[1] / 1024);
      $mem_used = $mem_total - $mem_available;
      $mem_pct = round($mem_used / max(1, $mem_total) * 100, 1);
    }
  }
}

$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');

$disk = [
  'total_gb' => $disk_total ? round($disk_total / 1024 / 1024 / 1024, 1) : 0,
  'used_gb' => $disk_total ? round(($disk_total - ($disk_free ?? 0)) / 1024 / 1024 / 1024, 1) : 0,
  'percent' => $disk_total ? round(($disk_total - ($disk_free ?? 0)) / $disk_total * 100, 1) : 0
];

echo json_encode([
  'cpu' => $cpu,
  'ram' => ['total_mb' => $mem_total, 'used_mb' => $mem_used, 'percent' => $mem_pct],
  'disk' => $disk
]);
