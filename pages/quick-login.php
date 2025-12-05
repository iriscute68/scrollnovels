<?php
// pages/quick-login.php - Dev session setter (remove in prod!)
require_once __DIR__ . '/../includes/auth.php';

// Quick login for development: set session and ensure roles via user_roles
$_SESSION['user_id'] = 1;  // Admin
$_SESSION['username'] = 'admin';
// Ensure roles assigned in DB (admin, author)
if (isset($pdo)) {
	try {
		$roles = ['admin','author'];
		foreach ($roles as $rname) {
			$r = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
			$r->execute([$rname]);
			$role_id = $r->fetchColumn();
			if (!$role_id) {
				$pdo->prepare('INSERT INTO roles (name) VALUES (?)')->execute([$rname]);
				$role_id = $pdo->lastInsertId();
			}
			$chk = $pdo->prepare('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1');
			$chk->execute([$_SESSION['user_id'], $role_id]);
			if (!$chk->fetchColumn()) {
				$pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$_SESSION['user_id'], $role_id]);
			}
		}
	} catch (Exception $e) { /* ignore */ }
}
header('Location: ' . rtrim(SITE_URL, '/') . '/pages/home.php');
exit;
?>
