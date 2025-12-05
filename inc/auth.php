<?php
// inc/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Simple auth helpers. In production, strengthen sessions, regenerate IDs, use secure cookies.
 */

function is_logged_in(): bool {
  return !empty($_SESSION['user_id']);
}

function require_login(): void {
  if (!is_logged_in()) {
    header('Location: /pages/login.php');
    exit;
  }
}

function current_user_id(): ?int {
  return $_SESSION['user_id'] ?? null;
}

function current_user_role(): ?string {
  // Support different keys that may be set by various login endpoints
  if (isset($_SESSION['user_role'])) return $_SESSION['user_role'];
  if (isset($_SESSION['role'])) return $_SESSION['role'];
  if (isset($_SESSION['admin_role'])) return $_SESSION['admin_role'];
  // also support roles JSON
  if (isset($_SESSION['roles'])) {
    $roles = json_decode($_SESSION['roles'], true) ?: [];
    if (is_array($roles) && !empty($roles)) return $roles[0];
  }
  return null;
}

function is_admin(): bool {
  // Legacy flags
  if (!empty($_SESSION['is_admin'])) return true;

  $role = current_user_role();
  if ($role === 'admin' || $role === 'superadmin') return true;

  // Also check 'roles' JSON for any admin-level role
  $r = $_SESSION['roles'] ?? null;
  if ($r) {
    $arr = json_decode($r, true) ?: [];
    foreach ($arr as $v) {
      if (in_array($v, ['admin','superadmin','moderator'])) return true;
    }
  }

  return false;
}

function require_admin(): void {
  if (!is_admin()) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

/**
 * Simple flash messages
 */
function flash_set($key, $value) {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $_SESSION['flash_'.$key] = $value;
}
function flash_get($key) {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $k = 'flash_'.$key;
  if (isset($_SESSION[$k])) {
    $v = $_SESSION[$k];
    unset($_SESSION[$k]);
    return $v;
  }
  return null;
}
