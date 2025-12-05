<?php
/**
 * ACHIEVEMENTS & POINTS DASHBOARD
 * User-facing page displaying achievements, points, and comments
 */

require_once dirname(__FILE__) . '/../includes/auth.php';
include dirname(__FILE__) . '/../includes/header.php';

$userId = $_SESSION['user_id'] ?? 1;
$currentPage = $_GET['page'] ?? 'achievements';

// Mock user data
$userData = [
    'id' => $userId,
    'name' => 'Alex Reader',
    'balance' => 2850,
    'totalEarned' => 5420,
    'totalSpent' => 2570,
];

// Mock achievements
$achievements = [
    ['id' => 1, 'name' => 'First Comment', 'icon' => '💬', 'description' => 'Leave your first comment', 'points' => 50, 'earned' => true, 'earned_at' => '2024-01-15'],
    ['id' => 2, 'name' => 'Comment King', 'icon' => '👑', 'description' => 'Leave 100 comments', 'points' => 200, 'earned' => true, 'earned_at' => '2024-02-20'],
    ['id' => 3, 'name' => 'Reviewer I', 'icon' => '⭐', 'description' => 'Leave your first review', 'points' => 50, 'earned' => false],
    ['id' => 4, 'name' => 'First Supporter', 'icon' => '💝', 'description' => 'Support a book with 50 points', 'points' => 100, 'earned' => false],
    ['id' => 5, 'name' => 'Prolific Author', 'icon' => '✍️', 'description' => 'Publish 20 chapters', 'points' => 500, 'earned' => false],
];

// Mock comments
$comments = [
    ['id' => 1, 'author' => 'John Doe', 'time' => '2 hours ago', 'content' => 'This chapter was absolutely amazing! The character development is incredible.', 'likes' => 24],
    ['id' => 2, 'author' => 'Sarah Smith', 'time' => '5 hours ago', 'content' => "Can't wait for the next update. This story has me hooked!", 'likes' => 18],
    ['id' => 3, 'author' => 'Mike Johnson', 'time' => '1 day ago', 'content' => "One of the best fantasy novels I've read in years!", 'likes' => 42],
];

// Mock leaderboard
$leaderboard = [
    ['rank' => 1, 'name' => 'Dragon Slayer', 'points' => 8950, 'badge' => '🥇'],
    ['rank' => 2, 'name' => 'Book Lover', 'points' => 7620, 'badge' => '🥈'],
    ['rank' => 3, 'name' => 'Story Seeker', 'points' => 6840, 'badge' => '🥉'],
    ['rank' => 4, 'name' => 'Alex Reader', 'points' => 5420, 'badge' => '4️⃣'],
];
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;" data-notification-container>

    <!-- POINTS DISPLAY -->
    <div class="points-container">
        <div class="points-display">
            <div class="points-stat">
                <div class="points-label">💰 Current Balance</div>
                <div class="points-value balance"><?= number_format($userData['balance']) ?></div>
            </div>
            <div class="points-stat">
                <div class="points-label">📈 Total Earned</div>
                <div class="points-value earned-total"><?= number_format($userData['totalEarned']) ?></div>
            </div>
            <div class="points-stat">
                <div class="points-label">💸 Total Spent</div>
                <div class="points-value" style="color: #fed7aa;"><?= number_format($userData['totalSpent']) ?></div>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div style="display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid #334155; padding-bottom: 12px; flex-wrap: wrap;">
        <button class="tab-btn" onclick="switchTab('achievements')" 
                style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 8px 16px; font-weight: 600; <?= $currentPage == 'achievements' ? 'color: #10b981; border-bottom: 3px solid #10b981;' : '' ?>">
            🏆 Achievements
        </button>
        <button class="tab-btn" onclick="switchTab('comments')" 
                style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 8px 16px; font-weight: 600; <?= $currentPage == 'comments' ? 'color: #10b981; border-bottom: 3px solid #10b981;' : '' ?>">
            💬 Comments
        </button>
        <button class="tab-btn" onclick="switchTab('leaderboard')" 
                style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 8px 16px; font-weight: 600; <?= $currentPage == 'leaderboard' ? 'color: #10b981; border-bottom: 3px solid #10b981;' : '' ?>">
            📊 Leaderboard
        </button>
    </div>

    <!-- ACHIEVEMENTS TAB -->
    <div id="achievements-tab" style="display: <?= $currentPage == 'achievements' ? 'block' : 'none' ?>;">
        <div class="section-title">🏆 Your Achievements</div>
        <div class="achievements-grid">
            <?php foreach($achievements as $achievement): ?>
                <div class="achievement-card <?= $achievement['earned'] ? 'earned' : '' ?>">
                    <div class="earned-badge">🏆</div>
                    <div class="achievement-icon"><?= $achievement['icon'] ?></div>
                    <div class="achievement-name"><?= $achievement['name'] ?></div>
                    <div class="achievement-description"><?= $achievement['description'] ?></div>
                    <div class="achievement-points">+<?= $achievement['points'] ?> pts</div>
                    <div class="achievement-status <?= $achievement['earned'] ? 'earned' : 'locked' ?>">
                        <?php if($achievement['earned']): ?>
                            ✓ Earned on <?= $achievement['earned_at'] ?>
                        <?php else: ?>
                            Locked
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- COMMENTS TAB -->
    <div id="comments-tab" style="display: <?= $currentPage == 'comments' ? 'block' : 'none' ?>;">
        <div class="comments-section">
            <div class="comments-header">
                <div class="section-title" style="margin: 0;">💬 Recent Comments</div>
                <div class="comments-count"><?= count($comments) ?> comments</div>
            </div>

            <!-- COMMENT FORM -->
            <div class="comment-form">
                <textarea class="comment-textarea" placeholder="Share your thoughts... (0/5000)" data-post-id="1"></textarea>
                <div class="comment-actions">
                    <span class="char-count">0/5000</span>
                    <button class="comment-btn" disabled>Post Comment</button>
                </div>
            </div>

            <!-- COMMENTS LIST -->
            <div class="comments-list">
                <?php foreach($comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <span class="comment-author"><?= htmlspecialchars($comment['author']) ?></span>
                            <span class="comment-time"><?= $comment['time'] ?></span>
                        </div>
                        <div class="comment-content"><?= htmlspecialchars($comment['content']) ?></div>
                        <div style="display: flex; gap: 16px; padding-top: 12px; border-top: 1px solid #334155;">
                            <button style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 12px; font-weight: 600;">
                                ❤️ Like (<?= $comment['likes'] ?>)
                            </button>
                            <button style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 12px; font-weight: 600;">
                                💬 Reply
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- LEADERBOARD TAB -->
    <div id="leaderboard-tab" style="display: <?= $currentPage == 'leaderboard' ? 'block' : 'none' ?>;">
        <div class="leaderboard-container">
            <div class="leaderboard-header">📊 Top Supporters</div>
            <div class="leaderboard-list">
                <?php foreach($leaderboard as $user): ?>
                    <div class="leaderboard-item">
                        <div class="leaderboard-rank">
                            <div class="rank-badge"><?= $user['badge'] ?></div>
                            <div class="leaderboard-user">
                                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="user-subtitle">#<?= $user['rank'] ?> Supporter</div>
                            </div>
                        </div>
                        <div class="leaderboard-points"><?= number_format($user['points']) ?> pts</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<link rel="stylesheet" href="/css/achievements-system.css">
<script src="/js/achievements-system.js" data-user-id="<?= htmlspecialchars($userId) ?>" data-user-name="Alex Reader"></script>
<script>
    function switchTab(tab) {
        document.querySelectorAll('[id$="-tab"]').forEach(el => el.style.display = 'none');
        document.getElementById(tab + '-tab').style.display = 'block';
    }
</script>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?>

