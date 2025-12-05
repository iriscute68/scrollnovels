<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Get all achievements with proper mappings and category assignment
try {
    // Map achievement titles to categories and icons
    $categoryMap = [
        'First Story' => 'Writing', 'Chapter Writer' => 'Writing', 'Book Completed' => 'Writing',
        'Popular Author' => 'Engagement', 'Viral Post' => 'Engagement', 'Critic' => 'Engagement',
        'Follower' => 'Community', 'Community Leader' => 'Community', 'Social Butterfly' => 'Community',
        'Early Bird' => 'Milestone', 'Collector' => 'Reading', 'Night Owl' => 'Milestone',
        'Comeback Kid' => 'Milestone', 'Marathon Reader' => 'Reading', 'Premium Member' => 'Milestone',
        'Contest Regular' => 'Engagement', 'First Win' => 'Engagement', 'Quality Over Quantity' => 'Writing',
        'Genre Master' => 'Writing', 'Verified Author' => 'Milestone', 'Influencer' => 'Community',
        'Serialist' => 'Writing', 'Supporter' => 'Community', 'Ambassador' => 'Community',
        'Trend Setter' => 'Engagement', 'Engagement Expert' => 'Engagement', 'Consistency' => 'Writing',
        'Speed Writer' => 'Writing', 'Comment Champion' => 'Engagement', 'Legendary' => 'Milestone'
    ];
    
    $iconMap = [
        'First Story' => '📖', 'Chapter Writer' => '✍️', 'Book Completed' => '📕',
        'Popular Author' => '⭐', 'Viral Post' => '🔥', 'Critic' => '📝',
        'Follower' => '👥', 'Community Leader' => '👑', 'Social Butterfly' => '🦋',
        'Early Bird' => '🐦', 'Collector' => '📚', 'Night Owl' => '🦉',
        'Comeback Kid' => '🔄', 'Marathon Reader' => '🏃', 'Premium Member' => '💎',
        'Contest Regular' => '⚔️', 'First Win' => '🏆', 'Quality Over Quantity' => '💯',
        'Genre Master' => '🎭', 'Verified Author' => '✅', 'Influencer' => '📢',
        'Serialist' => '🔗', 'Supporter' => '🎁', 'Ambassador' => '🌟',
        'Trend Setter' => '📈', 'Engagement Expert' => '📣', 'Consistency' => '📅',
        'Speed Writer' => '⚡', 'Comment Champion' => '💬', 'Legendary' => '🌠'
    ];
    
    $stmt = $pdo->query('SELECT id, title, description, points FROM achievements ORDER BY points ASC');
    $achievements = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $title = $row['title'];
        $achievements[] = [
            'id' => $row['id'],
            'name' => $title,
            'description' => $row['description'],
            'icon' => $iconMap[$title] ?? '🏅',
            'category' => $categoryMap[$title] ?? 'Other',
            'total' => $row['points']
        ];
    }
} catch (Exception $e) {
    $achievements = [];
}

// Get user's unlocked achievements (if logged in)
$userAchievements = [];
$achievementProgress = [];
if ($isLoggedIn && $userId) {
    try {
        $userAchievements = $pdo->query("
            SELECT achievement_id FROM user_achievements WHERE user_id = $userId
        ")->fetchAll(PDO::FETCH_COLUMN);
        $userAchievements = array_flip($userAchievements); // For faster lookup
    } catch (Exception $e) {
        $userAchievements = [];
    }
}

// Group achievements by category
$grouped = [];
foreach ($achievements as $ach) {
    $category = $ach['category'] ?? 'Other';
    if (!isset($grouped[$category])) {
        $grouped[$category] = [];
    }
    $grouped[$category][] = $ach;
}
?>
<?php
    $page_title = 'Achievements - Scroll Novels';
    // Per-page styles moved into body to avoid editing the shared head
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .achievement-card {
        transition: all 0.3s ease;
    }
    .achievement-card.locked {
        opacity: 0.6;
        grayscale: 1;
    }
    .achievement-card.unlocked:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(16, 185, 129, 0.3);
    }
    .achievement-icon {
        font-size: 3rem;
        line-height: 1;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-12">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">🏆 Achievements</h1>
        <p class="text-gray-600 dark:text-gray-400 text-lg">
            Unlock achievements as you explore Scroll Novels and engage with the community.
        </p>
    </div>

    <?php if ($isLoggedIn): ?>
        <!-- Progress Bar -->
        <?php
        $totalAchievements = count($achievements);
        $unlockedCount = count($userAchievements);
        $progressPercent = $totalAchievements > 0 ? round(($unlockedCount / $totalAchievements) * 100) : 0;
        ?>
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    Your Progress: <span class="text-emerald-600"><?php echo $unlockedCount; ?>/<?php echo $totalAchievements; ?></span>
                </span>
                <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo $progressPercent; ?>%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                <div class="bg-gradient-to-r from-emerald-500 to-green-500 h-3 rounded-full transition-all duration-500" style="width: <?php echo $progressPercent; ?>%"></div>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-8 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
            <p class="text-blue-800 dark:text-blue-300">
                📝 <a href="/pages/login.php" class="underline font-semibold">Sign in</a> to track your achievements and see your progress!
            </p>
        </div>
    <?php endif; ?>

    <!-- Achievements by Category -->
    <?php foreach ($grouped as $category => $categoryAchievements): ?>
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                <?php
                $categoryIcons = [
                    'Milestone' => '🎯',
                    'Reading' => '📚',
                    'Writing' => '✍️',
                    'Community' => '👥',
                    'Engagement' => '⭐',
                    'Other' => '🏅'
                ];
                echo ($categoryIcons[$category] ?? '🏅') . ' ' . $category;
                ?>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($categoryAchievements as $achievement): ?>
                    <?php
                    $isUnlocked = isset($userAchievements[$achievement['id']]);
                    $achievementClass = $isUnlocked ? 'unlocked' : 'locked';
                    ?>
                    <div class="achievement-card <?php echo $achievementClass; ?> bg-white dark:bg-gray-800 rounded-lg p-6 border-2 border-gray-200 dark:border-gray-700 cursor-pointer group"
                         title="<?php echo htmlspecialchars($achievement['description'] ?? $achievement['name']); ?>">

                        
                        <!-- Icon -->
                        <div class="achievement-icon mb-4 text-center">
                            <?php echo htmlspecialchars($achievement['icon']); ?>
                        </div>
                        
                        <!-- Name -->
                        <h3 class="font-bold text-gray-900 dark:text-white text-center mb-2 text-sm">
                            <?php echo htmlspecialchars($achievement['name']); ?>
                        </h3>
                        
                        <!-- Description -->
                        <p class="text-xs text-gray-600 dark:text-gray-400 text-center mb-3 line-clamp-2">
                            <?php echo htmlspecialchars($achievement['description'] ?? ''); ?>
                        </p>
                        
                        <!-- Status Badge -->
                        <div class="text-center">
                            <?php if ($isUnlocked): ?>
                                <span class="inline-block bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs font-semibold px-3 py-1 rounded-full">
                                    ✓ Unlocked
                                </span>
                            <?php else: ?>
                                <span class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 text-xs font-semibold px-3 py-1 rounded-full">
                                    🔒 Locked
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Hover Info -->
                        <div class="mt-2 text-center text-xs text-gray-500 dark:text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity">
                            Total: <?php echo $achievement['total']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Empty State -->
    <?php if (empty($achievements)): ?>
        <div class="text-center py-12">
            <p class="text-gray-500 dark:text-gray-400 text-lg">No achievements available yet.</p>
        </div>
    <?php endif; ?>

</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
    // Check and unlock achievements when page loads
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($isLoggedIn): ?>
            fetch('<?= site_url('/api/check-achievements.php') ?>')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.new_unlocks && data.new_unlocks.length > 0) {
                        // Show notification of newly unlocked achievements
                        const message = 'Congratulations! You unlocked: ' + data.new_unlocks.join(', ');
                        alert(message);
                        // Reload page to show updated achievements
                        setTimeout(() => location.reload(), 1000);
                    }
                })
                .catch(e => console.error('Achievement check failed:', e));
        <?php endif; ?>
    });
</script>

</body>
</html>

