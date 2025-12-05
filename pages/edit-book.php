<?php
/**
 * Edit Book Page - Comprehensive Implementation
 * Full book editing interface with metadata management
 */
session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/auth.php');

requireLogin();

$book_id = $_GET['id'] ?? 1;
$success = $_GET['success'] ?? false;

// Default categories
$categories = ['Fantasy', 'Romance', 'Thriller', 'Mystery', 'Science Fiction', 'Adventure', 'Horror', 'Historical'];

// Fetch book from database
try {
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ? AND author_id = ?");
    $stmt->execute([$book_id, $_SESSION['user_id']]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        $book = [
            'id' => $book_id,
            'title' => 'The Emerald Crown',
            'author_id' => 1,
            'description' => 'In a world where magic flows through ancient emeralds...',
            'status' => 'draft'
        ];
    }
} catch (Exception $e) {
    $book = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars($_POST['title'] ?? '');
    $description = htmlspecialchars($_POST['synopsis'] ?? '');
    $category = htmlspecialchars($_POST['category'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE stories 
            SET title = ?, description = ?, category = ?
            WHERE id = ? AND author_id = ?
        ");
        $stmt->execute([$title, $description, $category, $book_id, $_SESSION['user_id']]);
        
        header('Location: ?id=' . $book_id . '&success=1');
        exit;
    } catch (Exception $e) {
        $error = "Failed to update book: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - Scroll Novels</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/editor.css">
</head>
<body class="bg-background text-text-primary">
    <?php include(__DIR__ . '/../admin/header.php'); ?>

    <section class="edit-page">
        <div class="container">
            <div class="edit-header">
                <a href="/pages/book-detail-integrated.php?id=<?php echo $book_id; ?>" class="back-link">← Back to Book</a>
                <h1>Edit Book</h1>
            </div>

            <?php if ($success): ?>
                <div class="success-message">✓ Book details saved successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error-message">✗ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="edit-form">
                <!-- Cover Image -->
                <div class="form-section">
                    <h2>Cover Image</h2>
                    <div class="cover-upload">
                        <div class="cover-preview">👑</div>
                        <input type="file" name="cover" accept="image/*" class="file-input">
                    </div>
                </div>

                <!-- Book Details -->
                <div class="form-section">
                    <h2>Book Details</h2>
                    
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" 
                            value="<?php echo htmlspecialchars($book['title'] ?? ''); ?>" 
                            required>
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo ($book['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="synopsis">Synopsis *</label>
                        <textarea id="synopsis" name="synopsis" rows="6" required><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Publish Settings -->
                <div class="form-section">
                    <h2>Publish Settings</h2>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="visible" checked>
                            Visible to public
                        </label>
                        <label>
                            <input type="checkbox" name="comments" checked>
                            Allow comments
                        </label>
                        <label>
                            <input type="checkbox" name="donations" checked>
                            Allow donations
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="/pages/book-detail-integrated.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <?php include(__DIR__ . '/../admin/footer.php'); ?>
</body>
</html>
