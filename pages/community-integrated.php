<?php
/**
 * Community Page - Integrated Production Version
 * Discussion forum with categories, topics, and community interaction
 */

session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/auth.php');

// Get active category
$active_category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'All Discussions';

// Fetch community topics from database
$community_topics = [];
try {
    // Query forum topics from database
    $query = "SELECT 
        ft.id, 
        ft.title, 
        u.username as author, 
        DATE_FORMAT(ft.created_at, '%M %d, %Y') as date,
        COALESCE((SELECT COUNT(*) FROM forum_posts WHERE topic_id = ft.id), 0) as replies,
        COALESCE(ft.views, 0) as views,
        COALESCE(fc.name, 'General Chat') as category,
        SUBSTRING(ft.description, 1, 200) as preview
    FROM forum_topics ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.status = 'active'";
    
    $params = [];
    
    // Filter by category if not "All Discussions"
    if ($active_category !== 'All Discussions') {
        $query .= " AND COALESCE(fc.name, 'General Chat') = ?";
        $params[] = $active_category;
    }
    
    $query .= " ORDER BY ft.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $community_topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Forum topics query error: ' . $e->getMessage());
    $community_topics = [];
}

// Get categories from database
$categories = ['All Discussions'];
try {
    $stmt = $pdo->prepare("SELECT name FROM forum_categories ORDER BY name ASC");
    $stmt->execute();
    $db_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($db_categories)) {
        $categories = array_merge($categories, $db_categories);
    } else {
        // Fallback to default categories if none in database
        $categories = ['All Discussions', 'Writing Discussion', 'Help & Advice', 'Celebrations', 'Off-Topic', 'Contests & Challenges'];
    }
} catch (Exception $e) {
    // Use default categories on error
    $categories = ['All Discussions', 'Writing Discussion', 'Help & Advice', 'Celebrations', 'Off-Topic', 'Contests & Challenges'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community - Scroll Novels</title>
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
            --danger: #ef4444;
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

        /* Header */
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
        }

        .header-action {
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--secondary);
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(251, 191, 36, 0.4);
        }

        /* Main Layout */
        .community-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Sidebar */
        .community-sidebar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .community-sidebar h3 {
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .category-list {
            list-style: none;
        }

        .category-list li {
            margin-bottom: 0.5rem;
        }

        .category-list a {
            display: block;
            padding: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .category-list a:hover {
            background: var(--primary-lighter);
            color: var(--primary);
        }

        .category-list a.active {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        /* Main Content */
        .community-main {
            flex: 1;
        }

        .discussions-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .discussion-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .discussion-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.1);
            transform: translateY(-2px);
        }

        .discussion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .category-tag {
            background: var(--primary-lighter);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        .timestamp {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .discussion-card h3 {
            margin: 0.5rem 0 1rem 0;
            font-size: 1.25rem;
            line-height: 1.4;
        }

        .discussion-card h3 a {
            color: var(--text-primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .discussion-card h3 a:hover {
            color: var(--primary);
        }

        .discussion-preview {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .discussion-stats {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .discussion-stats span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-value {
            font-weight: 600;
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .community-layout {
                grid-template-columns: 1fr;
            }

            .community-sidebar {
                position: static;
                display: flex;
                gap: 1rem;
                padding: 1rem;
                overflow-x: auto;
            }

            .community-sidebar h3 {
                display: none;
            }

            .category-list {
                display: flex;
                gap: 0.5rem;
            }

            .category-list li {
                margin: 0;
                white-space: nowrap;
            }

            .category-list a {
                padding: 0.5rem 1rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .discussion-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .discussion-stats {
                flex-direction: column;
                gap: 0.5rem;
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

            .discussion-card {
                padding: 1rem;
            }

            .discussion-card h3 {
                font-size: 1.1rem;
            }

            .community-sidebar {
                flex-direction: column;
                gap: 0;
                padding: 0;
                border: none;
                background: transparent;
            }

            .category-list {
                flex-direction: column;
            }

            .category-list a {
                border-bottom: 1px solid var(--border);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>💬 Community Forum</h1>
            <p>Connect with writers, share stories, and discuss all things related to storytelling</p>
            <div class="header-action">
                <button class="btn btn-primary" onclick="alert('Redirecting to create new discussion...')">
                    + Start New Discussion
                </button>
            </div>
        </div>

        <!-- Main Layout -->
        <div class="community-layout">
            <!-- Sidebar -->
            <aside class="community-sidebar">
                <h3>Categories</h3>
                <ul class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="?category=<?php echo urlencode($category); ?>" 
                               class="<?php echo $active_category === $category ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="community-main">
                <div class="discussions-list">
                    <?php if (!empty($community_topics)): ?>
                        <?php foreach ($community_topics as $topic): ?>
                            <div class="discussion-card">
                                <div class="discussion-header">
                                    <span class="category-tag"><?php echo htmlspecialchars($topic['category']); ?></span>
                                    <span class="timestamp"><?php echo $topic['date']; ?></span>
                                </div>
                                <h3>
                                    <a href="#" onclick="alert('Opening discussion: ' + '<?php echo htmlspecialchars($topic['title']); ?>')">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                </h3>
                                <p class="discussion-preview">
                                    <?php echo htmlspecialchars(substr($topic['preview'], 0, 200)); ?>...
                                </p>
                                <div class="discussion-stats">
                                    <span>
                                        👤 by <strong><?php echo htmlspecialchars($topic['author']); ?></strong>
                                    </span>
                                    <span>
                                        💬 <span class="stat-value"><?php echo $topic['replies']; ?></span> replies
                                    </span>
                                    <span>
                                        👁️ <span class="stat-value"><?php echo number_format($topic['views']); ?></span> views
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>No discussions found</h3>
                            <p>Be the first to start a discussion in this category!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <footer style="text-align: center; padding: 2rem; color: var(--text-secondary); border-top: 1px solid var(--border); margin-top: 3rem;">
        <p>&copy; 2025 Scroll Novels Community. All rights reserved.</p>
    </footer>
</body>
</html>
