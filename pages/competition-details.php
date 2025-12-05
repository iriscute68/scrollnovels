<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Load functions early so we can use site_url() in error handlers
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$isAdmin = isset($_SESSION['admin_id']) || isset($_SESSION['admin_user']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$compId = intval($_GET['id'] ?? 0);

if (!$compId) {
    header('Location: ' . site_url('/pages/competitions.php'));
    exit;
}

$competition = null;
$entries = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
    $stmt->execute([$compId]);
    $competition = $stmt->fetch();
    
    if ($competition) {
        // Check if user can view this competition
        // Only show published/active competitions to public, drafts/closed only to admins
        $publicStatuses = ['published', 'active', 'upcoming']; // Support old and new status values
        if (!in_array($competition['status'], $publicStatuses) && !$isAdmin) {
            // Not public status and user is not admin - show 404
            header('Location: ' . site_url('/pages/competitions.php'));
            exit;
        }
    } else {
        // Not found - redirect to competitions list
        header('Location: ' . site_url('/pages/competitions.php'));
        exit;
    }
    
    // Get entries (exclude disqualified entries)
    $stmt = $pdo->prepare("SELECT ce.*, s.title as book_title, s.cover, u.username as author_name FROM competition_entries ce JOIN stories s ON ce.story_id = s.id JOIN users u ON ce.user_id = u.id WHERE ce.competition_id = ? AND ce.status != 'disqualified' ORDER BY ce.total_score DESC LIMIT 50");
    $stmt->execute([$compId]);
    $entries = $stmt->fetchAll();
} catch (Exception $e) {
    // If error, log it and redirect to competitions list
    error_log("competition-details.php ERROR for ID {$compId}: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    header('Location: ' . site_url('/pages/competitions.php'));
    exit;
}

$reqs = json_decode($competition['requirements_json'] ?? '{}', true);
$prizes = json_decode($competition['prize_info'] ?? '{}', true);

require_once __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($competition['title'] ?? 'Competition') ?> - Scroll Novels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #faf8f5;
            color: #1f2937;
        }

        main {
            padding-top: 80px !important;
            padding-bottom: 2rem;
        }

        .detail-header {
            background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
            color: white;
            padding: 1.5rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            overflow: hidden;
        }

        .detail-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            border-radius: 12px;
            z-index: 1;
        }

        .detail-header .detail-container {
            position: relative;
            z-index: 2;
        }

        .detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .detail-title {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .detail-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-body {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .detail-content {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .requirement-list {
            list-style: none;
            space-y: 0.5rem;
        }

        .requirement-list li {
            padding: 0.75rem;
            background: #f9fafb;
            border-left: 4px solid #065f46;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }

        .entries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .entry-card {
            background: #f9fafb;
            border-radius: 8px;
            overflow: hidden;
            text-align: center;
            transition: all 0.3s;
        }

        .entry-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .entry-cover {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .entry-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .entry-info {
            padding: 1rem;
        }

        .entry-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .entry-author {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }

        .entry-score {
            font-size: 1.2rem;
            font-weight: 700;
            color: #065f46;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .prize-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .prize-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .prize-icon {
            font-size: 1.5rem;
        }

        .prize-text {
            font-weight: 600;
        }

        .prize-value {
            color: #065f46;
            font-weight: 700;
        }

        .countdown {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1f2937;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #065f46;
            color: white;
        }

        .btn-primary:hover {
            background: #064e40;
        }

        .btn-secondary {
            background: #fbbf24;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background: #f59e0b;
        }

        @media (max-width: 768px) {
            .detail-body {
                grid-template-columns: 1fr;
            }

            .detail-title {
                font-size: 1.8rem;
            }

            .entries-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
                color: #e0e0e0;
            }

            .detail-content {
                background: #2d2d2d;
                border-color: #404040;
            }

            .sidebar-card {
                background: #2d2d2d;
                border-color: #404040;
            }

            .requirement-list li {
                background: rgba(255, 255, 255, 0.05);
                color: #e0e0e0;
            }

            .entry-card {
                background: rgba(255, 255, 255, 0.05);
            }

            .entry-info {
                background: #1a1a1a;
            }

            .section-title {
                color: #e0e0e0;
            }

            .card-title {
                color: #e0e0e0;
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="detail-header" style="<?= !empty($competition['cover_image']) ? 'background-image: linear-gradient(135deg, rgba(6, 95, 70, 0.8) 0%, rgba(16, 185, 129, 0.8) 100%), url(\'' . htmlspecialchars($competition['cover_image']) . '\'); background-size: cover; background-position: center;' : '' ?>">
            <div class="detail-container">
                <h1 class="detail-title"><?= htmlspecialchars($competition['title'] ?? 'Competition') ?></h1>
                <div class="detail-meta">
                    <div class="meta-item">
                        <i class="fas fa-tag"></i> <?= htmlspecialchars($competition['category'] ?? 'General') ?>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i> <?= ($competition['start_date'] ?? null) ? date('M d, Y', strtotime($competition['start_date'])) : 'TBD' ?> - <?= ($competition['end_date'] ?? null) ? date('M d, Y', strtotime($competition['end_date'])) : 'TBD' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="detail-container">
            <div class="detail-body">
                <!-- Main Content -->
                <div class="detail-content">
                    <h2 class="section-title">About This Competition</h2>
                    <div class="description-content"><?= $competition['description'] ?? '' ?></div>

                    <h2 class="section-title">Requirements</h2>
                    <ul class="requirement-list">
                        <?php if (isset($reqs['min_words'])): ?>
                            <li><i class="fas fa-check"></i> Minimum <?= number_format($reqs['min_words']) ?> words</li>
                        <?php endif; ?>
                        <?php if (isset($reqs['max_words'])): ?>
                            <li><i class="fas fa-check"></i> Maximum <?= number_format($reqs['max_words']) ?> words</li>
                        <?php endif; ?>
                        <?php if (isset($reqs['min_chapters'])): ?>
                            <li><i class="fas fa-check"></i> Minimum <?= $reqs['min_chapters'] ?> chapter(s)</li>
                        <?php endif; ?>
                        <?php if ($reqs['must_be_new'] ?? false): ?>
                            <li><i class="fas fa-check"></i> Must be a new/unpublished work</li>
                        <?php endif; ?>
                        <?php if (!($reqs['allow_nsfw'] ?? false)): ?>
                            <li><i class="fas fa-check"></i> No NSFW content allowed</li>
                        <?php else: ?>
                            <li><i class="fas fa-check"></i> NSFW content allowed</li>
                        <?php endif; ?>
                    </ul>

                    <h2 class="section-title">Entries (<?= count($entries) ?>)</h2>
                    <?php if (empty($entries)): ?>
                        <p style="color: #6b7280;">No entries yet. Be the first to join!</p>
                    <?php else: ?>
                        <div class="entries-grid">
                            <?php foreach ($entries as $entry): ?>
                                <a href="<?= site_url('/pages/book.php?id=' . intval($entry['story_id'])) ?>" style="text-decoration: none; color: inherit;">
                                    <div class="entry-card">
                                        <div class="entry-cover">
                                            <?php if ($entry['cover']): ?>
                                                <img src="<?= htmlspecialchars($entry['cover']) ?>" alt="<?= htmlspecialchars($entry['book_title']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-book"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="entry-info">
                                            <div class="entry-title"><?= htmlspecialchars(substr($entry['book_title'], 0, 25)) ?></div>
                                            <div class="entry-author">by <?= htmlspecialchars($entry['author_name']) ?></div>
                                            <div class="entry-score"><?= round($entry['total_score'] ?? 0, 1) ?> pts</div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Countdown -->
                    <div class="sidebar-card">
                        <div class="countdown">
                            ⏱️ <span id="countdown">Calculating...</span>
                        </div>
                        <?php if ($competition['status'] === 'active'): ?>
                            <?php if ($isLoggedIn): ?>
                                <a href="<?= site_url('/pages/write-story.php?competition=' . $compId) ?>" class="btn btn-primary" style="margin-bottom: 10px;">
                                    <i class="fas fa-pencil"></i> Start New Story
                                </a>
                                <button onclick="showJoinModal()" class="btn btn-secondary">
                                    <i class="fas fa-book"></i> Enter Existing Book
                                </button>
                            <?php else: ?>
                                <a href="<?= site_url('/pages/login.php') ?>" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login to Enter
                                </a>
                            <?php endif; ?>
                        <?php elseif ($competition['status'] === 'upcoming'): ?>
                            <button class="btn btn-secondary" disabled>Coming Soon</button>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Competition Ended</button>
                        <?php endif; ?>
                    </div>

                    <!-- Prizes -->
                    <div class="sidebar-card">
                        <h3 class="card-title">🏆 Prizes</h3>
                        <div class="prize-item">
                            <span class="prize-icon">💰</span>
                            <div>
                                <div class="prize-text">Cash Prize</div>
                                <div class="prize-value">$<?= number_format($prizes['cash_prize'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="prize-item">
                            <span class="prize-icon">⭐</span>
                            <div>
                                <div class="prize-text">Featured Spot</div>
                                <div class="prize-value"><?= !empty($prizes['featured']) ? 'Yes' : 'No' ?></div>
                            </div>
                        </div>
                        <div class="prize-item">
                            <span class="prize-icon">🎖️</span>
                            <div>
                                <div class="prize-text">Winner Badge</div>
                                <div class="prize-value"><?= htmlspecialchars($prizes['badge'] ?? 'None') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function updateCountdown() {
            const endDateStr = '<?= $competition['end_date'] ?? '' ?>';
            if (!endDateStr) {
                document.getElementById('countdown-container').innerHTML = '<span class="text-gray-500">Date not set</span>';
                return;
            }
            const endDate = new Date(endDateStr).getTime();
            const now = new Date().getTime();
            const distance = endDate - now;

            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const mins = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                document.getElementById('countdown').textContent = `${days}d ${hours}h ${mins}m remaining`;
            } else {
                document.getElementById('countdown').textContent = 'Ended';
            }
        }

        updateCountdown();
        setInterval(updateCountdown, 60000);
    </script>

    <!-- Join Competition Modal -->
    <?php if ($isLoggedIn && $competition['status'] === 'active'): ?>
    <div id="joinModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 700;">Enter Competition with Existing Book</h3>
                <button onclick="hideJoinModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="userBooks">Loading your books...</div>
        </div>
    </div>
    <script>
        function showJoinModal() {
            document.getElementById('joinModal').style.display = 'flex';
            loadUserBooks();
        }
        
        function hideJoinModal() {
            document.getElementById('joinModal').style.display = 'none';
        }
        
        async function loadUserBooks() {
            const container = document.getElementById('userBooks');
            try {
                const res = await fetch('<?= site_url('/api/user-stories.php') ?>');
                const books = await res.json();
                
                if (!books || books.length === 0) {
                    container.innerHTML = '<p style="color: #6b7280;">You don\'t have any published stories yet. <a href="<?= site_url('/pages/write-story.php?competition=' . $compId) ?>">Start writing one!</a></p>';
                    return;
                }
                
                let html = '<div style="display: flex; flex-direction: column; gap: 1rem;">';
                for (const book of books) {
                    html += `
                        <div style="display: flex; gap: 1rem; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; align-items: center;">
                            <img src="${book.cover || '/scrollnovels/assets/images/default-cover.jpg'}" alt="" style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">${book.title}</div>
                                <div style="font-size: 0.85rem; color: #6b7280;">${book.word_count || 0} words</div>
                            </div>
                            <button onclick="enterCompetition(${book.id})" style="background: #065f46; color: white; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer;">Enter</button>
                        </div>
                    `;
                }
                html += '</div>';
                container.innerHTML = html;
            } catch (e) {
                container.innerHTML = '<p style="color: red;">Error loading books. Please try again.</p>';
            }
        }
        
        async function enterCompetition(storyId) {
            if (!confirm('Enter this book into the competition?')) return;
            
            try {
                const res = await fetch('<?= site_url('/api/submit-competition-entry.php') ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        competition_id: <?= $compId ?>,
                        story_id: storyId
                    })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Successfully entered the competition!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || data.message || 'Could not enter competition'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
