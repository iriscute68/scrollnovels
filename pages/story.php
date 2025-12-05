<?php
// Support both legacy `id` and modern `slug`-based routes
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int)($_GET['id'] ?? $_REQUEST['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

if (!$id && $slug) {
    // Lookup by slug (allow any status)
    try {
        $stmt = $pdo->prepare('SELECT id FROM stories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row) {
            $id = (int)$row['id'];
        } else {
            // Try a few fallback matching strategies if slug isn't found.
            // 1) Case-insensitive partial match and removing dashes
            $fallbackStmt = $pdo->prepare('SELECT id FROM stories WHERE LOWER(slug) LIKE ? OR LOWER(REPLACE(slug, "-", "")) = LOWER(REPLACE(?, "-", "")) LIMIT 1');
            $fallbackStmt->execute(['%' . strtolower($slug) . '%', $slug]);
            $row = $fallbackStmt->fetch();
            if ($row) {
                $id = (int)$row['id'];
            }
        }
    } catch (Exception $e) {
        // ignore and treat as not-found
    }
}

    if (!$id) {
        // Log the missing slug for debugging.
        error_log("story.php: Could not map slug to id: '" . $slug . "'");
    // If still no id, try to redirect to browse
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Forward to the main book view by setting id in GET
$_GET['id'] = $id;
require __DIR__ . '/book.php';

?>

