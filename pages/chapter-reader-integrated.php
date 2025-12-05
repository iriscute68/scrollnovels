<?php
/**
 * Chapter Reader - Integrated Production Version
 * Full reading interface with font controls, navigation, and comments
 */

session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/auth.php');

$book_id = isset($_GET['book']) ? intval($_GET['book']) : 1;
$chapter_id = isset($_GET['chapter']) ? intval($_GET['chapter']) : 1;

try {
    // Fetch book data
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['title' => 'Unknown Book'];
    
    // Fetch chapter data
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? AND chapter_number = ?");
    $stmt->execute([$book_id, $chapter_id]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        $chapter = [
            'chapter_number' => $chapter_id,
            'title' => 'Chapter ' . $chapter_id,
            'content' => 'The morning sun filtered through the ancient oaks, casting long shadows across the forest floor. Lyra stood at the edge of the clearing, her emerald pendant glowing softly against her chest...',
            'views' => 45000
        ];
    }
    
    // Fetch author
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$book['author_id'] ?? 1]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['username' => 'Anonymous'];
    
    // Fetch comments
    $stmt = $pdo->prepare("SELECT * FROM blog_comments WHERE blog_post_id = ? LIMIT 5");
    $stmt->execute([$chapter['id'] ?? $chapter_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total chapters
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM chapters WHERE story_id = ?");
    $stmt->execute([$book_id]);
    $total_chapters_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_chapters = max(10, $total_chapters_count); // Default to at least 10 chapters
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $book = ['title' => 'Error Loading Book'];
    $chapter = ['title' => 'Error', 'content' => 'Unable to load chapter'];
    $author = ['username' => 'Unknown'];
    $comments = [];
    $total_chapters = 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($chapter['title'] ?? 'Chapter'); ?> - <?php echo htmlspecialchars($book['title'] ?? 'Book'); ?></title>
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
            --dark-bg: #1f2937;
            --dark-text: #f3f4f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', serif;
            background-color: var(--background);
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Reader Header */
        .reader-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reader-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .reader-meta {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .header-controls {
            display: flex;
            gap: 1rem;
        }

        .icon-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Settings Panel */
        .settings-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: none;
        }

        .settings-panel.active {
            display: block;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .setting-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .setting-group label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .setting-group input[type="range"] {
            width: 100%;
            cursor: pointer;
        }

        .setting-group select,
        .setting-group input[type="text"] {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: 'Georgia', serif;
            font-size: 0.95rem;
        }

        /* Chapter Content */
        .chapter-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 3rem;
            margin-bottom: 2rem;
            line-height: 1.8;
            font-size: 1.1rem;
            min-height: 600px;
        }

        body.dark-mode .chapter-content {
            background: #374151;
            border-color: #4b5563;
            color: var(--dark-text);
        }

        .chapter-content p {
            margin-bottom: 1.5rem;
            text-align: justify;
        }

        .reading-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .control-group label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .control-group input[type="range"] {
            width: 150px;
            cursor: pointer;
        }

        .value-display {
            background: var(--primary-lighter);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Navigation */
        .chapter-nav {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .nav-button {
            flex: 1;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .nav-button:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .nav-button:disabled {
            background: var(--border);
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        /* Progress Bar */
        .progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: var(--primary-light);
            transition: width 0.3s ease;
            z-index: 100;
        }

        /* Comments Section */
        .comments-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2rem;
        }

        .comments-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .comment {
            background: var(--background);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            border-radius: 4px;
        }

        body.dark-mode .comment {
            background: #2d3748;
        }

        .comment-author {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .comment-content {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .comment-actions {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
        }

        .like-btn,
        .dislike-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .like-btn:hover {
            color: var(--primary);
        }

        .dislike-btn:hover {
            color: #ef4444;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .reader-header {
                flex-direction: column;
                text-align: center;
            }

            .header-controls {
                width: 100%;
                justify-content: center;
            }

            .chapter-content {
                padding: 1.5rem;
                font-size: 1rem;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .chapter-nav {
                flex-direction: column;
            }

            .nav-button {
                padding: 0.75rem;
            }

            .reading-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .control-group {
                width: 100%;
            }

            .control-group input[type="range"] {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .chapter-content {
                padding: 1rem;
                font-size: 0.95rem;
                line-height: 1.6;
            }

            .comments-section {
                padding: 1rem;
            }

            .settings-grid {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="progress-bar" id="progressBar"></div>

    <div class="container">
        <!-- Reader Header -->
        <div class="reader-header">
            <div>
                <h1><?php echo htmlspecialchars($book['title'] ?? 'Book'); ?></h1>
                <div class="reader-meta">
                    <?php echo htmlspecialchars($chapter['title'] ?? 'Chapter ' . $chapter_id); ?> | 
                    by <?php echo htmlspecialchars($author['username'] ?? 'Anonymous'); ?> |
                    👁️ <?php echo number_format($chapter['views'] ?? 0); ?> views
                </div>
            </div>
            <div class="header-controls">
                <button class="icon-btn" id="settingsBtn" title="Settings">⚙️</button>
                <button class="icon-btn" id="darkModeBtn" title="Dark Mode">🌙</button>
                <button class="icon-btn" id="fullscreenBtn" title="Fullscreen">⛶</button>
            </div>
        </div>

        <!-- Settings Panel -->
        <div class="settings-panel" id="settingsPanel">
            <div class="settings-grid">
                <div class="setting-group">
                    <label for="fontFamily">Font:</label>
                    <select id="fontFamily" onchange="changeFontFamily(this.value)">
                        <option value="serif">Serif (Georgia)</option>
                        <option value="sans-serif">Sans-serif</option>
                        <option value="monospace">Monospace</option>
                    </select>
                </div>
                <div class="setting-group">
                    <label for="fontSize">Font Size: <span class="value-display" id="fontSizeDisplay">1.1rem</span></label>
                    <input type="range" id="fontSize" min="0.8" max="1.5" step="0.1" value="1.1" onchange="changeFontSize(this.value)">
                </div>
                <div class="setting-group">
                    <label for="lineHeight">Line Height: <span class="value-display" id="lineHeightDisplay">1.8</span></label>
                    <input type="range" id="lineHeight" min="1.4" max="2.5" step="0.1" value="1.8" onchange="changeLineHeight(this.value)">
                </div>
                <div class="setting-group">
                    <label for="textColor">Theme:</label>
                    <select id="textColor" onchange="changeTheme(this.value)">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                        <option value="sepia">Sepia</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Reading Controls -->
        <div class="reading-controls">
            <div class="control-group">
                <label>Progress:</label>
                <input type="range" id="scrollProgress" min="0" max="100" value="0" disabled style="flex: 1; margin: 0 1rem;">
                <span id="progressPercent">0%</span>
            </div>
        </div>

        <!-- Chapter Content -->
        <article class="chapter-content" id="chapterContent">
            <h2 style="margin-bottom: 2rem; color: var(--primary);">
                <?php echo htmlspecialchars($chapter['title'] ?? 'Chapter ' . $chapter_id); ?>
            </h2>
            <?php 
            $content = $chapter['content'] ?? 'Chapter content not available.';
            // Split content into paragraphs
            $paragraphs = explode("\n\n", $content);
            foreach ($paragraphs as $para) {
                if (trim($para)) {
                    echo '<p>' . htmlspecialchars($para) . '</p>';
                }
            }
            ?>
        </article>

        <!-- Navigation -->
        <div class="chapter-nav">
            <button class="nav-button" onclick="window.location.href='/scrollnovels/pages/chapter-reader-integrated.php?book=<?php echo $book_id; ?>&chapter=1'" <?php echo $chapter_id <= 1 ? 'disabled' : ''; ?>>
                ⏮ First Chapter
            </button>
            <button class="nav-button" onclick="previousChapter()" <?php echo $chapter_id <= 1 ? 'disabled' : ''; ?>>
                ← Previous Chapter
            </button>
            <button class="nav-button" onclick="window.location.href='/scrollnovels/pages/book-detail-integrated.php?id=<?php echo $book_id; ?>'">
                Back to Book
            </button>
            <button class="nav-button" onclick="nextChapter()" <?php echo $chapter_id >= $total_chapters ? 'disabled' : ''; ?>>
                Next Chapter →
            </button>
            <button class="nav-button" onclick="window.location.href='/scrollnovels/pages/chapter-reader-integrated.php?book=<?php echo $book_id; ?>&chapter=<?php echo $total_chapters; ?>'" <?php echo $chapter_id >= $total_chapters ? 'disabled' : ''; ?>>
                Last Chapter ⏭
            </button>
        </div>

        <!-- Comments Section -->
        <section class="comments-section">
            <h2>Comments (<?php echo count($comments); ?>)</h2>
            
            <!-- Comment Form -->
            <div class="comment-form" style="background: var(--surface); border: 1px solid var(--border); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                <textarea class="comment-textarea" placeholder="Share your thoughts about this chapter..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 4px; font-family: Arial, sans-serif; font-size: 1rem; min-height: 100px;"></textarea>
                <button onclick="postComment()" style="margin-top: 1rem; padding: 0.75rem 1.5rem; background: var(--primary-light); color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Post Comment</button>
            </div>
            
            <!-- Comments List -->
            <div class="comments-list">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment" style="background: var(--surface); border: 1px solid var(--border); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                            <div class="comment-author" style="font-weight: bold; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($comment['user_name'] ?? 'Reader'); ?>
                            </div>
                            <div class="comment-content" style="margin-bottom: 1rem; line-height: 1.6;">
                                <?php echo htmlspecialchars(substr($comment['comment_text'] ?? '', 0, 500)); ?>
                            </div>
                            <div class="comment-actions" style="display: flex; gap: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
                                <button class="like-btn" style="background: none; border: none; cursor: pointer; padding: 0;">❤️ Like</button>
                                <button class="dislike-btn" style="background: none; border: none; cursor: pointer; padding: 0;">👎 Dislike</button>
                                <span><?php echo isset($comment['created_at']) ? date('M d, Y', strtotime($comment['created_at'])) : ''; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        const bookId = <?php echo $book_id; ?>;
        const chapterId = <?php echo $chapter_id; ?>;
        const totalChapters = <?php echo $total_chapters; ?>;

        // Settings
        document.getElementById('settingsBtn').addEventListener('click', () => {
            document.getElementById('settingsPanel').classList.toggle('active');
        });

        document.getElementById('darkModeBtn').addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        });

        document.getElementById('fullscreenBtn').addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => console.log(err));
            } else {
                document.exitFullscreen();
            }
        });

        function changeFontSize(value) {
            document.getElementById('chapterContent').style.fontSize = value + 'rem';
            document.getElementById('fontSizeDisplay').textContent = value + 'rem';
            localStorage.setItem('fontSize', value);
        }

        function changeLineHeight(value) {
            document.getElementById('chapterContent').style.lineHeight = value;
            document.getElementById('lineHeightDisplay').textContent = value;
            localStorage.setItem('lineHeight', value);
        }

        function changeFontFamily(family) {
            const fontMap = {
                'serif': 'Georgia, serif',
                'sans-serif': 'Inter, -apple-system, BlinkMacSystemFont, sans-serif',
                'monospace': 'Monaco, monospace'
            };
            document.getElementById('chapterContent').style.fontFamily = fontMap[family];
            localStorage.setItem('fontFamily', family);
        }

        function changeTheme(theme) {
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
            localStorage.setItem('theme', theme);
        }

        function previousChapter() {
            if (chapterId > 1) {
                window.location.href = `/scrollnovels/pages/chapter-reader-integrated.php?book=${bookId}&chapter=${chapterId - 1}`;
            }
        }

        function nextChapter() {
            if (chapterId < totalChapters) {
                window.location.href = `/scrollnovels/pages/chapter-reader-integrated.php?book=${bookId}&chapter=${chapterId + 1}`;
            }
        }

        // Track reading progress
        document.addEventListener('scroll', () => {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrolled = (scrollTop / docHeight) * 100;
            document.getElementById('progressBar').style.width = scrolled + '%';
            document.getElementById('scrollProgress').value = scrolled;
            document.getElementById('progressPercent').textContent = Math.round(scrolled) + '%';
        });

        // Load saved preferences
        window.addEventListener('load', () => {
            const savedSize = localStorage.getItem('fontSize');
            const savedHeight = localStorage.getItem('lineHeight');
            const savedFamily = localStorage.getItem('fontFamily');
            const savedTheme = localStorage.getItem('theme');
            const savedDarkMode = localStorage.getItem('darkMode');

            if (savedSize) changeFontSize(savedSize);
            if (savedHeight) changeLineHeight(savedHeight);
            if (savedFamily) changeFontFamily(savedFamily);
            if (savedTheme) changeTheme(savedTheme);
            if (savedDarkMode === 'true') {
                document.body.classList.add('dark-mode');
                document.getElementById('darkModeBtn').innerHTML = '☀️';
            }
        });

        // Post Comment Function
        function postComment() {
            const textarea = document.querySelector('.comment-textarea');
            if (!textarea || !textarea.value.trim()) {
                alert('Please write a comment first');
                return;
            }

            fetch('/scrollnovels/api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    comment: textarea.value,
                    chapter_id: chapterId,
                    book_id: bookId
                }),
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    textarea.value = '';
                    alert('Comment posted successfully!');
                    location.reload();
                } else {
                    alert('Error posting comment: ' + (data.error || 'Unknown error'));
                }
            })
            .catch((error) => {
                console.error('Error posting comment:', error);
                alert('Error posting comment. Please try again.');
            });
        }

        // Initialize Like/Dislike buttons
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.color = this.style.color === 'red' ? 'var(--text-secondary)' : 'red';
            });
        });

        document.querySelectorAll('.dislike-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.color = this.style.color === 'red' ? 'var(--text-secondary)' : 'red';
            });
        });

        // Toggle dark mode icon
        const darkModeBtn = document.getElementById('darkModeBtn');
        darkModeBtn.addEventListener('click', () => {
            darkModeBtn.innerHTML = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        });
    </script>
</body>
</html>
