<?php
// api/contract-pdf.php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // TCPDF
requireLogin();

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT c.*, u.username FROM contracts c JOIN users u ON c.author_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$contract = $stmt->fetch();
if (!$contract || !in_array($contract['author_id'], [$_SESSION['user_id'], 1])) die("Access denied");

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

$html = "
<h1>{$contract['title']}</h1>
<p><strong>Author:</strong> {$contract['username']}</p>
<p><strong>Royalty:</strong> {$contract['royalty_rate']}%</p>
<div>{$contract['terms']}</div>
<br><br>
<p><strong>Signed on:</strong> " . date('F j, Y', strtotime($contract['signed_at'])) . "</p>
<img src='../{$contract['signature_image']}' width='150'>
";

$pdf->writeHTML($html);
$pdf->Output("contract_{$id}.pdf", 'D');
?>