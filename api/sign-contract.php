<?php
// api/sign-contract.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$contract_id = (int)$_POST['contract_id'];
$signature = $_POST['signature'];

$stmt = $pdo->prepare("SELECT author_id FROM contracts WHERE id = ? AND status = 'pending'");
$stmt->execute([$contract_id]);
$contract = $stmt->fetch();
if (!$contract || $contract['author_id'] != $_SESSION['user_id']) die("Invalid");

$sig_path = "signatures/sig_{$contract_id}.png";
file_put_contents("../$sig_path", file_get_contents($signature));

$pdo->prepare("UPDATE contracts SET status = 'signed', signature_image = ?, signed_at = NOW() WHERE id = ?")
    ->execute([$sig_path, $contract_id]);

header("Location: /contracts.php");
?>