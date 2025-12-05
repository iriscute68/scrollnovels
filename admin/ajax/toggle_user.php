<?php
// admin/ajax/toggle_user.php
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
  $stmt = $pdo->prepare("SELECT status FROM users WHERE id=?");
  $stmt->execute([$id]);
  $user = $stmt->fetch();
  
  if (!$user) { 
    echo json_encode(['error'=>'User not found']); 
    exit; 
  }
  
  $new = ($user['status'] === 'Active') ? 'Suspended' : 'Active';
  $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
  $stmt->execute([$new, $id]);
  
  // Log activity
  $stmt = $pdo->prepare("INSERT INTO activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$_SESSION['admin_id'], 'User Status Changed', "User ID: $id, New Status: $new"]);
  
  echo json_encode(['success'=>true, 'status'=>$new]);
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
?>
