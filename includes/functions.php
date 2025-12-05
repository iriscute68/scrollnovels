<?php
// includes/functions.php — small shared helpers used across the app.

// Ensure SITE_URL/config is available when functions are included directly from pages
if (!defined('SITE_URL')) {
    // Try common config locations: `config/config.php` (older layout) or root `config.php`.
    if (file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    } elseif (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    }
}

// Load UI components (small) so helper renderers are available
if (file_exists(__DIR__ . '/components/book-card.php')) {
    require_once __DIR__ . '/components/book-card.php';
}
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        if ($diff->y) return $diff->y . 'y ago';
        if ($diff->m) return $diff->m . 'mo ago';
        if ($diff->d) return $diff->d . 'd ago';
        if ($diff->h) return $diff->h . 'h ago';
        if ($diff->i) return $diff->i . 'm ago';
        return 'just now';
    }
}

if (!function_exists('notify')) {
    function notify($pdo, $user_id, $actor_id, $type, $message, $url = null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, message, url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $actor_id, $type, $message, $url]);
        } catch (Exception $e) {
            error_log('Notify failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path) {
        if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
        return rtrim(SITE_URL, '/') . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '') {
        if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
        if (empty($path)) return rtrim(SITE_URL, '/');
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('format_number')) {
    function format_number($num) {
        return number_format((int)$num);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('sorted')) {
    function sorted(array $arr) {
        sort($arr, SORT_NUMERIC);
        return $arr;
    }
}

if (!function_exists('slugify')) {
    function slugify($text) {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
}

if (!function_exists('getStorySlug')) {
    function getStorySlug($pdo, $story_id) {
        $stmt = $pdo->prepare('SELECT slug FROM stories WHERE id = ?');
        $stmt->execute([$story_id]);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('isApprovedAdmin')) {
    /**
     * Returns true if the current logged-in user is allowed to access admin.
     * Checks multiple authorization methods:
     *  1. Username 'Zakielenvt' (legacy hardcoded super admin)
     *  2. JSON roles column containing 'admin' role
     *  3. user_roles table with admin role
     */
    function isApprovedAdmin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) return false;
        global $pdo;
        try {
            // Fast username check (legacy super admin)
            $stmt = $pdo->prepare('SELECT username, roles FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) return false;
            
            // Check if username is the hardcoded super admin
            if ($row['username'] === 'Zakielenvt') return true;

            // Check roles stored as JSON in users.roles column (primary method)
            $rolesJson = $row['roles'] ?? null;
            if (!empty($rolesJson)) {
                $decoded = json_decode($rolesJson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $role) {
                        if (strtolower(trim($role)) === 'admin' || strtolower(trim($role)) === 'mod') {
                            return true;
                        }
                    }
                }
            }

            // Check normalized user_roles table for an 'admin' or 'mod' role (fallback)
            try {
                $r = $pdo->prepare('
                    SELECT 1 FROM user_roles ur 
                    JOIN roles r ON ur.role_id = r.id 
                    WHERE ur.user_id = ? AND (r.name = ? OR r.name = ?) 
                    LIMIT 1
                ');
                $r->execute([$_SESSION['user_id'], 'admin', 'mod']);
                if ($r->fetchColumn()) return true;
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }

            return false;
        } catch (Exception $e) {
            // If something went wrong (DB issue), default to false but log
            error_log('isApprovedAdmin check failed: ' . $e->getMessage());
            return false;
        }
    }
}

// hasAccess is a simple stub: check a purchases table or points; adapt for your logic
if (!function_exists('hasAccess')) {
    function hasAccess($user_id, $chapter_id) {
        if (!$user_id) return false;
        // Example: check a purchases table (purchases.user_id, purchases.chapter_id)
        global $pdo;
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM purchases WHERE user_id = ? AND chapter_id = ? LIMIT 1');
            $stmt->execute([$user_id, $chapter_id]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false; // default deny if no purchases table
        }
    }
}

if (!function_exists('story_link_by_id')) {
    function story_link_by_id($id, $slug = null) {
        if ($slug) return site_url('/pages/story.php?slug=' . urlencode($slug));
        return site_url('/pages/book.php?id=' . $id);
    }
}
