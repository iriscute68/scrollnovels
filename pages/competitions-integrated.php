<?php
/**
 * Competitions Page - Integrated Production Version
 * Writing competitions with rankings, prizes, and timeframe selection
 */

session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/auth.php');

// Get selected timeframe
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'daily';
$validTimeframes = ['daily', 'weekly', 'monthly'];

if (!in_array($timeframe, $validTimeframes)) {
    $timeframe = 'daily';
}

// Mock competition books (integrate with database as needed)
$competitionBooks = [
    [
        'rank' => 1,
        'title' => 'The Emerald Crown',
        'author' => 'Sarah Mitchell',
        'views' => 125000,
        'likes' => 8900,
        'weeks' => 3,
        'prize' => '$5,000',
        'category' => 'Fantasy',
        'trend' => 'up'
    ],
    [
        'rank' => 2,
        'title' => 'Shadow Protocol',
        'author' => 'Emma Watson',
        'views' => 118000,
        'likes' => 8750,
        'weeks' => 2,
        'prize' => '$3,000',
        'category' => 'Thriller',
        'trend' => 'stable'
    ],
    [
        'rank' => 3,
        'title' => 'Celestial Awakening',
        'author' => 'Marcus Lee',
        'views' => 105000,
        'likes' => 7890,
        'weeks' => 4,
        'prize' => '$2,000',
        'category' => 'Sci-Fi',
        'trend' => 'down'
    ],
    [
        'rank' => 4,
        'title' => 'Hearts of Steel',
        'author' => 'Jessica Brown',
        'views' => 98500,
        'likes' => 7234,
        'weeks' => 1,
        'prize' => '$1,500',
        'category' => 'Romance',
        'trend' => 'up'
    ],
    [
        'rank' => 5,
        'title' => 'The Last Dawn',
        'author' => 'David Chen',
        'views' => 87300,
        'likes' => 6821,
        'weeks' => 5,
        'prize' => '$1,000',
        'category' => 'Adventure',
        'trend' => 'down'
    ]
];

$totalPrizePool = 50000;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Writing Competitions - Scroll Novels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #065f46;
            --primary-light: #10b981;
            --primary-lighter: #d1fae5;
            --secondary: #fbbf24;
            --background: #faf8f5;
            --surface: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --gold: #f59e0b;
            --silver: #a8a8a8;
            --bronze: #cd7f32;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 3rem 1rem;
            margin-bottom: 3rem;
            border-radius: 12px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }

        /* Timeframe Selector */
        .timeframe-selector {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .timeframe-btn {
            padding: 0.75rem 1.5rem;
            background: var(--surface);
            border: 2px solid var(--border);
            color: var(--text-primary);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .timeframe-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .timeframe-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.1);
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Rankings Container */
        .rankings-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 3rem;
        }

        .rankings-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: 2px solid var(--border);
        }

        .rankings-header h2 {
            font-size: 1.5rem;
            margin: 0;
        }

        .rankings-list {
            display: flex;
            flex-direction: column;
        }

        .ranking-item {
            display: grid;
            grid-template-columns: 80px 1fr 1fr 150px;
            gap: 1.5rem;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .ranking-item:hover {
            background: var(--background);
        }

        .ranking-item:last-child {
            border-bottom: none;
        }

        .rank-badge {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .rank-badge.rank-1 {
            color: var(--gold);
        }

        .rank-badge.rank-2 {
            color: var(--silver);
        }

        .rank-badge.rank-3 {
            color: var(--bronze);
        }

        .rank-number {
            font-size: 1rem;
            color: var(--text-primary);
        }

        .book-info h3 {
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .book-info h3 a {
            color: var(--text-primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .book-info h3 a:hover {
            color: var(--primary);
        }

        .book-meta {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .book-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .book-stats span {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .book-stats strong {
            color: var(--primary);
            font-weight: 700;
        }

        .prize-section {
            text-align: right;
        }

        .prize {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        .text-center {
            text-align: center;
            margin-top: 2rem;
        }

        /* Trend Indicator */
        .trend {
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .trend.up {
            color: #22c55e;
        }

        .trend.down {
            color: #ef4444;
        }

        .trend.stable {
            color: var(--text-secondary);
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            border-top: 1px solid var(--border);
            margin-top: 3rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .ranking-item {
                grid-template-columns: 70px 1fr 1fr;
                gap: 1rem;
            }

            .prize-section {
                grid-column: 1 / -1;
                text-align: left;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .ranking-item {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }

            .rank-badge {
                justify-content: space-between;
            }

            .book-info,
            .prize-section {
                grid-column: 1 / -1;
            }

            .prize-section {
                text-align: left;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 1rem;
                border-top: 1px solid var(--border);
            }

            .timeframe-selector {
                flex-direction: column;
            }

            .timeframe-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 2rem 1rem;
                margin-bottom: 2rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .page-header p {
                font-size: 0.95rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .ranking-item {
                padding: 1rem;
            }

            .rank-badge {
                font-size: 1rem;
            }

            .book-stats {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>🏆 Writing Competitions</h1>
            <p>Compete with other writers and win amazing prizes. Submit your best work to compete in ongoing competitions.</p>
        </div>

        <!-- Timeframe Selector -->
        <div class="timeframe-selector">
            <?php foreach ($validTimeframes as $period): ?>
                <a href="?timeframe=<?php echo $period; ?>" 
                   class="timeframe-btn <?php echo $timeframe === $period ? 'active' : ''; ?>">
                    <?php echo ucfirst($period); ?> Rankings
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <p class="stat-label">Total Prize Pool (<?php echo ucfirst($timeframe); ?>)</p>
                <p class="stat-value">$<?php echo number_format($totalPrizePool); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <p class="stat-label">Participating Books</p>
                <p class="stat-value">1,247</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚡</div>
                <p class="stat-label">Active Competitions</p>
                <p class="stat-value">24</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <p class="stat-label">Competing Authors</p>
                <p class="stat-value">3,450</p>
            </div>
        </div>

        <!-- Rankings Table -->
        <div class="rankings-container">
            <div class="rankings-header">
                <h2>Top Rankings - <?php echo ucfirst($timeframe); ?></h2>
            </div>

            <div class="rankings-list">
                <?php foreach ($competitionBooks as $book): ?>
                    <div class="ranking-item">
                        <div class="rank-badge rank-<?php echo $book['rank']; ?>">
                            <?php
                            if ($book['rank'] === 1) echo '🥇';
                            elseif ($book['rank'] === 2) echo '🥈';
                            elseif ($book['rank'] === 3) echo '🥉';
                            else echo '#' . $book['rank'];
                            ?>
                            <span class="rank-number">#<?php echo $book['rank']; ?></span>
                        </div>

                        <div class="book-info">
                            <h3><a href="#" onclick="alert('Opening: ' + '<?php echo htmlspecialchars($book['title']); ?>')"><?php echo htmlspecialchars($book['title']); ?></a></h3>
                            <p class="book-meta">by <?php echo htmlspecialchars($book['author']); ?> • <?php echo $book['category']; ?></p>
                            <div class="book-stats">
                                <span><strong><?php echo number_format($book['views']); ?></strong> views</span>
                                <span><strong><?php echo number_format($book['likes']); ?></strong> likes</span>
                                <span><strong><?php echo $book['weeks']; ?></strong> weeks in competition</span>
                            </div>
                            <div class="trend <?php echo $book['trend']; ?>">
                                <?php
                                if ($book['trend'] === 'up') echo '📈 Trending Up';
                                elseif ($book['trend'] === 'down') echo '📉 Trending Down';
                                else echo '➡️ Stable';
                                ?>
                            </div>
                        </div>

                        <div class="prize-section">
                            <p class="prize"><?php echo $book['prize']; ?></p>
                            <button class="btn btn-primary" onclick="alert('Redirecting to book: ' + '<?php echo htmlspecialchars($book['title']); ?>')">
                                📖 View Book
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-center">
            <button class="btn btn-primary btn-large" onclick="alert('Redirecting to write story page...')">
                ✍️ Start Your Competition Now
            </button>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Scroll Novels Competitions. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Handle ranking item interactions
            const rankingItems = document.querySelectorAll('.ranking-item');
            rankingItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (!e.target.closest('.btn')) {
                        const title = this.querySelector('h3 a').textContent;
                        console.log('Viewing ranking item:', title);
                    }
                });
            });

            // Log page loaded
            console.log('Competitions page loaded');
        });
    </script>
</body>
</html>
