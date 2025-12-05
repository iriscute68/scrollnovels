<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Get the PDO connection
$pdo = isset($pdo) ? $pdo : (function() {
    try {
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        error_log('Database connection error: ' . $e->getMessage());
        return null;
    }
})();

$isLoggedIn = !empty($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'User';
$isAdmin = false;

// Determine if current user is an admin (admin_level >= 2)
if ($pdo && $isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $isAdmin = $user && isset($user['admin_level']) && (int)$user['admin_level'] >= 2;
    } catch (Exception $e) {
        error_log('Failed to fetch admin level: ' . $e->getMessage());
    }
}

// Ensure guides table exists
if ($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS guide_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(255) UNIQUE NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                content LONGTEXT,
                order_index INT DEFAULT 0,
                published TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    } catch (Exception $e) {
        error_log('Error creating guides table: ' . $e->getMessage());
    }
}

// Fetch all published guides
$guides = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                slug,
                title,
                description,
                content,
                order_index,
                created_at,
                updated_at
            FROM guide_pages
            WHERE published = 1
            ORDER BY order_index ASC, created_at DESC
        ");
        $stmt->execute();
        $guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Failed to fetch guides: ' . $e->getMessage());
    }
}

// Backwards compatibility: if admin used older `guides` table, load from there
if (empty($guides) && $pdo) {
    try {
        $oldStmt = $pdo->prepare("SELECT id, slug, title, content, created_at, updated_at, status FROM guides WHERE status = 'published' ORDER BY updated_at DESC");
        $oldStmt->execute();
        $oldGuides = $oldStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($oldGuides)) {
            $guides = array_map(function($g) {
                return [
                    'id' => $g['id'],
                    'slug' => $g['slug'] ?? ('guide-' . $g['id']),
                    'title' => $g['title'] ?? 'Guide',
                    'description' => '',
                    'content' => $g['content'] ?? '',
                    'order_index' => 0,
                    'created_at' => $g['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => $g['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }, $oldGuides);
        }
    } catch (Exception $e) {
        // ignore
    }
}

// If no guides in database, provide comprehensive default guides
$defaultGuides = [
    [
        'id' => 'getting-started',
        'slug' => 'getting-started',
        'title' => '🚀 Getting Started',
        'description' => 'Everything you need to know to start using Scroll Novels',
        'content' => '<h2>Welcome to Scroll Novels!</h2>
<p>Scroll Novels is a community-driven platform for sharing and discovering amazing stories. Whether you\'re a reader or writer, this guide will help you get started.</p>

<h3>📖 For Readers</h3>
<h4>Creating Your Account</h4>
<ol>
<li>Click the <strong>"Sign Up"</strong> button in the top right corner</li>
<li>Enter your email, username, and password</li>
<li>Verify your email address</li>
<li>Complete your profile with a bio and profile picture</li>
</ol>

<h4>Finding Stories to Read</h4>
<ul>
<li><strong>Browse:</strong> Explore stories by genre, popularity, or latest updates</li>
<li><strong>Search:</strong> Use the search bar to find specific titles or authors</li>
<li><strong>Rankings:</strong> Check out trending and top-rated stories</li>
<li><strong>Recommendations:</strong> Get personalized suggestions based on your reading history</li>
</ul>

<h4>Reading Features</h4>
<ul>
<li><strong>Library:</strong> Save stories to your library for easy access</li>
<li><strong>Reading Progress:</strong> Your progress is automatically saved</li>
<li><strong>Dark Mode:</strong> Toggle dark mode for comfortable nighttime reading</li>
<li><strong>Comments:</strong> Engage with authors and other readers</li>
</ul>

<h3>✍️ For Writers</h3>
<h4>Starting Your First Story</h4>
<ol>
<li>Go to your <strong>Dashboard</strong> and click <strong>"Write New Story"</strong></li>
<li>Fill in your story details: title, description, genre, tags</li>
<li>Upload a cover image (recommended size: 400x600px)</li>
<li>Start writing your first chapter!</li>
</ol>

<h4>Publishing Tips</h4>
<ul>
<li>Write compelling descriptions to attract readers</li>
<li>Use appropriate tags to help readers find your story</li>
<li>Maintain a consistent posting schedule</li>
<li>Engage with your readers through comments</li>
</ul>',
        'order_index' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'id' => 'writing-guide',
        'slug' => 'writing-guide',
        'title' => '✍️ Writing Your Story',
        'description' => 'Tips and best practices for writing on Scroll Novels',
        'content' => '<h2>Writing Guide</h2>
<p>This guide covers everything you need to know about creating and managing your stories on Scroll Novels.</p>

<h3>📝 Creating a New Story</h3>
<h4>Step 1: Story Setup</h4>
<ol>
<li>Navigate to your Dashboard</li>
<li>Click <strong>"Write New Story"</strong></li>
<li>Enter your story title (make it catchy!)</li>
<li>Write a compelling description (200-500 words recommended)</li>
<li>Select your primary genre</li>
<li>Add relevant tags (up to 10)</li>
</ol>

<h4>Step 2: Cover Image</h4>
<ul>
<li>Recommended dimensions: <strong>400x600 pixels</strong></li>
<li>Supported formats: JPG, PNG, WebP</li>
<li>Maximum file size: 5MB</li>
<li>Need a cover? Visit our <strong>Find Artist</strong> page to commission one!</li>
</ul>

<h4>Step 3: Writing Chapters</h4>
<ul>
<li>Click <strong>"Add Chapter"</strong> on your story page</li>
<li>Give each chapter a title</li>
<li>Use the rich text editor for formatting</li>
<li>Save as draft or publish immediately</li>
</ul>

<h3>💡 Writing Tips</h3>
<ul>
<li><strong>Hook readers early:</strong> Start with an engaging opening</li>
<li><strong>Consistent updates:</strong> Regular uploads keep readers engaged</li>
<li><strong>Chapter length:</strong> 1,500-3,000 words is ideal for web novels</li>
<li><strong>Cliffhangers:</strong> End chapters with something to look forward to</li>
<li><strong>Proofread:</strong> Check for typos and grammar before publishing</li>
</ul>

<h3>📊 Story Analytics</h3>
<p>Track your story\'s performance with:</p>
<ul>
<li>View counts per chapter</li>
<li>Reader retention rates</li>
<li>Comment engagement</li>
<li>Library additions</li>
</ul>',
        'order_index' => 2,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'id' => 'community-guidelines',
        'slug' => 'community-guidelines',
        'title' => '📜 Community Guidelines',
        'description' => 'Rules and guidelines for a safe and respectful community',
        'content' => '<h2>Community Guidelines</h2>
<p>Scroll Novels is committed to providing a safe, respectful, and inclusive environment for all users. Please follow these guidelines.</p>

<h3>🤝 Be Respectful</h3>
<ul>
<li>Treat all users with kindness and respect</li>
<li>No harassment, bullying, or personal attacks</li>
<li>Constructive criticism is welcome; rudeness is not</li>
<li>Respect different opinions and perspectives</li>
</ul>

<h3>📖 Content Rules</h3>
<ul>
<li><strong>Original Work:</strong> Only post content you created or have rights to</li>
<li><strong>No Plagiarism:</strong> Copying others\' work is strictly prohibited</li>
<li><strong>Content Warnings:</strong> Tag mature content appropriately</li>
<li><strong>No Illegal Content:</strong> Content promoting illegal activities is banned</li>
</ul>

<h3>💬 Comments & Reviews</h3>
<ul>
<li>Keep comments constructive and on-topic</li>
<li>No spam, advertising, or self-promotion in comments</li>
<li>Reviews should be honest but respectful</li>
<li>No spoilers without proper warnings</li>
</ul>

<h3>⚠️ Reporting Issues</h3>
<p>If you encounter content that violates our guidelines:</p>
<ol>
<li>Click the <strong>"Report"</strong> button on the story or comment</li>
<li>Select the appropriate reason</li>
<li>Provide details if needed</li>
<li>Our moderation team will review within 24-48 hours</li>
</ol>

<h3>🚫 Consequences</h3>
<p>Violations may result in:</p>
<ul>
<li>Warning</li>
<li>Temporary suspension</li>
<li>Permanent ban</li>
<li>Content removal</li>
</ul>',
        'order_index' => 3,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'id' => 'faq',
        'slug' => 'faq',
        'title' => '❓ Frequently Asked Questions',
        'description' => 'Answers to common questions about Scroll Novels',
        'content' => '<h2>Frequently Asked Questions</h2>

<h3>📱 Account & Profile</h3>

<h4>Q: How do I change my username?</h4>
<p>A: Go to <strong>Settings > Profile</strong> and click on your username to edit it. Note: You can only change your username once every 30 days.</p>

<h4>Q: How do I reset my password?</h4>
<p>A: Click "Forgot Password" on the login page, enter your email, and follow the instructions sent to your inbox.</p>

<h4>Q: Can I delete my account?</h4>
<p>A: Yes, go to <strong>Settings > Account > Delete Account</strong>. This action is permanent and cannot be undone.</p>

<h3>📖 Reading</h3>

<h4>Q: How do I save a story to read later?</h4>
<p>A: Click the <strong>bookmark icon (🔖)</strong> on any story page to add it to your library.</p>

<h4>Q: Does reading progress sync across devices?</h4>
<p>A: Yes! As long as you\'re logged in, your reading progress syncs automatically.</p>

<h4>Q: How do I enable dark mode?</h4>
<p>A: Click the <strong>moon icon (🌙)</strong> in the header or go to Settings > Display.</p>

<h3>✍️ Writing</h3>

<h4>Q: How many stories can I publish?</h4>
<p>A: There\'s no limit! You can publish as many stories as you\'d like.</p>

<h4>Q: Can I edit a chapter after publishing?</h4>
<p>A: Yes, you can edit published chapters anytime from your Book Dashboard.</p>

<h4>Q: How do I add images to my chapters?</h4>
<p>A: Use the image button in the chapter editor to upload images. You can add multiple images per chapter.</p>

<h4>Q: What genres are available?</h4>
<p>A: We support Fantasy, Romance, Sci-Fi, Mystery, Horror, Slice of Life, Action, Comedy, Drama, and more!</p>

<h3>💰 Monetization</h3>

<h4>Q: Can I earn money from my stories?</h4>
<p>A: Yes! Popular authors can earn through our Points system and reader support features.</p>

<h4>Q: How do Support Points work?</h4>
<p>A: Readers can send Support Points to their favorite authors. Points can be converted to real money when you reach the minimum threshold.</p>

<h3>🔧 Technical Issues</h3>

<h4>Q: The website isn\'t loading properly. What should I do?</h4>
<p>A: Try these steps:</p>
<ol>
<li>Clear your browser cache</li>
<li>Disable browser extensions</li>
<li>Try a different browser</li>
<li>Contact support if issues persist</li>
</ol>

<h4>Q: Images aren\'t uploading. Why?</h4>
<p>A: Check that your image is under 5MB and in JPG, PNG, or WebP format. Also ensure you have a stable internet connection.</p>',
        'order_index' => 4,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'id' => 'competitions',
        'slug' => 'competitions',
        'title' => '🏆 Competitions & Events',
        'description' => 'Learn how to participate in writing competitions',
        'content' => '<h2>Competitions & Events</h2>
<p>Scroll Novels regularly hosts writing competitions with exciting prizes. Here\'s how to participate!</p>

<h3>🎯 How to Enter a Competition</h3>
<ol>
<li>Go to the <strong>Competitions</strong> page</li>
<li>Find an active competition you\'d like to join</li>
<li>Click <strong>"Enter Competition"</strong></li>
<li>Either select an existing story or create a new one</li>
<li>Ensure your story meets the competition requirements</li>
<li>Submit before the deadline!</li>
</ol>

<h3>📋 Competition Rules</h3>
<ul>
<li>One entry per competition (unless stated otherwise)</li>
<li>Entries must be original work</li>
<li>Follow the theme/genre requirements</li>
<li>Meet minimum word count requirements</li>
<li>Submit before the deadline - late entries won\'t be accepted</li>
</ul>

<h3>🏅 Prizes</h3>
<p>Prizes vary by competition but may include:</p>
<ul>
<li>Cash prizes</li>
<li>Featured placement on homepage</li>
<li>Special badges for your profile</li>
<li>Points bonuses</li>
<li>Merchandise</li>
</ul>

<h3>📅 Upcoming Events</h3>
<p>Check the <strong>Competitions</strong> page regularly for new events. You can also enable notifications to be alerted when new competitions are announced!</p>',
        'order_index' => 5,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'id' => 'points-rewards',
        'slug' => 'points-rewards',
        'title' => '⭐ Points & Rewards',
        'description' => 'Understanding the points system and how to earn rewards',
        'content' => '<h2>Points & Rewards System</h2>
<p>Scroll Novels uses a points system to reward active community members. Here\'s how it works!</p>

<h3>📊 How to Earn Points</h3>
<table style="width: 100%; border-collapse: collapse; margin: 1rem 0;">
<tr style="background: var(--primary-lighter);">
<th style="padding: 0.75rem; text-align: left; border: 1px solid var(--border);">Activity</th>
<th style="padding: 0.75rem; text-align: left; border: 1px solid var(--border);">Points</th>
</tr>
<tr>
<td style="padding: 0.75rem; border: 1px solid var(--border);">Daily login</td>
<td style="padding: 0.75rem; border: 1px solid var(--border);">+5</td>
</tr>
<tr>
<td style="padding: 0.75rem; border: 1px solid var(--border);">Publish a chapter</td>
<td style="padding: 0.75rem; border: 1px solid var(--border);">+20</td>
</tr>
<tr>
<td style="padding: 0.75rem; border: 1px solid var(--border);">Leave a review</td>
<td style="padding: 0.75rem; border: 1px solid var(--border);">+10</td>
</tr>
<tr>
<td style="padding: 0.75rem; border: 1px solid var(--border);">Comment on a chapter</td>
<td style="padding: 0.75rem; border: 1px solid var(--border);">+2</td>
</tr>
<tr>
<td style="padding: 0.75rem; border: 1px solid var(--border);">Complete an achievement</td>
<td style="padding: 0.75rem; border: 1px solid var(--border);">+50 to +500</td>
</tr>
</table>

<h3>🎁 What Can I Do With Points?</h3>
<ul>
<li><strong>Support Authors:</strong> Send points to your favorite writers</li>
<li><strong>Unlock Badges:</strong> Display achievements on your profile</li>
<li><strong>Enter Raffles:</strong> Use points for special event entries</li>
<li><strong>Custom Features:</strong> Unlock profile customization options</li>
</ul>

<h3>🏆 Achievements</h3>
<p>Earn special badges by reaching milestones:</p>
<ul>
<li><strong>First Steps:</strong> Create your first story</li>
<li><strong>Bookworm:</strong> Read 10 stories</li>
<li><strong>Popular Author:</strong> Reach 1,000 total views</li>
<li><strong>Community Star:</strong> Leave 100 comments</li>
<li><strong>And many more!</strong></li>
</ul>',
        'order_index' => 6,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]
];

if (empty($guides)) {
    $guides = $defaultGuides;
}

// Get selected guide from URL
$selectedSlug = isset($_GET['slug']) ? htmlspecialchars($_GET['slug']) : null;
$selectedGuide = null;

if ($selectedSlug && !empty($guides)) {
    foreach ($guides as $g) {
        if ($g['slug'] === $selectedSlug) {
            $selectedGuide = $g;
            break;
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $selectedGuide ? htmlspecialchars($selectedGuide['title']) . ' - Guides' : 'Guides' ?> - Scroll Novels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= site_url('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= site_url('/assets/css/theme.css') ?>">
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
        }

        body {
            background: var(--background);
            color: var(--text-primary);
        }

        .guides-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
            min-height: calc(100vh - 200px);
        }

        .guides-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .guides-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .guides-header p {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .guides-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }

        .guides-sidebar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 120px;
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }

        .guides-sidebar h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .guides-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .guides-list li {
            margin-bottom: 0.5rem;
        }

        .guides-list a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .guides-list a:hover {
            background: var(--primary-lighter);
            color: var(--primary);
            border-left-color: var(--primary);
            padding-left: 1.25rem;
        }

        .guides-list a.active {
            background: var(--primary-lighter);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .guides-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
        }

        .guides-content h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .guides-content .guide-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .guides-content .guide-description {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--background);
            border-left: 4px solid var(--primary);
            border-radius: 6px;
        }

        .guides-content .guide-body {
            line-height: 1.8;
            margin-bottom: 2rem;
        }

        .guides-content .guide-body h3 {
            font-size: 1.5rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .guides-content .guide-body h4 {
            font-size: 1.2rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: var(--primary-light);
        }

        .guides-content .guide-body p {
            margin-bottom: 1rem;
        }

        .guides-content .guide-body ul,
        .guides-content .guide-body ol {
            margin-bottom: 1rem;
            margin-left: 1.5rem;
        }

        .guides-content .guide-body li {
            margin-bottom: 0.5rem;
        }

        .guides-content .guide-body code {
            background: var(--background);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: var(--primary);
        }

        .guides-content .guide-body pre {
            background: var(--background);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
            margin-bottom: 1rem;
        }

        .guides-images {
            margin-top: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .guides-images figure {
            margin: 0;
            text-align: center;
        }

        .guides-images img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .guides-images figcaption {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 0.75rem;
            font-style: italic;
        }

        .no-guides {
            text-align: center;
            padding: 3rem;
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .no-guides i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .no-guides p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        @media (max-width: 968px) {
            .guides-layout {
                grid-template-columns: 1fr;
            }

            .guides-sidebar {
                position: static;
                max-height: none;
            }

            .guides-header h1 {
                font-size: 1.8rem;
            }
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            :root {
                --background: #1a1a1a;
                --surface: #2d2d2d;
                --text-primary: #e0e0e0;
                --text-secondary: #a0a0a0;
                --border: #404040;
            }

            .guides-content .guide-description {
                background: rgba(16, 185, 129, 0.1);
            }

            .guides-content .guide-body code {
                background: rgba(0, 0, 0, 0.3);
            }

            .guides-content .guide-body pre {
                background: rgba(0, 0, 0, 0.5);
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <main>
        <div class="guides-container">
            <div class="guides-header">
                <h1>📚 Guides & Resources</h1>
                <p>Learn how to make the most of Scroll Novels</p>
                <?php if ($isAdmin): ?>
                    <div style="margin-top: 1rem;">
                        <a href="<?= site_url('/pages/admin/guides.php') ?>" class="btn" style="
                            display: inline-block;
                            padding: 0.5rem 0.9rem;
                            background: var(--primary);
                            color: #fff;
                            border-radius: 8px;
                            text-decoration: none;
                            font-size: 0.95rem;
                        ">
                            <i class="fas fa-tools"></i> Manage Guides
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($guides)): ?>
                <div class="no-guides">
                    <i class="fas fa-book-open"></i>
                    <p>No guides available yet. Check back soon!</p>
                </div>
            <?php elseif ($selectedGuide): ?>
                <div class="guides-layout">
                    <aside class="guides-sidebar">
                        <h3><i class="fas fa-list"></i> All Guides</h3>
                        <ul class="guides-list">
                            <?php foreach ($guides as $guide): ?>
                                <li>
                                    <a 
                                        href="<?= site_url('/pages/guides.php?slug=' . urlencode($guide['slug'])) ?>"
                                        class="<?= $guide['slug'] === $selectedSlug ? 'active' : '' ?>"
                                    >
                                        <?= htmlspecialchars($guide['title']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>

                    <article class="guides-content">
                        <h2><?= htmlspecialchars($selectedGuide['title']) ?></h2>
                        
                        <div class="guide-meta">
                            <span><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($selectedGuide['created_at'])) ?></span>
                            <span><i class="fas fa-history"></i> Updated: <?= date('F d, Y', strtotime($selectedGuide['updated_at'])) ?></span>
                        </div>

                        <?php if ($selectedGuide['description']): ?>
                            <div class="guide-description">
                                <?= htmlspecialchars($selectedGuide['description']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="guide-body">
                            <?= $selectedGuide['content'] ?>
                        </div>

                        <?php if (!empty($selectedGuide['sections'])): ?>
                            <div class="guide-sections">
                                <?php foreach ($selectedGuide['sections'] as $section): ?>
                                    <section>
                                        <h3><?= htmlspecialchars($section['title']) ?></h3>
                                        <div>
                                            <?= nl2br(htmlspecialchars($section['content'])) ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($selectedGuide['images'])): ?>
                            <div class="guides-images">
                                <?php foreach ($selectedGuide['images'] as $image): ?>
                                    <figure>
                                        <img 
                                            src="<?= htmlspecialchars($image['image_url']) ?>" 
                                            alt="<?= htmlspecialchars($image['alt_text'] ?? $selectedGuide['title']) ?>"
                                        >
                                        <?php if ($image['caption']): ?>
                                            <figcaption><?= htmlspecialchars($image['caption']) ?></figcaption>
                                        <?php endif; ?>
                                    </figure>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            <?php else: ?>
                <div class="guides-layout">
                    <aside class="guides-sidebar">
                        <h3><i class="fas fa-list"></i> All Guides</h3>
                        <ul class="guides-list">
                            <?php foreach ($guides as $guide): ?>
                                <li>
                                    <a href="<?= site_url('/pages/guides.php?slug=' . urlencode($guide['slug'])) ?>">
                                        <?= htmlspecialchars($guide['title']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>

                    <div class="guides-content">
                        <h2>Welcome to Guides</h2>
                        <p style="font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 1rem;">
                            Select a guide from the list on the left to get started.
                        </p>
                        <div style="background: var(--background); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary);">
                            <h3 style="color: var(--primary); margin-bottom: 0.5rem;">Available Guides:</h3>
                            <ul style="list-style-position: inside; color: var(--text-secondary);">
                                <?php foreach ($guides as $guide): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($guide['title']) ?></strong> 
                                        <?php if ($guide['description']): ?>
                                            - <?= htmlspecialchars(substr($guide['description'], 0, 100)) ?>...
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
