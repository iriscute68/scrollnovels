<?php
/**
 * api/review.php - Review management system
 * Handles: create/update, delete, report, and retrieve reviews
 * Enforces: 1 review per user per story
 */

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Must be logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    // Create reviews table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            rating TINYINT NOT NULL,
            review_text LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_book (user_id, book_id),
            KEY idx_book_id (book_id),
            KEY idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Create review reports table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS review_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_review_id (review_id),
            KEY idx_user_id (user_id),
            FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ============================================
    // CREATE OR UPDATE REVIEW
    // ============================================
    if ($action === 'store' || $action === 'save') {
        $bookId = (int)($_POST['book_id'] ?? $_POST['story_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $reviewText = $_POST['review_text'] ?? '';

        if (!$bookId || $rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid book_id or rating (1-5)']);
            exit;
        }

        // Check if book/story exists
        $bookCheck = $pdo->prepare("SELECT id FROM books WHERE id = ? UNION SELECT id FROM stories WHERE id = ?");
        $bookCheck->execute([$bookId, $bookId]);
        if (!$bookCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Story not found']);
            exit;
        }

        // Insert or update review (1 per user per story)
        $stmt = $pdo->prepare("
            INSERT INTO reviews (user_id, book_id, rating, review_text)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                review_text = VALUES(review_text),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([$userId, $bookId, $rating, $reviewText]);

        // Get the saved review
        $review = $pdo->prepare("
            SELECT * FROM reviews 
            WHERE user_id = ? AND book_id = ?
        ");
        $review->execute([$userId, $bookId]);
        $reviewData = $review->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Review saved successfully',
            'review' => $reviewData
        ]);
        exit;
    }

    // ============================================
    // DELETE REVIEW
    // ============================================
    if ($action === 'delete') {
        $reviewId = (int)($_POST['review_id'] ?? $_GET['review_id'] ?? 0);

        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'review_id required']);
            exit;
        }

        // Check if review belongs to user
        $review = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
        $review->execute([$reviewId, $userId]);

        if (!$review->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You can only delete your own reviews']);
            exit;
        }

        // Delete review (cascade deletes reports)
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);

        echo json_encode(['success' => true, 'message' => 'Review deleted']);
        exit;
    }

    // ============================================
    // REPORT REVIEW
    // ============================================
    if ($action === 'report') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $reason = $_POST['reason'] ?? 'Inappropriate content';

        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'review_id required']);
            exit;
        }

        // Check if review exists
        $reviewCheck = $pdo->prepare("SELECT id FROM reviews WHERE id = ?");
        $reviewCheck->execute([$reviewId]);
        if (!$reviewCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Review not found']);
            exit;
        }

        // Can't report own reviews
        $ownReview = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
        $ownReview->execute([$reviewId, $userId]);
        if ($ownReview->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You cannot report your own review']);
            exit;
        }

        // Check if already reported by this user
        $alreadyReported = $pdo->prepare("
            SELECT id FROM review_reports 
            WHERE review_id = ? AND user_id = ?
        ");
        $alreadyReported->execute([$reviewId, $userId]);
        if ($alreadyReported->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You already reported this review']);
            exit;
        }

        // Create report
        $stmt = $pdo->prepare("
            INSERT INTO review_reports (review_id, user_id, reason)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$reviewId, $userId, $reason]);

        echo json_encode(['success' => true, 'message' => 'Review reported for moderation']);
        exit;
    }

    // ============================================
    // GET REVIEWS FOR BOOK
    // ============================================
    if ($action === 'get' || $action === 'list') {
        $bookId = (int)($_GET['book_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = (int)($_GET['offset'] ?? 0);

        if (!$bookId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'book_id required']);
            exit;
        }

        $limit = min($limit, 50); // Max 50

        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE book_id = ?");
        $countStmt->execute([$bookId]);
        $total = $countStmt->fetchColumn();

        // Get paginated reviews
        $stmt = $pdo->prepare("
            SELECT r.*, u.username, u.profile_image
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            WHERE r.book_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$bookId, $limit, $offset]);
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
    // GET USER'S OWN REVIEW FOR BOOK
    // ============================================
    if ($action === 'get_user_review') {
        $bookId = (int)($_GET['book_id'] ?? 0);

        if (!$bookId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'book_id required']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT * FROM reviews 
            WHERE user_id = ? AND book_id = ?
        ");
        $stmt->execute([$userId, $bookId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'review' => $review
        ]);
        exit;
    }

    // ============================================
    // GET USER'S REVIEWS (PROFILE)
    // ============================================
    if ($action === 'user_reviews') {
        $targetUserId = (int)($_GET['user_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = (int)($_GET['offset'] ?? 0);

        if (!$targetUserId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'user_id required']);
            exit;
        }

        $limit = min($limit, 50);

        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
        $countStmt->execute([$targetUserId]);
        $total = $countStmt->fetchColumn();

        // Get reviews
        $stmt = $pdo->prepare("
            SELECT r.*, b.title as book_title, b.cover_image
            FROM reviews r
            JOIN books b ON r.book_id = b.id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$targetUserId, $limit, $offset]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => $total,
            'reviews' => $reviews
        ]);
        exit;
    }

    // No valid action
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
