<?php
// api/delete-ad.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) exit;

$ad_id = (int)$_POST['ad_id'];
$pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([$ad_id]);
header("Location: /ads.php");
?>