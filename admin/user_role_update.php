<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$id = isset($_POST["id"]) ? intval($_POST["id"]) : 0;
$role = isset($_POST["role"]) ? $_POST["role"] : '';

if ($id && $role) {
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$role, $id]);
}

echo "OK";
?>
