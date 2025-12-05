<?php
// api/get-rankings.php - Get book rankings by type (daily, weekly, monthly, all-time)

require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

$rankType = $_GET['type'] ?? 'weekly'; // daily, weekly, monthly, all_time
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);

// Validate rank type
if (!in_array($rankType, ['daily', 'weekly', 'monthly', 'all_time'])) {
    $rankType = 'weekly';
}

if ($limit > 100) $limit = 100;
if ($offset < 0) $offset = 0;

try {
    // Get rankings
    $stmt = $pdo->prepare("
        SELECT 
            br.rank_position,
            br.total_support_points,
            br.supporter_count,
            br.calculated_at,
            s.id as book_id,
            s.title,
            s.cover_url,
            s.slug,
            u.username as author,
            u.id as author_id,
            u.profile_image,
            (SELECT COUNT(*) FROM stories WHERE status = 'published') as total_books
        FROM book_rankings br
        JOIN stories s ON br.book_id = s.id
        LEFT JOIN users u ON s.author_id = u.id
        WHERE br.rank_type = ? AND s.status = 'published'
        ORDER BY br.total_support_points DESC, br.supporter_count DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$rankType, $limit, $offset]);
    $rankings = $stmt->fetchAll();

    // Get total ranking count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM book_rankings 
        WHERE rank_type = ? AND book_id IN (SELECT id FROM stories WHERE status = 'published')
    ");
    $stmt->execute([$rankType]);
    $totalCount = $stmt->fetch()['count'] ?? 0;

    // Assign rank positions if not set
    if (!empty($rankings)) {
        foreach ($rankings as $idx => &$book) {
            $book['rank'] = $offset + $idx + 1;
        }
    }

    echo json_encode([
        'success' => true,
        'type' => $rankType,
        'rankings' => $rankings,
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
