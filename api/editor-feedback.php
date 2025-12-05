<?php
// api/editor-feedback.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('editor')) exit;

$assignment_id = (int)$_POST['assignment_id'];
$feedback = trim($_POST['feedback']);
$action = $_POST['action'] ?? 'review';

$stmt = $pdo->prepare("SELECT story_id, editor_id FROM editor_assignments WHERE id = ?");
$stmt->execute([$assignment_id]);
$assign = $stmt->fetch();
if (!$assign || $assign['editor_id'] != $_SESSION['user_id']) die("Invalid");

$status = match($action) {
    'approve' => 'approved',
    'reject' => 'rejected',
    default => 'reviewing'
};

$pdo->prepare("UPDATE editor_assignments SET feedback = ?, status = ? WHERE id = ?")
    ->execute([$feedback, $status, $assignment_id]);

// Update story status
if (in_array($status, ['approved', 'rejected'])) {
    $new_status = $status == 'approved' ? 'published' : 'rejected';
    $pdo->prepare("UPDATE stories SET status = ? WHERE id = ?")
        ->execute([$new_status, $assign['story_id']]);

    // Notify author
    require_once '../includes/functions.php';
    $author_id = $pdo->query("SELECT author_id FROM stories WHERE id = {$assign['story_id']}")->fetchColumn();
    notify($pdo, $author_id, $_SESSION['user_id'], 'review', 
        "Your story has been " . ($status == 'approved' ? 'approved' : 'rejected') . "!", 
        "/book.php?id={$assign['story_id']}"
    );
}

header("Location: /editor.php");
?>