<?php
// pages/points-dashboard.php - User points and rewards system
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

$userId = $_SESSION['user_id'];
$currentPage = 'points';

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ' . site_url('/'));
        exit;
    }
    
    // Initialize points system if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT DEFAULT 0,
        lifetime_points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT NOT NULL,
        description VARCHAR(255),
        type ENUM('earn','redeem') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Create daily quests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_quest_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quest_type VARCHAR(50) NOT NULL,
        quest_date DATE NOT NULL,
        progress INT DEFAULT 0,
        target INT DEFAULT 1,
        completed TINYINT DEFAULT 0,
        claimed TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_daily_quest (user_id, quest_type, quest_date),
        INDEX idx_user_date (user_id, quest_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Get user's current points
    $stmt = $pdo->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userPoints = $stmt->fetch();
    
    if (!$userPoints) {
        $pdo->prepare("INSERT INTO user_points (user_id, points, lifetime_points) VALUES (?, 50, 50)")
            ->execute([$userId]);
        // Seed welcome bonus transaction if none exist
        $pdo->prepare("INSERT INTO point_transactions (user_id, points, description, type) VALUES (?, 50, 'Welcome bonus', 'earn')")
            ->execute([$userId]);
        $userPoints = ['points' => 50, 'lifetime_points' => 50];
    }
    
    // Initialize daily quests for today if not exists
    $today = date('Y-m-d');
    $dailyQuests = [
        ['type' => 'daily_reading', 'name' => '📖 Daily Reading', 'target' => 1, 'points' => 10],
        ['type' => 'daily_comment', 'name' => '💬 Write a Comment', 'target' => 2, 'points' => 15],
        ['type' => 'daily_like', 'name' => '👍 Give Likes', 'target' => 5, 'points' => 12],
        ['type' => 'daily_review', 'name' => '⭐ Write a Review', 'target' => 1, 'points' => 20],
        ['type' => 'daily_post', 'name' => '📝 Post a Comment', 'target' => 3, 'points' => 18],
    ];
    
    // Create entries for today's quests if they don't exist
    foreach ($dailyQuests as $quest) {
        $stmt = $pdo->prepare("
            SELECT * FROM daily_quest_progress 
            WHERE user_id = ? AND quest_type = ? AND quest_date = ?
        ");
        $stmt->execute([$userId, $quest['type'], $today]);
        if (!$stmt->fetch()) {
            $pdo->prepare("
                INSERT INTO daily_quest_progress (user_id, quest_type, quest_date, target, progress, completed, claimed)
                VALUES (?, ?, ?, ?, 0, 0, 0)
            ")->execute([$userId, $quest['type'], $today, $quest['target']]);
        }
    }
    
    // Get today's quest progress
    $stmt = $pdo->prepare("
        SELECT * FROM daily_quest_progress 
        WHERE user_id = ? AND quest_date = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId, $today]);
    $todayQuestProgress = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    $todayQuestProgress = $todayQuestProgress[$userId] ?? [];
    
    // Create quest list with progress data
    $dailyQuestsWithProgress = [];
    foreach ($dailyQuests as $quest) {
        $progress = null;
        foreach ($todayQuestProgress as $p) {
            if ($p['quest_type'] === $quest['type']) {
                $progress = $p;
                break;
            }
        }
        if (!$progress) {
            $progress = [
                'quest_type' => $quest['type'],
                'progress' => 0,
                'target' => $quest['target'],
                'completed' => 0,
                'claimed' => 0
            ];
        }
        $dailyQuestsWithProgress[] = array_merge($quest, $progress);
    }
    
    // Get recent transactions
    $stmt = $pdo->prepare("
        SELECT * FROM point_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll();
    
    // Check actual task completion status
    // 1. Check if user has published any stories
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM stories WHERE author_id = ?");
    $stmt->execute([$userId]);
    $hasPublishedStory = ($stmt->fetch()['count'] ?? 0) > 0;
    
    // 2. Check if user has published any chapters
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chapters c JOIN stories s ON c.story_id = s.id WHERE s.author_id = ?");
    $stmt->execute([$userId]);
    $hasPublishedChapter = ($stmt->fetch()['count'] ?? 0) > 0;
    
    // 3. Check if user has written any reviews
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reviews WHERE user_id = ?");
    $stmt->execute([$userId]);
    $hasWrittenReview = ($stmt->fetch()['count'] ?? 0) > 0;
    
    // 4. Check if user has gotten 10+ likes (on stories or comments)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(likes), 0) as total_likes FROM stories WHERE author_id = ?");
    $stmt->execute([$userId]);
    $totalLikes = $stmt->fetch()['total_likes'] ?? 0;
    $hasLikes = $totalLikes >= 10;
    
    // 5. Check if user has added support links (columns are patreon, kofi)
    $stmt = $pdo->prepare("SELECT patreon, kofi FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $supportLinks = $stmt->fetch();
    $hasSupportLinks = !empty($supportLinks['patreon']) || !empty($supportLinks['kofi']);
    
    // Function to check if points were already awarded for a specific action
    function hasAwardedPoints($pdo, $userId, $actionDescription) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM point_transactions WHERE user_id = ? AND description LIKE ?");
        $stmt->execute([$userId, $actionDescription . '%']);
        return ($stmt->fetch()['count'] ?? 0) > 0;
    }
    
    // Function to award points if not already awarded
    function awardPoints($pdo, $userId, $points, $description) {
        // Check if already awarded
        if (!hasAwardedPoints($pdo, $userId, $description)) {
            // Award the points
            $pdo->prepare("INSERT INTO point_transactions (user_id, points, description, type) VALUES (?, ?, ?, 'earn')")
                ->execute([$userId, $points, $description]);
            
            // Update user's points
            $pdo->prepare("UPDATE user_points SET points = points + ?, lifetime_points = lifetime_points + ? WHERE user_id = ?")
                ->execute([$points, $points, $userId]);
                
            return true;
        }
        return false;
    }
    
    // Award points for completed tasks
    $pointsAwarded = false;
    
    if ($hasPublishedStory && !hasAwardedPoints($pdo, $userId, 'Publish a Story')) {
        awardPoints($pdo, $userId, 50, 'Publish a Story');
        $pointsAwarded = true;
    }
    
    if ($hasPublishedChapter && !hasAwardedPoints($pdo, $userId, 'Publish a Chapter')) {
        awardPoints($pdo, $userId, 25, 'Publish a Chapter');
        $pointsAwarded = true;
    }
    
    if ($hasWrittenReview && !hasAwardedPoints($pdo, $userId, 'Write a Review')) {
        awardPoints($pdo, $userId, 10, 'Write a Review');
        $pointsAwarded = true;
    }
    
    if ($hasLikes && !hasAwardedPoints($pdo, $userId, 'Get 10 Likes')) {
        awardPoints($pdo, $userId, 5, 'Get 10 Likes');
        $pointsAwarded = true;
    }
    
    if (!empty($user['bio']) && !hasAwardedPoints($pdo, $userId, 'Complete Bio')) {
        awardPoints($pdo, $userId, 15, 'Complete Bio');
        $pointsAwarded = true;
    }
    
    if (!empty($user['profile_image']) && !hasAwardedPoints($pdo, $userId, 'Add Profile Picture')) {
        awardPoints($pdo, $userId, 20, 'Add Profile Picture');
        $pointsAwarded = true;
    }
    
    if (!empty($user['is_verified']) && !hasAwardedPoints($pdo, $userId, 'Get Verified')) {
        awardPoints($pdo, $userId, 100, 'Get Verified');
        $pointsAwarded = true;
    }
    
    if ($hasSupportLinks && !hasAwardedPoints($pdo, $userId, 'Add Support Links')) {
        awardPoints($pdo, $userId, 30, 'Add Support Links');
        $pointsAwarded = true;
    }
    
    // If points were awarded, refresh the data
    if ($pointsAwarded) {
        $stmt = $pdo->prepare("SELECT * FROM user_points WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userPoints = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM point_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$userId]);
        $transactions = $stmt->fetchAll();
    }
    
    // Available point tasks with real completion status
    $pointTasks = [
        ['icon' => '✍️', 'name' => 'Publish a Story', 'points' => 50, 'action' => 'publish_story', 'completed' => $hasPublishedStory],
        ['icon' => '📖', 'name' => 'Publish a Chapter', 'points' => 25, 'action' => 'publish_chapter', 'completed' => $hasPublishedChapter],
        ['icon' => '💬', 'name' => 'Write a Review', 'points' => 10, 'action' => 'write_review', 'completed' => $hasWrittenReview],
        ['icon' => '👍', 'name' => 'Get 10 Likes', 'points' => 5, 'action' => 'get_likes', 'completed' => $hasLikes],
        ['icon' => '📝', 'name' => 'Complete Bio', 'points' => 15, 'action' => 'complete_bio', 'completed' => !empty($user['bio'])],
        ['icon' => '🖼️', 'name' => 'Add Profile Picture', 'points' => 20, 'action' => 'add_profile_pic', 'completed' => !empty($user['profile_image'])],
        ['icon' => '⭐', 'name' => 'Get Verified', 'points' => 100, 'action' => 'get_verified', 'completed' => !empty($user['is_verified'])],
        ['icon' => '💝', 'name' => 'Add Support Links', 'points' => 30, 'action' => 'add_support_links', 'completed' => $hasSupportLinks],
    ];
    
} catch (Exception $e) {
    error_log("Points dashboard error: " . $e->getMessage());
    header('Location: ' . site_url('/'));
    exit;
}

$page_title = 'Points & Rewards - Scroll Novels';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-1">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">⭐ Points & Rewards</h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">Earn points by completing tasks and supporting the community</p>
        </div>

        <!-- Points Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Current Points -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold opacity-90">Current Points</h3>
                    <span class="text-2xl">⭐</span>
                </div>
                <div class="text-4xl font-bold"><?= number_format($userPoints['points'] ?? 0) ?></div>
                <p class="text-xs opacity-75 mt-2">Available to spend</p>
            </div>

            <!-- Lifetime Points -->
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold opacity-90">Lifetime Points</h3>
                    <span class="text-2xl">🏆</span>
                </div>
                <div class="text-4xl font-bold"><?= number_format($userPoints['lifetime_points'] ?? 0) ?></div>
                <p class="text-xs opacity-75 mt-2">Total earned all time</p>
            </div>

            <!-- Rank -->
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold opacity-90">Your Rank</h3>
                    <span class="text-2xl">🎖️</span>
                </div>
                <div class="text-3xl font-bold">
                    <?php
                    $points = $userPoints['lifetime_points'] ?? 0;
                    if ($points >= 1000) echo "Legendary";
                    elseif ($points >= 500) echo "Master";
                    elseif ($points >= 250) echo "Expert";
                    elseif ($points >= 100) echo "Advanced";
                    elseif ($points >= 50) echo "Intermediate";
                    else echo "Beginner";
                    ?>
                </div>
                <p class="text-xs opacity-75 mt-2">Based on lifetime points</p>
            </div>

            <!-- Next Milestone -->
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold opacity-90">Next Milestone</h3>
                    <span class="text-2xl">🎯</span>
                </div>
                <div class="text-3xl font-bold">
                    <?php
                    $points = $userPoints['lifetime_points'] ?? 0;
                    if ($points >= 1000) echo "∞";
                    elseif ($points >= 500) echo "1000";
                    elseif ($points >= 250) echo "500";
                    elseif ($points >= 100) echo "250";
                    elseif ($points >= 50) echo "100";
                    else echo "50";
                    ?>
                </div>
                <p class="text-xs opacity-75 mt-2">Points to next rank</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Daily Quests -->
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-gray-800 dark:to-gray-800 rounded-xl shadow-lg p-8 mb-8 border-2 border-amber-200 dark:border-amber-700">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">📅 Today's Daily Quests</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($dailyQuestsWithProgress as $quest): 
                            $progress = $quest['progress'] ?? 0;
                            $target = $quest['target'] ?? 1;
                            $completed = $quest['completed'] ?? 0;
                            $claimed = $quest['claimed'] ?? 0;
                            $percent = min(100, round(($progress / $target) * 100));
                        ?>
                            <div class="p-4 rounded-lg <?= $completed ? 'bg-emerald-100 dark:bg-emerald-900/30 border-2 border-emerald-400' : 'bg-white dark:bg-gray-700 border-2 border-amber-200 dark:border-amber-700' ?>">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl"><?= $quest['name'] ?></span>
                                        <?php if ($completed): ?>
                                            <span class="px-2 py-1 bg-emerald-500 text-white text-xs rounded font-semibold">✅ Complete</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-yellow-600">+<?= $quest['points'] ?></div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">points</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Progress: <?= $progress ?>/<?= $target ?></p>
                                    <?php if ($completed && !$claimed): ?>
                                        <button onclick="claimDailyQuest('<?= $quest['type'] ?>')" class="px-3 py-1 bg-yellow-500 hover:bg-yellow-600 text-white text-xs rounded font-semibold transition">
                                            Claim Reward
                                        </button>
                                    <?php elseif ($claimed): ?>
                                        <span class="text-xs text-emerald-600 font-semibold">Reward Claimed ✓</span>
                                    <?php endif; ?>
                                </div>
                                <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-amber-400 to-orange-500 h-2 rounded-full transition-all" style="width: <?= $percent ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Available Tasks -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">📋 Available Tasks</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($pointTasks as $task): ?>
                            <div class="flex items-center justify-between p-4 rounded-lg border-2 <?= $task['completed'] 
                                ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20' 
                                : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50' ?>">
                                <div class="flex items-center gap-4">
                                    <span class="text-3xl"><?= $task['icon'] ?></span>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white"><?= $task['name'] ?></h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            <?= $task['completed'] ? '✅ Completed' : 'Not yet completed' ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-yellow-600">+<?= $task['points'] ?></div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">points</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">📊 Recent Activity</h2>
                    
                    <?php if (!empty($transactions)): ?>
                        <div class="space-y-3">
                            <?php foreach ($transactions as $transaction): ?>
                                <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($transaction['description']) ?>
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            <?= date('M d, Y H:i', strtotime($transaction['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold <?= $transaction['type'] === 'earn' ? 'text-emerald-600' : 'text-orange-600' ?>">
                                            <?= $transaction['type'] === 'earn' ? '+' : '-' ?><?= $transaction['points'] ?>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400"><?= ucfirst($transaction['type']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p>No transactions yet. Complete a task to earn your first points!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Rewards Store -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">🎁 Rewards Store</h3>
                    
                    <div class="space-y-4">
                        <!-- Badge Rewards -->
                        <div class="p-4 rounded-lg border-2 border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Verified Badge</h4>
                                <span class="text-yellow-600 font-bold">100</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">Get a ⭐ verified badge on your profile</p>
                            <button class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition <?= ($userPoints['points'] ?? 0) >= 100 ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= ($userPoints['points'] ?? 0) >= 100 ? '' : 'disabled' ?>>
                                Redeem
                            </button>
                        </div>

                        <!-- Feature Unlock -->
                        <div class="p-4 rounded-lg border-2 border-purple-300 dark:border-purple-700 bg-purple-50 dark:bg-purple-900/20">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Premium Profile</h4>
                                <span class="text-yellow-600 font-bold">250</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">Unlock advanced profile customization</p>
                            <button class="w-full px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition <?= ($userPoints['points'] ?? 0) >= 250 ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= ($userPoints['points'] ?? 0) >= 250 ? '' : 'disabled' ?>>
                                Redeem
                            </button>
                        </div>

                        <!-- Boost -->
                        <div class="p-4 rounded-lg border-2 border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-900/20">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Story Boost (24h)</h4>
                                <span class="text-yellow-600 font-bold">50</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">Get featured on homepage for 24 hours</p>
                            <button class="w-full px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm font-medium transition <?= ($userPoints['points'] ?? 0) >= 50 ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= ($userPoints['points'] ?? 0) >= 50 ? '' : 'disabled' ?>>
                                Redeem
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Buy Points -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-gray-800 dark:to-gray-800 rounded-xl shadow-lg p-6 mb-6 border-2 border-green-300 dark:border-green-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">💳 Buy More Points</h3>
                    
                    <div class="space-y-3">
                        <div class="p-3 rounded-lg bg-white dark:bg-gray-700 border border-green-200 dark:border-green-700 cursor-pointer hover:shadow-md transition" onclick="buyPoints(1, '1,100 Points', 10)">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">1,100 Points</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">110 points/$</p>
                                </div>
                                <span class="text-lg font-bold text-green-600">$10</span>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg bg-white dark:bg-gray-700 border border-green-200 dark:border-green-700 cursor-pointer hover:shadow-md transition" onclick="buyPoints(2, '3,000 Points', 25)">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">3,000 Points</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">120 points/$</p>
                                </div>
                                <span class="text-lg font-bold text-green-600">$25</span>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg bg-white dark:bg-gray-700 border border-green-200 dark:border-green-700 cursor-pointer hover:shadow-md transition" onclick="buyPoints(3, '6,500 Points', 50)">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">6,500 Points</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">130 points/$</p>
                                </div>
                                <span class="text-lg font-bold text-green-600">$50</span>
                            </div>
                        </div>
                        
                        <div class="p-3 rounded-lg bg-white dark:bg-gray-700 border border-green-200 dark:border-green-700 cursor-pointer hover:shadow-md transition" onclick="buyPoints(4, '14,000 Points', 100)">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">14,000 Points</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">140 points/$</p>
                                </div>
                                <span class="text-lg font-bold text-green-600">$100</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips -->
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border-2 border-emerald-300 dark:border-emerald-700 rounded-xl p-6">
                    <h3 class="font-bold text-emerald-900 dark:text-emerald-200 mb-3">💡 Quick Tips</h3>
                    <ul class="space-y-2 text-sm text-emerald-800 dark:text-emerald-300">
                        <li>✓ Complete your profile to earn 35 bonus points</li>
                        <li>✓ Publish stories and chapters regularly</li>
                        <li>✓ Engage with the community through reviews</li>
                        <li>✓ Set up your support links for extra points</li>
                        <li>✓ Get verified to unlock exclusive rewards</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function claimDailyQuest(questType) {
    fetch('<?= site_url('/api/claim-daily-quest.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'quest_type=' + questType
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Reward claimed! +' + data.points + ' points earned');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to claim reward'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function buyPoints(packageId, packageName, price) {
    // Redirect to points purchase page with package parameter
    window.location.href = '<?= site_url('/pages/points-purchase.php') ?>?package=' + packageId;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
