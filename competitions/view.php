<?php
// competitions/view.php - Competition detail with leaderboard
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$compId = intval($_GET['id'] ?? 0);

if (!$compId) {
    header('Location: ' . SITE_URL . '/pages/competitions.php');
    exit;
}

// Get competition
try {
    $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
    $stmt->execute([$compId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$competition) {
        throw new Exception('Competition not found');
    }
    
    // Get entered books
    $stmt = $pdo->prepare("
        SELECT DISTINCT ce.story_id, s.title, s.author_id, u.username,
               COUNT(*) as views,
               (SELECT COUNT(*) FROM reviews WHERE story_id = s.id) as reader_count
        FROM competition_entries ce
        JOIN stories s ON ce.story_id = s.id
        JOIN users u ON s.author_id = u.id
        WHERE ce.competition_id = ?
        GROUP BY s.id
        ORDER BY views DESC
        LIMIT 10
    ");
    $stmt->execute([$compId]);
    $topBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT story_id) as total_entries,
            COUNT(DISTINCT author_id) as unique_authors
        FROM competition_entries
        WHERE competition_id = ?
    ");
    $stmt->execute([$compId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}

function getStatus($start, $end) {
    $now = date('Y-m-d H:i:s');
    if ($start <= $now && $end >= $now) return 'active';
    if ($start > $now) return 'upcoming';
    return 'closed';
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-800">
    
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-700 dark:to-teal-700 text-white py-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-5xl"><?= $competition['icon'] ?? '📝' ?></span>
                <div>
                    <h1 class="text-4xl font-bold"><?= htmlspecialchars($competition['title']) ?></h1>
                    <p class="text-emerald-100 mt-1">Writing Competition</p>
                </div>
            </div>
            <p class="text-emerald-50 max-w-2xl"><?= htmlspecialchars($competition['description']) ?></p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-12">
        
        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Info & Prize -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Prize Pool Card -->
                <div class="bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-900/30 dark:to-yellow-900/30 border-2 border-amber-200 dark:border-amber-700 rounded-lg p-8">
                    <h2 class="text-3xl font-bold text-amber-600 dark:text-amber-400 mb-2">Prize Pool</h2>
                    <div class="flex items-baseline gap-2">
                        <span class="text-5xl font-bold text-amber-700 dark:text-amber-300">
                            <?= !empty($competition['prize']) ? htmlspecialchars($competition['prize']) : 'TBA' ?>
                        </span>
                        <?php if (!empty($competition['prize'])): ?>
                            <span class="text-amber-600 dark:text-amber-400">in rewards</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status & Action -->
                <?php $status = getStatus($competition['start_date'], $competition['end_date']); ?>
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <span class="px-4 py-2 rounded-full font-bold text-sm 
                            <?= $status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 
                                ($status === 'upcoming' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 
                                'bg-gray-100 text:gray-700 dark:bg-gray-700 dark:text-gray-300') ?>">
                                <?= match($status) {
                                    'active' => '🟢 Currently Active',
                                    'upcoming' => '🔵 Coming Soon',
                                    'closed' => '⚫ Competition Ended'
                                } ?>
                            </span>
                        </div>
                        <?php if ($status === 'active' && $isLoggedIn): ?>
                            <button onclick="enterCompetition(<?= $compId ?>)" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-md transition">
                                ✍️ Enter Competition
                            </button>
                        <?php elseif ($status === 'upcoming' && $isLoggedIn): ?>
                            <button disabled class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg font-bold cursor-not-allowed">
                                🔔 Notify Me
                            </button>
                        <?php elseif (!$isLoggedIn): ?>
                            <a href="<?= SITE_URL ?>/auth/login.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold shadow-md transition">
                                Sign In to Enter
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Timeline -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📅</span>
                            <div>
                                <div class="text-sm text-slate-600 dark:text-slate-400">Start Date</div>
                                <div class="font-semibold text-slate-900 dark:text-white"><?= date('F d, Y \a\t h:i A', strtotime($competition['start_date'])) ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xl">🏁</span>
                            <div>
                                <div class="text-sm text-slate-600 dark:text-slate-400">End Date</div>
                                <div class="font-semibold text-slate-900 dark:text-white"><?= date('F d, Y \a\t h:i A', strtotime($competition['end_date'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-6 text-center shadow-md">
                        <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400"><?= $stats['total_entries'] ?? 0 ?></div>
                        <div class="text-sm text-slate-600 dark:text-slate-400 mt-2">Books Entered</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-6 text-center shadow-md">
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?= $stats['unique_authors'] ?? 0 ?></div>
                        <div class="text-sm text-slate-600 dark:text-slate-400 mt-2">Authors</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-6 text-center shadow-md">
                        <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">
                            <?= !empty($competition['rating']) ? round($competition['rating'], 1) : '5.0' ?>
                        </div>
                        <div class="text-sm text-slate-600 dark:text-slate-400 mt-2">Rating</div>
                    </div>
                </div>

                <!-- Rules -->
                <?php if (!empty($competition['rules'])): ?>
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">📋 Competition Rules</h3>
                        <div class="prose dark:prose-invert max-w-none text-slate-700 dark:text-slate-300">
                            <?= nl2br(htmlspecialchars($competition['rules'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Leaderboard -->
            <div class="space-y-6">
                
                <!-- Leaderboard Header -->
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 dark:from-purple-700 dark:to-pink-700 text-white rounded-lg p-6 shadow-md">
                    <h3 class="text-2xl font-bold">🏆 Top Books</h3>
                    <p class="text-purple-100 text-sm mt-1">Performance Ranking</p>
                </div>

                <!-- Leaderboard Cards -->
                <?php if (!empty($topBooks)): ?>
                    <div class="space-y-3">
                        <?php foreach ($topBooks as $idx => $book): ?>
                            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden hover:shadow-lg transition">
                                <!-- Medal -->
                                <div class="h-1 bg-gradient-to-r <?= match($idx) {
                                    0 => 'from-yellow-400 to-yellow-500',
                                    1 => 'from-gray-300 to-gray-400',
                                    2 => 'from-orange-400 to-orange-500',
                                    default => 'from-slate-300 to-slate-400'
                                } ?>"></div>

                                <div class="p-4">
                                    <!-- Rank & Medal -->
                                    <div class="flex items-start gap-3 mb-3">
                                        <div class="text-3xl font-bold text-slate-300 dark:text-slate-600 w-8 text-center">
                                            <?= ['🥇', '🥈', '🥉'][$idx] ?? ($idx + 1) ?>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-slate-900 dark:text-white line-clamp-2">
                                                <?= htmlspecialchars($book['title']) ?>
                                            </h4>
                                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                                by <?= htmlspecialchars($book['username']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Stats -->
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded p-2 text-center">
                                            <div class="font-bold text-blue-700 dark:text-blue-300"><?= $book['views'] ?? 0 ?></div>
                                            <div class="text-xs text-slate-600 dark:text-slate-400">Views</div>
                                        </div>
                                        <div class="bg-pink-50 dark:bg-pink-900/30 rounded p-2 text-center">
                                            <div class="font-bold text-pink-700 dark:text-pink-300"><?= $book['reader_count'] ?? 0 ?></div>
                                            <div class="text-xs text-slate-600 dark:text-slate-400">Readers</div>
                                        </div>
                                    </div>

                                    <!-- View Button -->
                                    <a href="<?= SITE_URL ?>/pages/book.php?id=<?= $book['story_id'] ?>" 
                                       class="mt-3 block w-full text-center px-3 py-2 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded font-semibold text-sm hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition">
                                        View Book →
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-100 dark:bg-slate-700/50 rounded-lg p-6 text-center">
                        <div class="text-2xl mb-2">📚</div>
                        <p class="text-slate-700 dark:text-slate-300 font-semibold">No books entered yet</p>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Be the first to enter!</p>
                    </div>
                <?php endif; ?>

                <!-- View All Button -->
                <?php if (count($topBooks) >= 10): ?>
                    <a href="?id=<?= $compId ?>&tab=entries" 
                       class="block w-full px-4 py-3 text-center bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg font-semibold transition">
                        View All Entries →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function enterCompetition(id) {
    alert('Feature to implement: Contest entry form');
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
