<?php
// Minimal notifications footer wrapper — include global footer when available
if (file_exists(__DIR__ . '/../../includes/footer.php')) {
    require_once __DIR__ . '/../../includes/footer.php';
} elseif (file_exists(__DIR__ . '/../../inc/footer.php')) {
    require_once __DIR__ . '/../../inc/footer.php';
}
