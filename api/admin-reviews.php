<?php
/**
 * api/admin-reviews.php - Admin review moderation system
 * Requires admin authentication
 */

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

// Admin check
requireLogin();
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Auth error']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    // ============================================
    // VIEW ALL REVIEWS (WITH SEARCH)
    // ============================================
    if ($action === 'list' || $action === 'get_all') {
        $search = $_GET['search'] ?? '';
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $limit = min($limit, 100);

        $query = "
            SELECT r.id, r.user_id, r.book_id, r.rating, r.review_text, 
                   r.created_at, r.updated_at,
                   u.username as author_username,
                   b.title as book_title
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN books b ON r.book_id = b.id
        ";

        $params = [];

        if ($search) {
            $query .= " WHERE (r.review_text LIKE ? OR u.username LIKE ? OR b.title LIKE ?)";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        // Get count
        $countQuery = "SELECT COUNT(*) FROM reviews r";
        if ($search) {
            $countQuery .= " JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id";
            $countQuery .= " WHERE (r.review_text LIKE ? OR u.username LIKE ? OR b.title LIKE ?)";
        }

        $countStmt = $pdo->prepare($countQuery);
        if ($search) {
            $countStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        } else {
            $countStmt->execute();
        }
        $total = $countStmt->fetchColumn();

        // Get reviews
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'reviews' => $reviews
        ]);
        exit;
    }

    // ============================================
    // DELETE REVIEW (ADMIN)
    // ============================================
    if ($action === 'delete') {
        $reviewId = (int)($_POST['review_id'] ?? 0);

        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'review_id required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);

        echo json_encode(['success' => true, 'message' => 'Review deleted by admin']);
        exit;
    }

    // ============================================
    // VIEW REPORTED REVIEWS
    // ============================================
    if ($action === 'reports' || $action === 'get_reports') {
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $limit = min($limit, 100);

        // Get count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM review_reports");
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        // Get reports with review details
        $stmt = $pdo->prepare("
            SELECT rr.id as report_id, rr.reason as report_reason, rr.created_at as report_date,
                   r.id as review_id, r.rating, r.review_text,
                   u_author.username as review_author,
                   u_reporter.username as reporter_username,
                   b.title as book_title,
                   COUNT(DISTINCT rr2.id) as total_reports
            FROM review_reports rr
            JOIN reviews r ON rr.review_id = r.id
            JOIN users u_author ON r.user_id = u_author.id
            JOIN users u_reporter ON rr.user_id = u_reporter.id
            JOIN books b ON r.book_id = b.id
            LEFT JOIN review_reports rr2 ON r.id = rr2.review_id
            GROUP BY r.id
            ORDER BY rr.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => $total,
            'reports' => $reports
        ]);
        exit;
    }

    // ============================================
    // RESOLVE REPORT (DELETE REVIEW + CLEAR REPORTS)
    // ============================================
    if ($action === 'resolve_report') {
        $reviewId = (int)($_POST['review_id'] ?? 0);

        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'review_id required']);
            exit;
        }

        // Delete review (cascade deletes reports)
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);

        echo json_encode(['success' => true, 'message' => 'Review and reports deleted']);
        exit;
    }

    // ============================================
    // DISMISS REPORT (KEEP REVIEW, DELETE REPORT)
    // ============================================
    if ($action === 'dismiss_report') {
        $reportId = (int)($_POST['report_id'] ?? 0);

        if (!$reportId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'report_id required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM review_reports WHERE id = ?");
        $stmt->execute([$reportId]);

        echo json_encode(['success' => true, 'message' => 'Report dismissed']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
