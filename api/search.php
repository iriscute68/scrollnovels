<?php
// api/search.php
require_once '../includes/auth.php';
require_once '../config/db.php';

$q = trim($_GET['q'] ?? '');
$genre = (int)($_GET['genre'] ?? 0);
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'views';
$rating = (float)($_GET['rating'] ?? 0);
$chapters = (int)($_GET['chapters'] ?? 0);
$tag = trim($_GET['tag'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

$where = ['s.status = "published"'];
$params = [];

if ($q) {
    $where[] = "(s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($genre) {
    $where[] = "s.category_id = ?";
    $params[] = $genre;
}
if ($status) {
    $where[] = "s.status = ?";
    $params[] = $status;
}
if ($rating > 0) {
    $where[] = "COALESCE(AVG(r.rating), 0) >= ?";
    $params[] = $rating;
}
if ($chapters > 0) {
    $where[] = "(SELECT COUNT(*) FROM chapters WHERE story_id = s.id) >= ?";
    $params[] = $chapters;
}
if ($tag) {
    $where[] = "EXISTS (SELECT 1 FROM story_tags st JOIN tags t ON st.tag_id = t.id WHERE st.story_id = s.id AND t.name = ?)";
    $params[] = $tag;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$order = match($sort) {
    'rating' => 'avg_rating DESC',
    'chapters' => 'chapter_count DESC',
    'newest' => 's.created_at DESC',
    default => 's.views DESC'
};

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM stories s
    JOIN users u ON s.author_id = u.id
    LEFT JOIN ratings r ON r.story_id = s.id
    $where_sql
    GROUP BY s.id
");
$count_stmt->execute($params);
$total = $count_stmt->rowCount();

$stmt = $pdo->prepare("
    SELECT s.id, s.title, s.slug, s.cover, s.description, u.username,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           (SELECT COUNT(*) FROM chapters WHERE story_id = s.id) as chapter_count
    FROM stories s
    JOIN users u ON s.author_id = u.id
    LEFT JOIN ratings r ON r.story_id = s.id
    $where_sql
    GROUP BY s.id
    ORDER BY $order
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$results = $stmt->fetchAll();

$html = '';
foreach ($results as $r) {
    $html .= "
    <div class='col-md-4 mb-4'>
        <div class='card story-card h-100'>
            <img src='" . htmlspecialchars($r['cover'] ?? '/assets/default-cover.jpg') . "' class='card-img-top' style='height:180px; object-fit:cover;'>
            <div class='card-body d-flex flex-column'>
                <h6 class='card-title'><a href='/pages/story.php?slug={$r['slug']}' class='text-decoration-none'>" . htmlspecialchars($r['title']) . "</a></h6>
                <small class='text-muted'>by " . htmlspecialchars($r['username']) . "</small>
                <small><i class='fas fa-star text-warning'></i> " . number_format($r['avg_rating'], 1) . " | " . $r['chapter_count'] . " chapters</small>
                <p class='mt-2 flex-grow-1' style='font-size:0.9rem;'>" . substr(htmlspecialchars($r['description']), 0, 100) . "...</p>
                <a href='/pages/story.php?slug={$r['slug']}' class='btn btn-sm btn-outline-primary mt-auto'>Read</a>
            </div>
        </div>
    </div>";
}

$pages = ceil($total / $limit);
$pagination = '';
if ($pages > 1) {
    $pagination .= "<nav><ul class='pagination'>";
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i == $page ? 'active' : '';
        $pagination .= "<li class='page-item $active'><a class='page-link' href='#' data-page='$i'>$i</a></li>";
    }
    $pagination .= "</ul></nav>";
}

echo json_encode(['html' => $html ?: '<p class="text-center text-muted">No stories found.</p>', 'pagination' => $pagination]);
?>