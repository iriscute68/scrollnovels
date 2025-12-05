<?php
// api/create-support-ticket.php - Create support ticket
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Please login to create a support ticket']));
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
// Fallback to form-encoded POST if JSON decode failed
if (!$data || !is_array($data)) {
    $data = $_POST;
}
$user_id = $_SESSION['user_id'];

$subject = trim($data['subject'] ?? '');
$description = trim($data['description'] ?? '');
$category = trim($data['category'] ?? 'other');
$priority = trim($data['priority'] ?? 'medium');

// Validate
if (empty($subject) || empty($description)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Subject and description are required']));
}

if (strlen($subject) < 5) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Subject must be at least 5 characters']));
}

if (!in_array($category, ['bug', 'feature', 'payment', 'account', 'content', 'other'])) {
    $category = 'other';
}

if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
    $priority = 'medium';
}

try {
    // Create table if not exists (avoid strict foreign key to be compatible with older DBs)
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        description LONGTEXT NOT NULL,
        category VARCHAR(50) DEFAULT 'other',
        priority VARCHAR(20) DEFAULT 'medium',
        status VARCHAR(20) DEFAULT 'open',
        assigned_admin_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Best-effort: ensure assigned_admin_id exists for older installs
    try {
        $pdo->exec("ALTER TABLE support_tickets ADD COLUMN IF NOT EXISTS assigned_admin_id INT NULL");
    } catch (Exception $e) {
        // ignore if it already exists or DB doesn't support IF NOT EXISTS
    }
    
    // Backwards-compatible insertion: detect whether the table uses 'description' or 'message'
    $colStmt = $pdo->query("SHOW COLUMNS FROM support_tickets");
    $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    $descCol = in_array('message', $cols) ? 'message' : (in_array('description', $cols) ? 'description' : 'message');

    $sql = "INSERT INTO support_tickets (user_id, subject, {$descCol}, category, priority, status) VALUES (?, ?, ?, ?, ?, 'open')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $subject, $description, $category, $priority]);
    $ticket_id = $pdo->lastInsertId();
    
    // Notify admins
    try {
        $notif_stmt = $pdo->prepare("
            SELECT id FROM users WHERE role IN ('admin', 'super_admin', 'moderator')
        ");
        $notif_stmt->execute();
        $admins = $notif_stmt->fetchAll();
        
        if (!empty($admins)) {
            $notif_title = "New Support Ticket: " . substr($subject, 0, 50);
            $notif_msg = "Priority: " . ucfirst($priority) . " | Category: " . ucfirst($category);
            
            foreach ($admins as $admin) {
                if (function_exists('notify')) {
                    notify($pdo, $admin['id'], $user_id, 'support_ticket', $notif_msg, "/admin/admin.php?page=support&ticket=" . $ticket_id);
                }
            }
        }
    } catch (Exception $e) {
        // Notification failed but ticket was created
        error_log('Support ticket notification error: ' . $e->getMessage());
    }
    
    http_response_code(201);
    // Fetch the created ticket to return to client
    try {
        // Re-detect the column name for the SELECT
        $colStmt2 = $pdo->query("SHOW COLUMNS FROM support_tickets");
        $cols2 = $colStmt2->fetchAll(PDO::FETCH_COLUMN);
        $descCol2 = in_array('message', $cols2) ? 'message' : (in_array('description', $cols2) ? 'description' : 'message');
        
        $tstmt = $pdo->prepare("SELECT id, user_id, subject, " . $descCol2 . " as description, category, priority, status, created_at, updated_at FROM support_tickets WHERE id = ? LIMIT 1");
        $tstmt->execute([$ticket_id]);
        $ticketRow = $tstmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $ticketRow = null;
    }

    echo json_encode([
        'success' => true,
        'ticket_id' => $ticket_id,
        'ticket' => $ticketRow,
        'message' => 'Support ticket created. Our team will respond shortly.'
    ]);
    
} catch (Exception $e) {
    // Log to a file for easier debugging
    $msg = '[' . date('c') . '] Ticket creation error: ' . $e->getMessage() . "\nRaw input: " . substr($raw ?? '', 0, 200) . "\n";
    @file_put_contents(__DIR__ . '/../logs/support_ticket_errors.log', $msg, FILE_APPEND | LOCK_EX);
    error_log('Ticket creation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create ticket: ' . $e->getMessage()]);
}