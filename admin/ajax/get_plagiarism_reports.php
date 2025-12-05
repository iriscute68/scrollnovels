<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
session_start();
if (!in_array($_SESSION['role'] ?? '', ['admin','super_admin','moderator'])) {
    http_response_code(403); echo json_encode(['reports'=>[]]); exit;
}

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = 'WHERE 1=1';
$params = [];
if ($q) { $where .= ' AND (p.id = ? OR c.title LIKE ?)'; $params[] = $q; $params[] = "%$q%"; }
if ($status) { $where .= ' AND p.status = ?'; $params[] = $status; }

$sql = "SELECT p.*, c.title AS chapter_title, s.title AS story_title, u.username AS author FROM plagiarism_reports p JOIN chapters c ON c.id = p.chapter_id JOIN stories s ON s.id = p.story_id LEFT JOIN users u ON u.id = s.author_id $where ORDER BY p.created_at DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['matches'] = json_decode($r['matches_json'] ?? '[]', true) ?: [];
    $r['score'] = floatval($r['score'] ?? 0);
}

echo json_encode(['reports' => $rows]);

?>
<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    $status = $_GET['status'] ?? 'all';
    $limit = min((int)($_GET['limit'] ?? 100), 500);
    
    $query = "
        SELECT pr.*, c.title, s.title as story_title, u.username 
        FROM plagiarism_reports pr 
        JOIN chapters c ON pr.chapter_id = c.id 
        JOIN stories s ON c.story_id = s.id 
        JOIN users u ON s.author_id = u.id
    ";
    $params = [];
    
    if ($status !== 'all') {
        $query .= " WHERE pr.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY pr.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [];
    $summary['total'] = count($reports);
    $summary['high_plagiarism'] = count(array_filter($reports, fn($r) => $r['match_percentage'] >= 75));
    $summary['medium'] = count(array_filter($reports, fn($r) => $r['match_percentage'] >= 25 && $r['match_percentage'] < 75));
    
    echo json_encode([
        'ok' => true,
        'reports' => $reports,
        'summary' => $summary
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
