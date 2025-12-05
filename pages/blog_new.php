<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $content = $_POST['content'] ?? '';
    $cover_image = $_POST['cover_image'] ?? '';
    $category = $_POST['category'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $author_id = $_SESSION['user_id'] ?? 1;
    $created_at = date('Y-m-d H:i:s');
    $response = ['success' => false, 'message' => ''];
    try {
        $stmt = $pdo->prepare("INSERT INTO posts (title, slug, excerpt, content, cover_image, category, status, author_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $excerpt, $content, $cover_image, $category, $status, $author_id, $created_at]);
        $response['success'] = true;
        $response['message'] = 'Blog post created successfully.';
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// Render blog creation form with rich text editor
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="max-w-3xl mx-auto p-8 mt-16">';
    echo '<h1 class="text-3xl font-bold mb-6">Create New Blog Post</h1>';
    echo '<form id="blogForm" class="space-y-6" method="POST" onsubmit="document.getElementById(\'content\').value = getEditorContent();">';
    echo '<input type="text" name="title" id="title" class="input-field w-full mb-4" placeholder="Title" required />';
    echo '<input type="text" name="slug" id="slug" class="input-field w-full mb-4" placeholder="Slug" required />';
    echo '<input type="text" name="excerpt" id="excerpt" class="input-field w-full mb-4" placeholder="Excerpt" maxlength="500" />';
    echo '<input type="text" name="cover_image" id="cover_image" class="input-field w-full mb-4" placeholder="Cover Image URL" />';
    echo '<input type="text" name="category" id="category" class="input-field w-full mb-4" placeholder="Category" />';
    echo '<select name="status" id="status" class="input-field w-full mb-4"><option value="draft">Draft</option><option value="published">Published</option></select>';
    echo '<input type="hidden" name="content" id="content" />';
    require_once __DIR__ . '/../includes/components/rich-text-editor.php';
    echo '<button type="submit" class="btn btn-primary px-6 py-2 rounded font-semibold">Create Post</button>';
    echo '</form>';
    echo '</div>';
    require_once __DIR__ . '/../includes/footer.php';
}
