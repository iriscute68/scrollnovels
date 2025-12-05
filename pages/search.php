<?php
// search.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

$genres = $pdo->query("SELECT id, name FROM categories WHERE type = 'story'")->fetchAll();
$tags = $pdo->query("SELECT name, COUNT(*) as count FROM tags GROUP BY name ORDER BY count DESC LIMIT 20")->fetchAll();
?>

<?php
    $page_title = 'Search - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">'
        . '<style>.filter-card{border-right:1px solid #ddd}@media(max-width:768px){.filter-card{border-right:none;border-bottom:1px solid #ddd}}.tag-cloud a{font-size:.9rem}.tag-cloud a:hover{text-decoration:underline}.story-card{transition:transform .2s}.story-card:hover{transform:translateY(-3px)}</style>';

    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Filters -->
        <div class="col-md-3 filter-card">
            <div class="sticky-top" style="top: 70px;">
                <h5>Filters</h5>
                <form id="filter-form">
                    <div class="mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="q" class="form-control" placeholder="Title, author, description..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Genre</label>
                        <select name="genre" class="form-select">
                            <option value="">All Genres</option>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= ($_GET['genre'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Any</option>
                            <option value="published" <?= ($_GET['status'] ?? '') == 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="ongoing" <?= ($_GET['status'] ?? '') == 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="completed" <?= ($_GET['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="hiatus" <?= ($_GET['status'] ?? '') == 'hiatus' ? 'selected' : '' ?>>Hiatus</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="views" <?= ($_GET['sort'] ?? 'views') == 'views' ? 'selected' : '' ?>>Most Views</option>
                            <option value="rating" <?= ($_GET['sort'] ?? '') == 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                            <option value="chapters" <?= ($_GET['sort'] ?? '') == 'chapters' ? 'selected' : '' ?>>Most Chapters</option>
                            <option value="newest" <?= ($_GET['sort'] ?? '') == 'newest' ? 'selected' : '' ?>>Newest</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Min Rating</label>
                        <select name="rating" class="form-select">
                            <option value="">Any</option>
                            <option value="4" <?= ($_GET['rating'] ?? '') == '4' ? 'selected' : '' ?>>4+ Stars</option>
                            <option value="3" <?= ($_GET['rating'] ?? '') == '3' ? 'selected' : '' ?>>3+ Stars</option>
                            <option value="2" <?= ($_GET['rating'] ?? '') == '2' ? 'selected' : '' ?>>2+ Stars</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chapters</label>
                        <select name="chapters" class="form-select">
                            <option value="">Any</option>
                            <option value="10" <?= ($_GET['chapters'] ?? '') == '10' ? 'selected' : '' ?>>10+ Chapters</option>
                            <option value="50" <?= ($_GET['chapters'] ?? '') == '50' ? 'selected' : '' ?>>50+ Chapters</option>
                            <option value="100" <?= ($_GET['chapters'] ?? '') == '100' ? 'selected' : '' ?>>100+ Chapters</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    <a href="search.php" class="btn btn-outline-secondary w-100 mt-2">Clear</a>
                </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
let currentPage = 1;

function loadResults(page = 1) {
    currentPage = page;
    const form = $('#filter-form').serialize() + '&page=' + page;
    $.get('/api/search.php?' + form, function(data) {
        $('#search-results').html(data.html);
        $('#pagination').html(data.pagination);
    });
}

$('#filter-form').on('submit', function(e) {
    e.preventDefault();
    loadResults(1);
});

$(document).on('click', '.page-link', function(e) {
    e.preventDefault();
    const page = $(this).data('page');
    loadResults(page);
    $('html, body').animate({ scrollTop: 0 }, 300);
});

// Initial load
$(document).ready(() => loadResults(1));
</script>
</body>
</html>
        $('#search-results').html(data.html);
        $('#pagination').html(data.pagination);
    });
}

$('#filter-form').on('submit', function(e) {
    e.preventDefault();
    loadResults(1);
});

$(document).on('click', '.page-link', function(e) {
    e.preventDefault();
    const page = $(this).data('page');
    loadResults(page);
    $('html, body').animate({ scrollTop: 0 }, 300);
});

// Initial load
$(document).ready(() => loadResults(1));
</script>
</body>
</html>
