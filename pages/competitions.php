<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

$filter = $_GET['filter'] ?? 'all';
$competitions = [];
$now = date('Y-m-d H:i:s');

try {
    $query = "SELECT * FROM competitions WHERE status = 'published'";
    $params = [];
    
    // Filter based on dates
    if ($filter === 'active') {
        // Active: start date in past, end date in future, AND published
        $query .= " AND start_date <= ? AND end_date >= ?";
        $params = [$now, $now];
    } elseif ($filter === 'upcoming') {
        // Upcoming: start date in future, AND published
        $query .= " AND start_date > ?";
        $params = [$now];
    } elseif ($filter === 'ended') {
        // Ended: end date in past, AND published
        $query .= " AND end_date < ?";
        $params = [$now];
    }
    // 'all' filter shows everything published
    
    $query .= " ORDER BY start_date DESC LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $competitions = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching competitions: ' . $e->getMessage());
    $competitions = [];
}

function getStatusBadge($status) {
    if ($status === 'active') return '<span class="status-active">🟢 Active</span>';
    if ($status === 'upcoming') return '<span class="status-upcoming">🔵 Upcoming</span>';
    return '<span class="status-ended">⚪ Ended</span>';
}

function getEntryCount($compId) {
    global $pdo;
    try {
        // Count entries excluding disqualified ones (DB enum: 'active','disqualified','completed')
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM competition_entries WHERE competition_id = ? AND status != 'disqualified'");
        $stmt->execute([$compId]);
        return $stmt->fetch()['cnt'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Writing Competitions - Scroll Novels</title>
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
        }

        .comp-header {
            background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
            color: white;
            padding: 1.5rem 1rem;
            text-align: center;
            margin-bottom: 2rem;
            margin-top: 0;
            border-radius: 12px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .comp-header h1 {
            font-size: 2rem;
            margin-bottom: 0.25rem;
            font-weight: 700;
            color: #ffffff;
        }

        .comp-header p {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
            margin: 0;
        }

        .comp-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid #e5e7eb;
            background: white;
            color: #1f2937;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }

        .filter-tab:hover {
            border-color: #065f46;
            color: #065f46;
        }

        .filter-tab.active {
            background: #065f46;
            color: white;
            border-color: #065f46;
        }

        .comp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .comp-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .comp-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .comp-banner {
            height: 200px;
            background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            position: relative;
        }

        .comp-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: white;
        }

        .status-active {
            background: #10b981;
            color: white;
        }

        .status-upcoming {
            background: #3b82f6;
            color: white;
        }

        .status-ended {
            background: #9ca3af;
            color: white;
        }

        .comp-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .comp-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #065f46;
        }

        .comp-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.8rem;
            background: #f3f4f6;
            border-radius: 20px;
            color: #6b7280;
        }

        .meta-tag i {
            color: #065f46;
        }

        .comp-desc {
            color: #6b7280;
            margin-bottom: 1rem;
            flex: 1;
            font-size: 0.95rem;
        }

        .comp-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem;
            background: #faf8f5;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .stat-num {
            font-size: 1.3rem;
            font-weight: 700;
            color: #065f46;
        }

        .stat-label {
            color: #9ca3af;
            font-size: 0.8rem;
        }

        .comp-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            flex: 1;
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
            .comp-header h1 {
                font-size: 2rem;
            }

            .comp-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
                color: #e0e0e0;
            }

            .comp-card {
                background: #2d2d2d;
                border-color: #404040;
            }

            .filter-tab {
                background: #2d2d2d;
                border-color: #404040;
                color: #e0e0e0;
            }

            .comp-meta {
                color: #a0a0a0;
            }

            .meta-tag {
                background: rgba(255, 255, 255, 0.1);
                color: #a0a0a0;
            }

            .comp-desc {
                color: #a0a0a0;
            }

            .comp-stats {
                background: rgba(255, 255, 255, 0.05);
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="comp-header">
            <h1>🏆 Writing Competitions</h1>
            <p>Compete for amazing prizes and recognition</p>
        </div>

        <div class="comp-container">
            <!-- Competitions Grid -->
            <div class="comp-grid">
                <?php foreach ($competitions as $comp):
                    $prizeInfo = is_array($comp['prize_info'] ?? null) ? $comp['prize_info'] : json_decode($comp['prize_info'] ?? '{}', true);
                    $entryCount = getEntryCount($comp['id'] ?? $comp['id']);
                    
                    // Calculate status dynamically based on dates
                    $nowDt = new DateTime();
                    $startDt = new DateTime($comp['start_date'] ?? 'now');
                    $endDt = new DateTime($comp['end_date'] ?? 'now');
                    
                    if ($nowDt < $startDt) {
                        $compStatus = 'upcoming';
                    } elseif ($nowDt > $endDt) {
                        $compStatus = 'ended';
                    } else {
                        $compStatus = 'active';
                    }
                ?>
                    <div class="comp-card">
                        <div class="comp-banner">
                            <span class="status-badge <?= $compStatus === 'active' ? 'status-active' : ($compStatus === 'upcoming' ? 'status-upcoming' : 'status-ended') ?>">
                                <?= $compStatus === 'active' ? '🟢 Active' : ($compStatus === 'upcoming' ? '🔵 Upcoming' : '⚪ Ended') ?>
                            </span>
                            📝
                        </div>

                        <div class="comp-body">
                            <h3 class="comp-title"><?= htmlspecialchars($comp['title'] ?? 'Untitled') ?></h3>

                            <div class="comp-meta">
                                <?php if (!empty($comp['category'])): ?>
                                    <span class="meta-tag"><i class="fas fa-tag"></i> <?= htmlspecialchars($comp['category']) ?></span>
                                <?php endif; ?>
                                <?php if (isset($prizeInfo['cash_prize']) && $prizeInfo['cash_prize'] > 0): ?>
                                    <span class="meta-tag"><i class="fas fa-money-bill"></i> $<?= number_format($prizeInfo['cash_prize']) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="comp-desc">
                                <?= htmlspecialchars(substr(strip_tags($comp['description'] ?? ''), 0, 120)) ?>...
                            </p>

                            <div class="comp-stats">
                                <div>
                                    <div class="stat-num"><?= $entryCount ?></div>
                                    <div class="stat-label">Entries</div>
                                </div>
                                <div>
                                    <div class="stat-num">0</div>
                                    <div class="stat-label">Votes</div>
                                </div>
                                <div>
                                    <div class="stat-num">4.8</div>
                                    <div class="stat-label">Rating</div>
                                </div>
                            </div>

                            <div class="comp-actions">
                                <a href="<?= site_url('/pages/competition-details.php?id=' . $comp['id']) ?>" class="btn btn-secondary">View Details</a>
                                <?php if ($compStatus === 'active' && $isLoggedIn): ?>
                                    <a href="<?= site_url('/pages/write-story.php?competition=' . $comp['id']) ?>" class="btn btn-primary">Join</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

