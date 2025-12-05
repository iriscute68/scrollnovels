<?php
// upload-chapter.php
require_once dirname(__DIR__) . '/../../includes/auth.php';
require_once dirname(__DIR__) . '/../../config/db.php';
// functions: notify(), getStorySlug(), hasAccess(), etc.
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$story_id = (int)($_GET['story_id'] ?? 0);
$chapter_id = (int)($_GET['chapter_id'] ?? 0);

$user = getCurrentUser();

// Verify story ownership
$stmt = $pdo->prepare("SELECT id, title FROM stories WHERE id = ? AND author_id = ?");
$stmt->execute([$story_id, $user['id']]);
$story = $stmt->fetch();
if (!$story) die("Story not found");

$chapter = null;
if ($chapter_id) {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ? AND story_id = ?");
    $stmt->execute([$chapter_id, $story_id]);
    $chapter = $stmt->fetch();
}

// Get next chapter number
$next_num = $pdo->query("SELECT COALESCE(MAX(number),0)+1 FROM chapters WHERE story_id = $story_id")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $number = (int)$_POST['number'];
    $content = trim($_POST['content'] ?? '');
    $images = json_decode($_POST['images'] ?? '[]', true);

    // Validate
    if (empty($title)) die("Title required");
    if ($number < 1) die("Invalid chapter number");

    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $title));
    $slug = trim($slug, '-');

    if ($chapter_id) {
        $stmt = $pdo->prepare("UPDATE chapters SET title=?, slug=?, number=?, content=?, images=? WHERE id=?");
        $stmt->execute([$title, $slug, $number, $content, json_encode($images), $chapter_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO chapters (story_id, title, slug, number, content, images) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$story_id, $title, $slug, $number, $content, json_encode($images)]);
        // New chapter created — notify the story author if uploader is not the author
        $new_chapter_id = (int)$pdo->lastInsertId();
        try {
            $a = $pdo->prepare('SELECT author_id FROM stories WHERE id = ?');
            $a->execute([$story_id]);
            $author_id = $a->fetchColumn();
            if ($author_id && ($author_id != ($_SESSION['user_id'] ?? 0))) {
                // send notification to author
                    notify(
                    $pdo,
                    (int)$author_id,
                    $_SESSION['user_id'] ?? null,
                    'chapter',
                    'New chapter added to your story',
                    '/pages/story.php?slug=' . getStorySlug($pdo, $story_id) . '&ch=' . $number
                );
            }
        } catch (Exception $e) {
            // don't block the upload on notification errors
            error_log('Chapter notify error: ' . $e->getMessage());
        }
    }

    header("Location: write-story.php?id=$story_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Chapter - <?= htmlspecialchars($story['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/dropzone.css" rel="stylesheet">
</head>
<body>
<?php @include dirname(__DIR__) . '/includes/navbar.php'; ?>

<div class="container mt-4">
    <h2>Chapter: <?= $chapter ? 'Edit' : 'New' ?></h2>
    <p><strong>Story:</strong> <?= htmlspecialchars($story['title']) ?></p>

    <form method="POST" id="chapter-form">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label>Chapter Title</label>
                    <input type="text" name="title" class="form-control" value="<?= $chapter['title'] ?? '' ?>" required>
                </div>
                <div class="mb-3">
                    <label>Chapter Number</label>
                    <input type="number" name="number" class="form-control" value="<?= $chapter['number'] ?? $next_num ?>" min="1" required>
                </div>

                <div class="mb-3">
                    <label>Text Content (Optional for Webtoon)</label>
                    <textarea name="content" class="form-control" rows="10"><?= $chapter['content'] ?? '' ?></textarea>
                </div>

                <input type="hidden" name="images" id="images-input" value='<?= $chapter['images'] ?? "[]" ?>'>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Webtoon Images (Drag & Drop)</div>
                    <div class="card-body">
                        <div id="dropzone" class="dropzone"></div>
                        <small class="text-muted">Upload in order. First image = first page.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-success">Save Chapter</button>
            <a href="write-story.php?id=<?= $story_id ?>" class="btn btn-secondary">Back to Story</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/dropzone.js"></script>
<script>
Dropzone.autoDiscover = false;
const images = <?= $chapter['images'] ?? '[]' ?>;
const existingFiles = images.map((src, i) => ({
    name: src.split('/').pop(),
    size: 12345,
    accepted: true,
    url: '/uploads/chapters/' + src
}));

new Dropzone("#dropzone", {
    url: "/api/upload-image.php",
    paramName: "image",
    maxFilesize: 10,
    acceptedFiles: "image/*",
    addRemoveLinks: true,
    init: function() {
        existingFiles.forEach(file => this.emit("addedfile", file));
        existingFiles.forEach(file => this.emit("thumbnail", file, file.url));
        existingFiles.forEach(file => this.emit("complete", file));
        this.files = existingFiles;
    },
    success: function(file, res) {
        if (res.success) {
            file.serverId = res.filename;
            updateImagesInput();
        }
    },
    removedfile: function(file) {
        if (file.serverId) {
            fetch('/api/delete-image.php', {
                method: 'POST',
                body: JSON.stringify({ filename: file.serverId })
            });
        }
        file.previewElement.remove();
        updateImagesInput();
    }
});

function updateImagesInput() {
    const files = document.querySelectorAll('.dz-success');
    const names = Array.from(files).map(f => f._removeLink ? f.serverId || f.name : null).filter(Boolean);
    document.getElementById('images-input').value = JSON.stringify(names);
}
</script>
</body>
</html>