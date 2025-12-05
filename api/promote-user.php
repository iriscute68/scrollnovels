<?php
// api/promote-user.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) exit;

$user_id = (int)($_POST['user_id'] ?? 0);
if ($user_id <= 0) {
	header('Location: ' . rtrim(SITE_URL, '/') . '/admin/admin.php#users');
	exit;
}

// Ensure 'admin' role exists and assign it if not already assigned
try {
	$pdo->beginTransaction();
	$r = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
	$r->execute(['admin']);
	$role_id = $r->fetchColumn();
	if (!$role_id) {
		$ins = $pdo->prepare('INSERT INTO roles (name) VALUES (?)');
		$ins->execute(['admin']);
		$role_id = $pdo->lastInsertId();
	}
	// Insert mapping if missing
	$chk = $pdo->prepare('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1');
	$chk->execute([$user_id, $role_id]);
	if (!$chk->fetchColumn()) {
		$pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$user_id, $role_id]);
	}
	$pdo->commit();
} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
}

header('Location: ' . rtrim(SITE_URL, '/') . '/admin/admin.php#users');
?>