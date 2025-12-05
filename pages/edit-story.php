<?php
// pages/edit-story.php - Redirect to write-story.php with edit parameter
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/'));
    exit;
}

// Redirect to write-story.php with edit parameter
header('Location: ' . (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false 
    ? 'http://localhost/scrollnovels/pages/write-story.php?edit=' . $id 
    : 'https://' . $_SERVER['HTTP_HOST'] . '/pages/write-story.php?edit=' . $id));
exit;
?>

