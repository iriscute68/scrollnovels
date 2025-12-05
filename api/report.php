<?php
/**
 * Content Reporting API
 * 
 * Allows users to report inappropriate content (stories, chapters, comments, etc.)
 * Creates report record and notifies moderators via notifications system
 * 
 * POST /api/report.php
 * 
 * Expected POST parameters:
 * - target_type: 'story', 'chapter', 'comment', 'user', 'entry'
 * - target_id: ID of the content being reported (int)
 * - reason_code: 'spam', 'harassment', 'plagiarism', 'adult_content', 'copyright', 'other'
 * - details: Reporter's message (text, optional)
 * - evidence: JSON array of evidence (links, screenshots, etc., optional)
 */

session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/notifications.php';

header('Content-Type: application/json');

// Check authentication
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
  http_response_code(401);
  echo json_encode(['error' => 'login_required']);
  exit;
}

try {
  // Get POST data
  $raw = $_POST ?? [];
  $target_type = $raw['target_type'] ?? null;
  $target_id = intval($raw['target_id'] ?? 0);
  $reason_code = $raw['reason_code'] ?? null;
  $details = $raw['details'] ?? null;
  $evidence = $raw['evidence'] ?? null; // JSON string or array

  // Validate input
  if (!$target_type || !$target_id) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request', 'message' => 'target_type and target_id required']);
    exit;
  }

  // Validate target type
  $validTypes = ['story', 'chapter', 'comment', 'user', 'entry'];
  if (!in_array($target_type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_target_type', 'valid_types' => $validTypes]);
    exit;
  }

  // Insert report into database
  $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, target_type, target_id, reason_code, details, evidence, priority, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'open')");
  
  $priority = 2; // default
  if ($reason_code === 'harassment' || $reason_code === 'copyright') {
    $priority = 4;
  } elseif ($reason_code === 'adult_content') {
    $priority = 3;
  }

  $evidenceJson = null;
  if ($evidence) {
    if (is_string($evidence)) {
      $evidenceJson = $evidence;
    } else {
      $evidenceJson = json_encode($evidence);
    }
  }

  $stmt->execute([$uid, $target_type, $target_id, $reason_code, $details, $evidenceJson, $priority]);
  $reportId = $pdo->lastInsertId();

  // Notify all moderators about the report
  try {
    $modIds = $pdo->query("SELECT id FROM users WHERE role IN ('moderator', 'admin', 'super_admin')")
      ->fetchAll(PDO::FETCH_COLUMN);
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, title, body, url, meta, is_important)
                              VALUES (?, ?, 'report', ?, ?, ?, ?, 1)");
    
    foreach ($modIds as $modId) {
      $title = "New report: {$target_type} #{$target_id}";
      $body = $reason_code . (strlen($details) > 100 ? ': ' . substr($details, 0, 100) . '...' : '');
      $url = "/admin/moderation.php?report_id={$reportId}";
      $meta = json_encode(['report_id' => $reportId, 'target_type' => $target_type, 'target_id' => $target_id]);
      
      $notifStmt->execute([$modId, $uid, $title, $body, $url, $meta]);
    }
  } catch (Exception $e) {
    error_log("Failed to notify moderators: " . $e->getMessage());
    // Don't fail the report creation just because notification failed
  }

  http_response_code(201);
  echo json_encode([
    'ok' => true,
    'report_id' => $reportId,
    'message' => 'Report submitted successfully. Our moderation team will review it shortly.'
  ]);

} catch (PDOException $e) {
  error_log("Database error in report.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'database_error']);
} catch (Exception $e) {
  error_log("Error in report.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error']);
}

?>
