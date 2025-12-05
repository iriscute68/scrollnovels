<?php
// inc/roles_permissions.php
// Helper functions to check and manage roles for compatibility across admin pages.
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php'; // ensure $pdo exists when possible

function get_user_roles($pdo, $user_id) {
    if (!$user_id) return [];
    try {
        $stmt = $pdo->prepare('SELECT roles FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $rolesJson = $row['roles'] ?? null;
        if ($rolesJson) {
            $decoded = json_decode($rolesJson, true);
            if (is_array($decoded)) return $decoded;
        }
        // Fallback to user_roles join table
        $stmt2 = $pdo->prepare('SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?');
        $stmt2->execute([$user_id]);
        $names = $stmt2->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return $names;
    } catch (Exception $e) {
        return [];
    }
}

function is_moderator_or_above($pdo, $user_id) {
    $roles = get_user_roles($pdo, $user_id);
    foreach ($roles as $r) {
        $n = strtolower($r);
        if (in_array($n, ['admin','super_admin','moderator','mod'])) return true;
    }
    return false;
}

function is_admin_or_above($pdo, $user_id) {
    $roles = get_user_roles($pdo, $user_id);
    foreach ($roles as $r) {
        $n = strtolower($r);
        if (in_array($n, ['admin','super_admin'])) return true;
    }
    return false;
}

function require_admin_session() {
    if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
        header('Location: /pages/login.php');
        exit;
    }
}

function promote_user_to_role($pdo, $user_id, $roleName) {
    // Attempts to insert into roles and user_roles, and update users.roles JSON if present
    $roleName = trim($roleName);
    if ($roleName === '') return false;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $rid = $stmt->fetchColumn();
        if (!$rid) {
            $ins = $pdo->prepare('INSERT INTO roles (name) VALUES (?)');
            $ins->execute([$roleName]);
            $rid = $pdo->lastInsertId();
        }
        // ensure not duplicate
        $chk = $pdo->prepare('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1');
        $chk->execute([$user_id, $rid]);
        if (!$chk->fetchColumn()) {
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$user_id, $rid]);
        }
        // update users.roles JSON if exists
        $u = $pdo->prepare('SELECT roles FROM users WHERE id = ? LIMIT 1');
        $u->execute([$user_id]);
        $r = $u->fetchColumn();
        if ($r !== false) {
            $decoded = json_decode($r, true);
            if (!is_array($decoded)) $decoded = [];
            if (!in_array($roleName, $decoded)) $decoded[] = $roleName;
            $pdo->prepare('UPDATE users SET roles = ? WHERE id = ?')->execute([json_encode($decoded), $user_id]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch(Exception $e) {}
        return false;
    }
}

?>
