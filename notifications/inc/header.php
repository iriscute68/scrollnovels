<?php
// Minimal notifications header wrapper — include global header when available
if (file_exists(__DIR__ . '/../../includes/header.php')) {
    require_once __DIR__ . '/../../includes/header.php';
} elseif (file_exists(__DIR__ . '/../../inc/header.php')) {
    require_once __DIR__ . '/../../inc/header.php';
}
