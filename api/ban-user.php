<?php
// api/ban-user.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) exit;

$user_id = (int)($_POST['user_id'] ?? 0);
if ($user_id <= 0) {
	header('Location: ' . rtrim(SITE_URL, '/') . '/admin/admin.php#users');
	exit;
}

// Remove existing role mappings and set 'banned' role
$pdo->beginTransaction();
try {
	$pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$user_id]);
	$r = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
	$r->execute(['banned']);
	$role_id = $r->fetchColumn();
	if (!$role_id) {
		$ins = $pdo->prepare('INSERT INTO roles (name) VALUES (?)');
		$ins->execute(['banned']);
		$role_id = $pdo->lastInsertId();
	}
	$ur = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
	$ur->execute([$user_id, $role_id]);
	$pdo->commit();
} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
}

header('Location: ' . rtrim(SITE_URL, '/') . '/admin/admin.php#users');
?>