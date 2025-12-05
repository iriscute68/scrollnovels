<?php
// admin/ajax/approve_story.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

if (!isAdminLoggedIn()) { 
  echo json_encode(['error'=>'Unauthorized']); 
  exit; 
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if (!$id) { 
  echo json_encode(['error'=>'Invalid id']); 
  exit; 
}

try {
  $stmt = $pdo->prepare("UPDATE stories SET status='Published' WHERE id=?");
  $stmt->execute([$id]);
  
  // Log activity
  $stmt = $pdo->prepare("INSERT INTO activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$_SESSION['admin_id'], 'Story Published', "Story ID: $id"]);
  
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
?>
