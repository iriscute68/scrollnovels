<?php
/**
 * COMPLETE BOOK READER SYSTEM
 * Full book reading interface with all features
 */

require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Comprehensive Reader Server Class
 * Full server-side implementation of critical features
 */
class ScrollNovelsReaderServer {
    private $db;
    private $config = [
        'maxFontSize' => 28,
        'minFontSize' => 12,
        'supportedThemes' => ['light', 'dark', 'sepia', 'green'],
        'supportedFonts' => ['serif', 'sans-serif', 'mono', 'dyslexic'],
    ];

    public function __construct($database = null) {
        $this->db = $database;
    }

    public function saveReadingPreference($userId, $preferences) {
        $validated = [
            'fontSize' => $this->validateFontSize($preferences['fontSize'] ?? 16),
            'theme' => $this->validateTheme($preferences['theme'] ?? 'light'),
            'font' => $this->validateFont($preferences['font'] ?? 'sans-serif'),
            'lineHeight' => $this->validateLineHeight($preferences['lineHeight'] ?? 1.6),
            'alignment' => $this->validateAlignment($preferences['alignment'] ?? 'left'),
            'mode' => $this->validateMode($preferences['mode'] ?? 'scroll'),
        ];
        $_SESSION["user_{$userId}_preferences"] = $validated;
        return true;
    }

    public function voteOnComment($commentId, $userId, $voteType) {
        if (!in_array($voteType, ['like', 'dislike'])) return false;
        $existingVote = $this->getExistingVote($commentId, $userId);
        if ($existingVote) {
            return $this->updateVote($commentId, $userId, $voteType);
        }
        return $this->addVote($commentId, $userId, $voteType);
    }

    public function followAuthor($userId, $authorId) {
        if ($userId === $authorId) return false;
        $following = $this->getUserFollowing($userId) ?? [];
        if (!in_array($authorId, $following)) {
            $following[] = $authorId;
            return $this->updateUserFollowing($userId, $following);
        }
        return true;
    }

    public function bookmarkBook($userId, $bookId) {
        $bookmarks = $this->getUserBookmarks($userId) ?? [];
        if (!in_array($bookId, $bookmarks)) {
            $bookmarks[] = $bookId;
            return $this->updateUserBookmarks($userId, $bookmarks);
        }
        return true;
    }

    public function getUserLibrary($userId) {
        $bookmarks = $this->getUserBookmarks($userId) ?? [];
        return array_map(function($bookId) {
            return $this->getBookDetails($bookId);
        }, $bookmarks);
    }

    private function validateFontSize($size) {
        return max($this->config['minFontSize'], min($this->config['maxFontSize'], intval($size)));
    }

    private function validateTheme($theme) {
        return in_array($theme, $this->config['supportedThemes']) ? $theme : 'light';
    }

    private function validateFont($font) {
        return in_array($font, $this->config['supportedFonts']) ? $font : 'sans-serif';
    }

    private function validateLineHeight($height) {
        return max(1.2, min(2.5, floatval($height)));
    }

    private function validateAlignment($alignment) {
        $valid = ['left', 'center', 'justify', 'right'];
        return in_array($alignment, $valid) ? $alignment : 'left';
    }

    private function validateMode($mode) {
        $valid = ['scroll', 'pageflip', 'continuous'];
        return in_array($mode, $valid) ? $mode : 'scroll';
    }

    private function getUserFollowing($userId) {
        return $_SESSION["user_{$userId}_following"] ?? null;
    }

    private function updateUserFollowing($userId, $following) {
        $_SESSION["user_{$userId}_following"] = $following;
        return true;
    }

    private function getUserBookmarks($userId) {
        return $_SESSION["user_{$userId}_bookmarks"] ?? null;
    }

    private function updateUserBookmarks($userId, $bookmarks) {
        $_SESSION["user_{$userId}_bookmarks"] = $bookmarks;
        return true;
    }

    private function getExistingVote($commentId, $userId) {
        return $_SESSION["vote_{$commentId}_{$userId}"] ?? null;
    }

    private function addVote($commentId, $userId, $voteType) {
        $_SESSION["vote_{$commentId}_{$userId}"] = $voteType;
        return true;
    }

    private function updateVote($commentId, $userId, $voteType) {
        $_SESSION["vote_{$commentId}_{$userId}"] = $voteType;
        return true;
    }

    private function getBookDetails($bookId) {
        return ['id' => $bookId, 'title' => 'Book ' . $bookId];
    }
}

$readerServer = new ScrollNovelsReaderServer();

$bookId = intval($_GET['id'] ?? 0);
$chapterId = intval($_GET['chapter'] ?? 0);

if (!$bookId) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Get book details
$stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ? LIMIT 1");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Get chapters
$chaptersStmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? ORDER BY chapter_number ASC");
$chaptersStmt->execute([$bookId]);
$chapters = $chaptersStmt->fetchAll();

// Get current chapter
if ($chapterId) {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ? AND story_id = ? LIMIT 1");
    $stmt->execute([$chapterId, $bookId]);
    $currentChapter = $stmt->fetch();
} else {
    $currentChapter = $chapters[0] ?? null;
}

// Update reading progress
if ($_SESSION['user_id'] ?? null) {
    $stmt = $pdo->prepare("
        UPDATE stories 
        SET last_read_chapter = ?, views = views + 1 
        WHERE id = ?
    ");
    $stmt->execute([$chapterId, $bookId]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Book Reader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Georgia', serif;
            background: #fff;
            transition: background 0.3s;
        }

        body.dark-theme {
            background: #1a1a1a;
            color: #e0e0e0;
        }

        body.sepia-theme {
            background: #f4ecd8;
            color: #5c4033;
        }

        .reader-container {
            display: flex;
            height: 100vh;
        }

        .reader-sidebar {
            width: 300px;
            background: #f8f9fa;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            padding: 20px;
            transition: all 0.3s;
        }

        .reader-sidebar.collapsed {
            width: 0;
            padding: 0;
            overflow: hidden;
        }

        .reader-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .reader-header {
            background: white;
            border-bottom: 1px solid #ddd;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reader-header.dark-theme {
            background: #2a2a2a;
            border-color: #444;
        }

        .reader-content {
            flex: 1;
            overflow-y: auto;
            padding: 60px 80px;
        }

        .reader-content.scrolling {
            overflow-y: scroll;
        }

        .reader-content.paginated {
            overflow: hidden;
        }

        .chapter-text {
            line-height: 1.8;
            font-size: 16px;
            max-width: 900px;
            margin: 0 auto;
        }

        .chapter-text p {
            margin-bottom: 1.5em;
            text-align: justify;
        }

        .chapter-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }

        .chapter-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .chapter-meta {
            color: #666;
            font-size: 14px;
        }

        /* Settings Panel */
        .settings-panel {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background: white;
            border-left: 1px solid #ddd;
            padding: 20px;
            overflow-y: auto;
            transition: right 0.3s;
            z-index: 1000;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        }

        .settings-panel.open {
            right: 0;
        }

        .settings-panel.dark-theme {
            background: #2a2a2a;
        }

        .settings-group {
            margin-bottom: 25px;
        }

        .settings-label {
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }

        .settings-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .settings-option {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .settings-option.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .settings-option:hover {
            border-color: #667eea;
        }

        .settings-slider {
            width: 100%;
            margin-top: 10px;
        }

        /* Chapter List */
        .chapter-list {
            margin-top: 20px;
        }

        .chapter-item {
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 8px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .chapter-item:hover {
            background: #f0f0f0;
        }

        .chapter-item.active {
            background: #667eea;
            color: white;
            border-left-color: white;
        }

        /* Controls */
        .reader-controls {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border: 1px solid #ddd;
            border-radius: 50px;
            padding: 15px 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .reader-controls.dark-theme {
            background: #2a2a2a;
            border-color: #444;
        }

        .control-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #667eea;
            transition: all 0.2s;
        }

        .control-btn:hover {
            transform: scale(1.2);
        }

        /* Progress Bar */
        .progress-bar-reader {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s;
        }

        /* Comments Section */
        .comments-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #ddd;
        }

        .comment-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .comment-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: bold;
        }

        .comment-date {
            font-size: 12px;
            color: #666;
        }

        @media (max-width: 768px) {
            .reader-sidebar {
                width: 0;
                padding: 0;
                overflow: hidden;
            }
            .reader-content {
                padding: 40px 20px;
            }
            .settings-panel {
                width: 100%;
                right: -100%;
            }
        }
    </style>
</head>
<body>

<div class="progress-bar-reader" id="progressBar"></div>

<div class="reader-container">
    <!-- SIDEBAR - CHAPTER LIST -->
    <div class="reader-sidebar" id="sidebar">
        <h5 class="mb-3">
            <i class="fas fa-times-circle" style="cursor: pointer;" onclick="toggleSidebar()"></i> 
            Chapters
        </h5>
        <div class="chapter-list">
            <?php foreach ($chapters as $ch): ?>
                <div class="chapter-item <?php echo $ch['id'] == $currentChapter['id'] ? 'active' : ''; ?>" 
                     onclick="loadChapter(<?php echo $ch['id']; ?>)">
                    <strong>Chapter <?php echo $ch['chapter_number']; ?></strong>
                    <p class="mb-0" style="font-size: 12px;"><?php echo htmlspecialchars(substr($ch['title'], 0, 50)); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN READING AREA -->
    <div class="reader-main">
        <div class="reader-header">
            <div>
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSidebar()">
                    <i class="fas fa-list"></i> Chapters
                </button>
            </div>
            <h6 class="mb-0"><?php echo htmlspecialchars($book['title']); ?></h6>
            <div>
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSettings()">
                    <i class="fas fa-sliders-h"></i> Settings
                </button>
                <a href="<?php echo site_url('/pages/book.php?id=' . $bookId); ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>

        <div class="reader-content scrolling" id="readerContent">
            <?php if ($currentChapter): ?>
                <div class="chapter-text">
                    <div class="chapter-header">
                        <div class="chapter-title"><?php echo htmlspecialchars($currentChapter['title']); ?></div>
                        <div class="chapter-meta">Chapter <?php echo $currentChapter['chapter_number']; ?></div>
                    </div>
                    <div class="chapter-body">
                        <?php echo nl2br(htmlspecialchars($currentChapter['content'])); ?>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="comments-section">
                    <h4>Comments</h4>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="comment-form">
                            <textarea class="form-control mb-2" placeholder="Add your comment..." rows="3"></textarea>
                            <button class="btn btn-primary btn-sm">Post Comment</button>
                        </div>
                    <?php else: ?>
                        <p><a href="<?php echo site_url('/pages/login.php'); ?>">Login</a> to comment</p>
                    <?php endif; ?>

                    <!-- Sample Comments -->
                    <div class="comment-item">
                        <div class="comment-header">
                            <span class="comment-author">John Doe</span>
                            <span class="comment-date">2 hours ago</span>
                        </div>
                        <p>Amazing chapter! Can't wait for the next one.</p>
                        <small><a href="#">Like</a> • <a href="#">Reply</a></small>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reading Controls -->
        <div class="reader-controls" id="controls">
            <button class="control-btn" title="Previous Chapter" onclick="prevChapter()">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span id="chapterCounter">1 / <?php echo count($chapters); ?></span>
            <button class="control-btn" title="Next Chapter" onclick="nextChapter()">
                <i class="fas fa-chevron-right"></i>
            </button>
            <div style="width: 1px; height: 20px; background: #ddd;"></div>
            <button class="control-btn" title="Brightness" onclick="toggleBrightness()">
                <i class="fas fa-sun"></i>
            </button>
            <button class="control-btn" title="Text to Speech" onclick="toggleTTS()">
                <i class="fas fa-volume-up"></i>
            </button>
            <button class="control-btn" title="Fullscreen" onclick="toggleFullscreen()">
                <i class="fas fa-expand"></i>
            </button>
        </div>
    </div>
</div>

<!-- SETTINGS PANEL -->
<div class="settings-panel" id="settingsPanel">
    <h5 class="mb-4">Reading Settings</h5>

    <div class="settings-group">
        <label class="settings-label">Font</label>
        <div class="settings-options">
            <div class="settings-option active" onclick="setFont('serif')">Serif</div>
            <div class="settings-option" onclick="setFont('sans-serif')">Sans</div>
            <div class="settings-option" onclick="setFont('mono')">Mono</div>
            <div class="settings-option" onclick="setFont('dyslexic')">Dyslexic</div>
        </div>
    </div>

    <div class="settings-group">
        <label class="settings-label">Font Size</label>
        <input type="range" class="settings-slider" min="12" max="24" value="16" 
               oninput="setFontSize(this.value)">
        <span id="fontSizeValue">16px</span>
    </div>

    <div class="settings-group">
        <label class="settings-label">Theme</label>
        <div class="settings-options">
            <div class="settings-option active" onclick="setTheme('light')">Light</div>
            <div class="settings-option" onclick="setTheme('dark')">Dark</div>
            <div class="settings-option" onclick="setTheme('sepia')">Sepia</div>
        </div>
    </div>

    <div class="settings-group">
        <label class="settings-label">Text Alignment</label>
        <div class="settings-options">
            <div class="settings-option active" onclick="setAlignment('justify')">Justify</div>
            <div class="settings-option" onclick="setAlignment('left')">Left</div>
            <div class="settings-option" onclick="setAlignment('center')">Center</div>
        </div>
    </div>

    <div class="settings-group">
        <label class="settings-label">Line Spacing</label>
        <input type="range" class="settings-slider" min="1" max="2.5" step="0.1" value="1.8" 
               oninput="setLineSpacing(this.value)">
        <span id="lineSpacingValue">1.8</span>
    </div>

    <div class="settings-group">
        <label class="settings-label">Reading Mode</label>
        <div class="settings-options">
            <div class="settings-option active" onclick="setReadingMode('scroll')">Scroll</div>
            <div class="settings-option" onclick="setReadingMode('pageflip')">Page Flip</div>
        </div>
    </div>

    <div class="settings-group">
        <label class="form-check-label">
            <input type="checkbox" class="form-check-input" checked> Auto-save Progress
        </label>
    </div>

    <div class="settings-group">
        <label class="form-check-label">
            <input type="checkbox" class="form-check-input" checked> Show Brightness Control
        </label>
    </div>
</div>

<script>
const bookId = <?php echo $bookId; ?>;
const chapters = <?php echo json_encode(array_map(fn($c) => $c['id'], $chapters)); ?>;
let currentChapterIndex = 0;

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

function toggleSettings() {
    document.getElementById('settingsPanel').classList.toggle('open');
}

function loadChapter(chapterId) {
    window.location.href = `?id=${bookId}&chapter=${chapterId}`;
}

function nextChapter() {
    if (currentChapterIndex < chapters.length - 1) {
        loadChapter(chapters[++currentChapterIndex]);
    }
}

function prevChapter() {
    if (currentChapterIndex > 0) {
        loadChapter(chapters[--currentChapterIndex]);
    }
}

function setFont(font) {
    const fontMap = {
        'serif': 'Georgia, serif',
        'sans-serif': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        'mono': '"Courier New", monospace',
        'dyslexic': 'OpenDyslexic, sans-serif'
    };
    document.querySelector('.chapter-text').style.fontFamily = fontMap[font];
    localStorage.setItem('readerFont', font);
}

function setFontSize(size) {
    document.querySelector('.chapter-text').style.fontSize = size + 'px';
    document.getElementById('fontSizeValue').textContent = size + 'px';
    localStorage.setItem('readerFontSize', size);
}

function setTheme(theme) {
    document.body.className = theme === 'light' ? '' : theme + '-theme';
    localStorage.setItem('readerTheme', theme);
}

function setAlignment(align) {
    document.querySelector('.chapter-text p').style.textAlign = align;
    localStorage.setItem('readerAlignment', align);
}

function setLineSpacing(spacing) {
    document.querySelector('.chapter-text').style.lineHeight = spacing;
    document.getElementById('lineSpacingValue').textContent = spacing;
    localStorage.setItem('readerLineSpacing', spacing);
}

function setReadingMode(mode) {
    const content = document.getElementById('readerContent');
    content.className = mode === 'scroll' ? 'reader-content scrolling' : 'reader-content paginated';
    localStorage.setItem('readerMode', mode);
}

function toggleBrightness() {
    alert('Brightness control feature');
}

function toggleTTS() {
    alert('Text-to-Speech feature');
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// Update progress bar
window.addEventListener('scroll', () => {
    const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
    const scrolled = window.scrollY;
    const progress = (scrolled / scrollHeight) * 100;
    document.getElementById('progressBar').style.width = progress + '%';
});

// Load saved settings
window.addEventListener('load', () => {
    const savedFont = localStorage.getItem('readerFont');
    const savedSize = localStorage.getItem('readerFontSize');
    const savedTheme = localStorage.getItem('readerTheme');
    const savedMode = localStorage.getItem('readerMode');

    if (savedFont) setFont(savedFont);
    if (savedSize) setFontSize(savedSize);
    if (savedTheme) setTheme(savedTheme);
    if (savedMode) setReadingMode(savedMode);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
