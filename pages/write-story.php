<?php
// pages/write-story.php - Redesigned story creation with TinyMCE
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$story_id = (int)($_GET['edit'] ?? 0);
$competition_id = (int)($_GET['competition'] ?? $_POST['competition_id'] ?? 0);
$story = null;
$was_just_saved = isset($_GET['saved']) && $_GET['saved'] === '1';

// Fetch story if editing (do this BEFORE POST processing so we have original data)
if ($story_id) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM stories WHERE id = ? AND author_id = ?');
        $stmt->execute([$story_id, $user_id]);
        $story = $stmt->fetch();
        if (!$story) {
            header('Location: ' . site_url('/pages/dashboard.php'));
            exit;
        }
        
        // If just saved, show success message with what was saved
        if ($was_just_saved) {
            $tags_display = $story['tags'] ? implode(', ', array_filter(explode(',', $story['tags']))) : 'None';
            $genres_display = $story['genres'] ? implode(', ', array_filter(explode(',', $story['genres']))) : 'None';
            $warnings_display = $story['content_warnings'] ? implode(', ', array_filter(explode(',', $story['content_warnings']))) : 'None';
            $success = "✓ Story saved! Tags: $tags_display | Genres: $genres_display | Warnings: $warnings_display";
        }
    } catch (Exception $e) {
        header('Location: ' . site_url('/pages/dashboard.php'));
        exit;
    }
}

// Check if competition exists
$competition = null;
if ($competition_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
        $stmt->execute([$competition_id]);
        $competition = $stmt->fetch();
    } catch (Exception $e) {
        $competition_id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $is_adult = isset($_POST['is_adult']) ? 1 : 0;
    $status = $_POST['status'] ?? 'draft';
    $cover = $_FILES['cover'] ?? null;
    
    // New fields
    $selected_genres = json_decode($_POST['selected_genres'] ?? '[]', true);
    $selected_tags = json_decode($_POST['selected_tags'] ?? '[]', true);
    $selected_warnings = json_decode($_POST['content_warnings'] ?? '[]', true);
    
    error_log("DEBUG: POST content_warnings field: " . ($_POST['content_warnings'] ?? 'MISSING'));
    error_log("DEBUG: Decoded selected_warnings: " . json_encode($selected_warnings));
    
    // Convert selected_genres array to comma-separated string for storage
    $genres = null;
    if (!empty($selected_genres)) {
        $genre_strings = array_map(function($genre) {
            if (is_array($genre)) {
                if (isset($genre['name'])) {
                    return trim($genre['name']);
                } elseif (isset($genre['value'])) {
                    return trim($genre['value']);
                } elseif (isset($genre[0])) {
                    return trim((string)$genre[0]);
                }
                return '';
            } elseif (is_object($genre)) {
                if (isset($genre->name)) {
                    return trim($genre->name);
                } elseif (isset($genre->value)) {
                    return trim($genre->value);
                }
                return '';
            }
            return is_string($genre) ? trim($genre) : '';
        }, $selected_genres);
        $genres = implode(',', array_filter($genre_strings));
        $genres = !empty($genres) ? $genres : NULL;
    } else {
        // If no genres submitted but editing, preserve existing genres
        if ($story_id && isset($story['genres']) && !empty($story['genres'])) {
            $genres = $story['genres'];
        }
    }
    
    // ENSURE genres is always a string (not an array)
    if (is_array($genres)) {
        $genres = implode(',', array_filter(array_map(function($g) {
            return is_array($g) && isset($g['name']) ? trim($g['name']) : trim((string)$g);
        }, $genres)));
        $genres = !empty($genres) ? $genres : NULL;
    }
    error_log("DEBUG: Final genres before DB: " . ($genres ?? 'NULL') . " (type: " . gettype($genres) . ")");
    
    // Convert selected_warnings array to comma-separated string for storage
    $content_warnings = null;
    error_log("DEBUG: selected_warnings from form: " . json_encode($selected_warnings));
    if (!empty($selected_warnings)) {
        $warning_strings = array_map(function($warning) {
            if (is_array($warning)) {
                if (isset($warning['name'])) {
                    return trim($warning['name']);
                } elseif (isset($warning['value'])) {
                    return trim($warning['value']);
                } elseif (isset($warning[0])) {
                    return trim((string)$warning[0]);
                }
                return '';
            } elseif (is_object($warning)) {
                if (isset($warning->name)) {
                    return trim($warning->name);
                } elseif (isset($warning->value)) {
                    return trim($warning->value);
                }
                return '';
            }
            return is_string($warning) ? trim($warning) : '';
        }, $selected_warnings);
        $content_warnings = implode(',', array_filter($warning_strings));
        $content_warnings = !empty($content_warnings) ? $content_warnings : NULL;
        error_log("DEBUG: Processed content_warnings: " . ($content_warnings ?? 'NULL'));
    } else {
        // If no warnings submitted but editing, preserve existing warnings
        if ($story_id && isset($story['content_warnings']) && !empty($story['content_warnings'])) {
            $content_warnings = $story['content_warnings'];
        }
    }
    
    // ENSURE content_warnings is always a string (not an array)
    if (is_array($content_warnings)) {
        $content_warnings = implode(',', array_filter(array_map(function($w) {
            return is_array($w) && isset($w['name']) ? trim($w['name']) : trim((string)$w);
        }, $content_warnings)));
        $content_warnings = !empty($content_warnings) ? $content_warnings : NULL;
    }
    error_log("DEBUG: Final content_warnings before DB: " . ($content_warnings ?? 'NULL') . " (type: " . gettype($content_warnings) . ")");
    
    // Convert selected_tags array to comma-separated string for storage
    // Handle both string and object/array elements
    $tags = null;
    error_log("DEBUG: selected_tags from form: " . json_encode($selected_tags));
    if (!empty($selected_tags)) {
        $tag_strings = array_map(function($tag) {
            if (is_array($tag)) {
                // If tag is an array, try to get its value or label
                if (isset($tag['value'])) {
                    return trim($tag['value']);
                } elseif (isset($tag['label'])) {
                    return trim($tag['label']);
                } elseif (isset($tag['name'])) {
                    return trim($tag['name']);
                } elseif (isset($tag[0])) {
                    return trim((string)$tag[0]);
                }
                return '';
            } elseif (is_object($tag)) {
                // If tag is an object, try to get its properties
                if (isset($tag->value)) {
                    return trim($tag->value);
                } elseif (isset($tag->label)) {
                    return trim($tag->label);
                } elseif (isset($tag->name)) {
                    return trim($tag->name);
                }
                return '';
            }
            // Simple string
            return is_string($tag) ? trim($tag) : '';
        }, $selected_tags);
        $tags = implode(',', array_filter($tag_strings));
        $tags = !empty($tags) ? $tags : NULL;
        error_log("DEBUG: Processed tags: " . ($tags ?? 'NULL'));
    } else {
        // If no tags submitted but editing existing story, preserve existing tags
        if ($story_id && isset($story['tags']) && !empty($story['tags'])) {
            $tags = $story['tags'];
            error_log("DEBUG: Preserving existing tags for edit: " . $tags);
        }
    }
    
    // ENSURE tags is always a string (not an array)
    if (is_array($tags)) {
        $tags = implode(',', array_filter(array_map(function($t) {
            return is_array($t) && isset($t['name']) ? trim($t['name']) : trim((string)$t);
        }, $tags)));
        $tags = !empty($tags) ? $tags : NULL;
    }
    error_log("DEBUG: Final tags before DB: " . ($tags ?? 'NULL') . " (type: " . gettype($tags) . ")");
    
    $is_fanfiction = (int)($_POST['is_fanfiction'] ?? 0);
    $fanfic_source = $is_fanfiction ? trim($_POST['fanfic_source'] ?? '') : null;
    $content_type = in_array($_POST['content_type'] ?? 'novel', ['novel', 'webtoon', 'fanfic']) ? $_POST['content_type'] : 'novel';

    if (empty($title)) {
        $error = 'Title is required';
    } else {
        try {
            $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($title));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');

            // Check for duplicate slug
            $check = $pdo->prepare('SELECT id FROM stories WHERE slug = ? AND id != ?');
            $check->execute([$slug, $story_id]);
            if ($check->fetch()) {
                $slug .= '-' . time();
            }

            $cover_path = null;
            if ($cover && $cover['size'] > 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($cover['type'], $allowed)) {
                    $error = 'Invalid image format';
                } elseif ($cover['size'] > 5 * 1024 * 1024) {
                    $error = 'Image must be less than 5MB';
                } else {
                    $ext = pathinfo($cover['name'], PATHINFO_EXTENSION);
                    $filename = 'story-' . time() . '.' . $ext;
                    $upload_path = dirname(__DIR__) . '/uploads/covers/';
                    if (!is_dir($upload_path)) @mkdir($upload_path, 0755, true);
                    
                    if (move_uploaded_file($cover['tmp_name'], $upload_path . $filename)) {
                        $cover_path = site_url('/uploads/covers/' . $filename);
                    }
                }
            }

            if (empty($error)) {
                        // Ensure 'tags' column exists before using it
                        try {
                            $colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stories' AND COLUMN_NAME = 'tags' LIMIT 1");
                            $colCheck->execute();
                            $hasTags = (bool)$colCheck->fetchColumn();
                            if (!$hasTags) {
                                // Add tags column if missing
                                $pdo->exec("ALTER TABLE stories ADD COLUMN tags TEXT NULL");
                                $hasTags = true; // Column now exists
                            }
                        } catch (Exception $e) {
                            // Try to add it anyway
                            try {
                                $pdo->exec("ALTER TABLE stories ADD COLUMN tags TEXT NULL");
                                $hasTags = true;
                            } catch (Exception $e2) {
                                $hasTags = false;
                            }
                        }
                        
                        // Ensure 'genres' column exists
                        try {
                            $colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stories' AND COLUMN_NAME = 'genres' LIMIT 1");
                            $colCheck->execute();
                            $hasGenres = (bool)$colCheck->fetchColumn();
                            if (!$hasGenres) {
                                $pdo->exec("ALTER TABLE stories ADD COLUMN genres TEXT NULL");
                                $hasGenres = true;
                            }
                        } catch (Exception $e) {
                            try {
                                $pdo->exec("ALTER TABLE stories ADD COLUMN genres TEXT NULL");
                                $hasGenres = true;
                            } catch (Exception $e2) {
                                $hasGenres = false;
                            }
                        }
                        
                        // Ensure 'content_warnings' column exists
                        try {
                            $colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stories' AND COLUMN_NAME = 'content_warnings' LIMIT 1");
                            $colCheck->execute();
                            $hasWarnings = (bool)$colCheck->fetchColumn();
                            if (!$hasWarnings) {
                                $pdo->exec("ALTER TABLE stories ADD COLUMN content_warnings TEXT NULL");
                                $hasWarnings = true;
                            }
                        } catch (Exception $e) {
                            try {
                                $pdo->exec("ALTER TABLE stories ADD COLUMN content_warnings TEXT NULL");
                                $hasWarnings = true;
                            } catch (Exception $e2) {
                                $hasWarnings = false;
                            }
                        }

                        // Ensure 'is_adult' column exists (some older schemas may be missing it)
                        try {
                            $colCheck2 = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stories' AND COLUMN_NAME = 'is_adult' LIMIT 1");
                            $colCheck2->execute();
                            $hasIsAdult = (bool)$colCheck2->fetchColumn();
                            if (!$hasIsAdult) {
                                $pdo->exec("ALTER TABLE stories ADD COLUMN is_adult TINYINT(1) DEFAULT 0");
                            }
                        } catch (Exception $e) {
                            // ignore
                            $hasIsAdult = false;
                        }

                        if ($story_id) {
                    // Update story
                            $update_cover = $cover_path ? ', cover = ?' : '';
                            if ($hasTags) {
                                $params = [$title, $slug, $description, $tags, $genres, $content_warnings, $is_adult, $content_type, $is_fanfiction, $fanfic_source, $status, $story_id, $user_id];
                                if ($cover_path) array_splice($params, -2, 0, [$cover_path]);
                                $stmt = $pdo->prepare("UPDATE stories SET title = ?, slug = ?, description = ?, tags = ?, genres = ?, content_warnings = ?, is_adult = ?, content_type = ?, is_fanfiction = ?, fanfic_source = ?, status = ? $update_cover WHERE id = ? AND author_id = ?");
                                error_log("DEBUG UPDATE with tags - params: " . json_encode($params) . " | tags value: " . ($tags ?? 'NULL'));
                            } else {
                                $params = [$title, $slug, $description, $genres, $content_warnings, $is_adult, $content_type, $is_fanfiction, $fanfic_source, $status, $story_id, $user_id];
                                if ($cover_path) array_splice($params, -2, 0, [$cover_path]);
                                $stmt = $pdo->prepare("UPDATE stories SET title = ?, slug = ?, description = ?, genres = ?, content_warnings = ?, is_adult = ?, content_type = ?, is_fanfiction = ?, fanfic_source = ?, status = ? $update_cover WHERE id = ? AND author_id = ?");
                            }
                            $stmt->execute($params);
                    $success = 'Story updated successfully!';
                    
                    // Reload story data to show what was just saved
                    $stmt = $pdo->prepare('SELECT * FROM stories WHERE id = ? AND author_id = ?');
                    $stmt->execute([$story_id, $user_id]);
                    $story = $stmt->fetch();
                    
                    // Redirect to the same page to reload all data fresh
                    header('Location: ' . site_url('/pages/write-story.php?edit=' . $story_id . '&saved=1'));
                    exit;
                } else {
                    // Create new story
                            if ($hasTags) {
                                $stmt = $pdo->prepare('INSERT INTO stories (title, slug, description, tags, genres, content_warnings, is_adult, content_type, is_fanfiction, fanfic_source, status, cover, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                                $stmt->execute([$title, $slug, $description, $tags, $genres, $content_warnings, $is_adult, $content_type, $is_fanfiction, $fanfic_source, $status, $cover_path, $user_id]);
                            } else {
                                $stmt = $pdo->prepare('INSERT INTO stories (title, slug, description, genres, content_warnings, is_adult, content_type, is_fanfiction, fanfic_source, status, cover, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                                $stmt->execute([$title, $slug, $description, $genres, $content_warnings, $is_adult, $content_type, $is_fanfiction, $fanfic_source, $status, $cover_path, $user_id]);
                            }
                    $story_id = $pdo->lastInsertId();
                    
                    // Reload story data to show what was just created
                    $stmt = $pdo->prepare('SELECT * FROM stories WHERE id = ? AND author_id = ?');
                    $stmt->execute([$story_id, $user_id]);
                    $story = $stmt->fetch();
                    
                    // If joining a competition, create entry
                    if ($competition_id > 0) {
                        try {
                            // Create competition_entries table if not exists
                            $pdo->exec("CREATE TABLE IF NOT EXISTS competition_entries (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                competition_id INT NOT NULL,
                                story_id INT NOT NULL,
                                user_id INT NOT NULL,
                                status ENUM('pending','submitted','approved','disqualified') DEFAULT 'pending',
                                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                total_score DECIMAL(10,2) DEFAULT 0,
                                INDEX idx_comp (competition_id),
                                INDEX idx_story (story_id),
                                INDEX idx_user (user_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            
                            // Check if story_id column exists, if not add it (for backwards compat with book_id)
                            try {
                                $pdo->exec("ALTER TABLE competition_entries ADD COLUMN IF NOT EXISTS story_id INT NOT NULL DEFAULT 0");
                            } catch (Exception $e2) {}
                            
                            $stmt = $pdo->prepare("INSERT INTO competition_entries (competition_id, story_id, user_id, status) VALUES (?, ?, ?, 'pending')");
                            $stmt->execute([$competition_id, $story_id, $user_id]);
                        } catch (Exception $e) {
                            // Log but don't fail - competition entry is optional
                            error_log('Failed to create competition entry: ' . $e->getMessage());
                        }
                    }
                    
                    $success = 'Story created! Now add your first chapter.';
                    
                    // Notify followers that author published a new story
                    try {
                        $fstmt = $pdo->prepare("SELECT follower_id FROM follows WHERE following_id = ?");
                        $fstmt->execute([$user_id]);
                        $followers = $fstmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($followers)) {
                            foreach ($followers as $fid) {
                                if (function_exists('notify')) {
                                    notify($pdo, $fid, $user_id, 'new_story', "New story published: " . substr($title, 0, 80), "/pages/book.php?id=" . $story_id);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Non-fatal
                    }
                    
                    // Redirect to edit page so user can see what was saved and add chapters
                    header('Location: ' . site_url('/pages/write-story.php?edit=' . $story_id . '&saved=1'));
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
// Load categories
try {
    $categories = $pdo->query('SELECT DISTINCT name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
}
if (empty($categories)) {
    $categories = ['Fiction', 'Fantasy', 'Mystery', 'Romance', 'Sci-Fi', 'Adventure', 'Horror'];
}

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>

<?php
    $page_title = ($story_id ? 'Edit Story' : 'Write a Story') . ' - Scroll Novels';
    $page_head = '<script src="https://cdn.tailwindcss.com"></script>'
        . '<script>tailwind.config={darkMode:"class"};</script>'
        . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
        . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">'
        . '<script src="' . asset_url('js/theme.js') . '" defer></script>'
        . '<style>:root{--transition-base:200ms ease-in-out}body{transition:background-color var(--transition-base),color var(--transition-base)}</style>';

    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-4xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2"><?= $story_id ? '✏️ Edit Story' : '✍️ Write a New Story' ?></h1>
            <p class="text-gray-600 dark:text-gray-400">Create or update your story details below</p>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-400">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg text-green-700 dark:text-green-400">
                ✓ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Story Form -->
        <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 rounded-lg p-8 shadow border border-emerald-200 dark:border-emerald-900 space-y-6">
            <?php if ($competition_id > 0): ?>
                <input type="hidden" name="competition_id" value="<?= $competition_id ?>">
            <?php endif; ?>
            
            <?php if ($competition): ?>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                    <p class="text-blue-800 dark:text-blue-300"><strong>📝 Participating in:</strong> <?= htmlspecialchars($competition['title'] ?? '') ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Story Title *</label>
                <input type="text" id="title" name="title" required value="<?= htmlspecialchars($story['title'] ?? '') ?>" placeholder="Enter your story title" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            </div>

            <!-- Cover Image -->
            <div>
                <label for="cover" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Cover Image</label>
                <input type="file" id="cover" name="cover" accept="image/*" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">PNG, JPG, GIF, or WebP. Max 5MB.</p>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Write a compelling description of your story..." class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600"><?= htmlspecialchars($story['description'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Language & Format -->
                <div>
                    <label for="language" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Primary Language</label>
                    <select id="language" name="language" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="English" selected>English</option>
                        <option value="Spanish">Spanish</option>
                        <option value="Portuguese">Portuguese</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="format" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Format</label>
                    <select id="format" name="format" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="novel" selected>Novel</option>
                        <option value="webtoon">Webtoon</option>
                    </select>
                </div>
                <!-- Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Category</label>
                    <select id="category" name="category" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($story['category'] ?? 'fiction') === $cat ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="draft" <?= ($story['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft (Private)</option>
                        <option value="published" <?= ($story['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (Public)</option>
                    </select>
                </div>
            </div>

            <!-- Genres (Select up to 4) -->
            <div>
                <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">📚 Genres (Select up to 4)</label>
                <div id="genres-container" class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <p class="text-gray-500">Loading genres...</p>
                </div>
                <input type="hidden" id="selected-genres" name="selected_genres">
            </div>

            <!-- Story Tags -->
            <div>
                <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">🏷️ Tags</label>
                <div id="tags-container" class="space-y-3">
                    <p class="text-gray-500">Loading tags...</p>
                </div>
                <input type="hidden" id="selected-tags" name="selected_tags">
            </div>

            <!-- Content Warnings -->
            <div>
                <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">⚠️ Content Warnings</label>
                <div id="warnings-container" class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <p class="text-gray-500">Loading warnings...</p>
                </div>
                <input type="hidden" id="selected-warnings" name="content_warnings">
            </div>

            <!-- Novel/Webtoon/Fanfic Type -->
            <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-900 rounded-lg p-4">
                <label class="block text-sm font-medium text-indigo-700 dark:text-indigo-400 mb-2">📚 Content Format</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="content_type" value="novel" <?= ($story['content_type'] ?? 'novel') === 'novel' ? 'checked' : '' ?> class="w-4 h-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">📖 Novel (Text)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="content_type" value="webtoon" <?= ($story['content_type'] ?? '') === 'webtoon' ? 'checked' : '' ?> class="w-4 h-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">🎨 Webtoon (Comic/Visual)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="content_type" value="fanfic" <?= ($story['content_type'] ?? '') === 'fanfic' ? 'checked' : '' ?> class="w-4 h-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">✨ Fanfic (Fan Story)</span>
                    </label>
                </div>
            </div>

            <!-- Fanfiction Flag -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <label class="block text-sm font-medium text-blue-700 dark:text-blue-400 mb-2">📖 Is this a fanfiction?</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="is_fanfiction" value="0" <?= ($story['is_fanfiction'] ?? 0) == 0 ? 'checked' : '' ?> class="w-4 h-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">No, this is original content</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="is_fanfiction" value="1" <?= ($story['is_fanfiction'] ?? 0) == 1 ? 'checked' : '' ?> class="w-4 h-4">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Yes, uses someone else's characters/world</span>
                    </label>
                    <input type="text" name="fanfic_source" id="fanfic-source-input" value="<?= htmlspecialchars($story['fanfic_source'] ?? '') ?>" placeholder="Source (e.g., 'Based on Harry Potter universe')" class="w-full px-4 py-2 border border-blue-300 dark:border-blue-700 rounded-lg bg-white dark:bg-gray-700 text-blue-900 dark:text-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600 text-sm <?= ($story['is_fanfiction'] ?? 0) == 1 ? '' : 'hidden' ?>">
                </div>
            </div>

            <!-- Mature Content -->
            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_adult" name="is_adult" <?= ($story['is_adult'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 rounded border-emerald-300 text-emerald-600">
                <label for="is_adult" class="text-sm text-emerald-700 dark:text-emerald-400">Mature Content (18+)</label>
            </div>

            <!-- Submit -->
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                    <?= $story_id ? '💾 Update Story' : '✨ Create Story' ?>
                </button>
                <a href="<?= site_url('/pages/dashboard.php') ?>" class="flex-1 text-center px-6 py-2 border-2 border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium transition-colors">← Back</a>
            </div>
        </form>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('translate-x-full');
        sidebar.classList.add('translate-x-0');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('block');
        }
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('translate-x-full');
        sidebar.classList.remove('translate-x-0');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.classList.remove('block');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e){
            e.preventDefault();
            openSidebar();
        });
    }

    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});

// Load genres and tags on page load
document.addEventListener('DOMContentLoaded', async function() {
    const form = document.querySelector('form');
    
    try {
        const response = await fetch('<?= site_url('/api/get-genres-tags.php') ?>');
        const data = await response.json();
        
        if (!data.success) throw new Error('Failed to load genres and tags');
        
        let selectedGenres = [];
        let selectedTags = [];
        let selectedWarnings = [];
        
        // Pre-populate existing genres if editing
        const existingGenresStr = '<?= addslashes($story['genres'] ?? '') ?>';
        if (existingGenresStr && existingGenresStr.trim()) {
            const existingGenres = existingGenresStr.split(',').map(g => g.trim()).filter(g => g);
            console.log('DEBUG: Existing genres from DB: ' + JSON.stringify(existingGenres));
            selectedGenres = existingGenres.map(genreName => {
                const foundGenre = data.genres.find(g => g.name.toLowerCase() === genreName.toLowerCase());
                return foundGenre ? {id: foundGenre.id, name: foundGenre.name} : {id: null, name: genreName};
            });
            console.log('DEBUG: selectedGenres after mapping: ' + JSON.stringify(selectedGenres));
        }
        
        // Pre-populate existing tags if editing
        const existingTagsStr = '<?= addslashes($story['tags'] ?? '') ?>';
        console.log('DEBUG: existingTagsStr from PHP:', existingTagsStr);
        if (existingTagsStr && existingTagsStr.trim()) {
            const existingTags = existingTagsStr.split(',').map(t => t.trim()).filter(t => t);
            console.log('DEBUG: Existing tags from DB (split):', existingTags);
            console.log('DEBUG: Available API tags:', Object.values(data.tags).flat().map(t => ({ id: t.id, name: t.name })));
            
            selectedTags = existingTags.map(tagName => {
                const allTags = Object.values(data.tags).flat();
                const foundTag = allTags.find(t => t.name.toLowerCase() === tagName.toLowerCase());
                if (foundTag) {
                    console.log(`  ✓ Found "${tagName}" -> ID ${foundTag.id}`);
                    return {id: foundTag.id, name: foundTag.name};
                } else {
                    console.log(`  ✗ NOT FOUND "${tagName}" (will use id: null)`);
                    return {id: null, name: tagName};
                }
            });
            console.log('DEBUG: selectedTags after API lookup:', JSON.stringify(selectedTags));
        } else {
            console.log('DEBUG: No existing tags from PHP');
        }
        
        // Pre-populate existing warnings if editing
        const existingWarningsStr = '<?= addslashes($story['content_warnings'] ?? '') ?>';
        console.log('DEBUG: existingWarningsStr from PHP:', existingWarningsStr);
        if (existingWarningsStr && existingWarningsStr.trim()) {
            const existingWarnings = existingWarningsStr.split(',').map(w => w.trim()).filter(w => w);
            console.log('DEBUG: Existing warnings from DB (split):', existingWarnings);
            
            selectedWarnings = existingWarnings.map(warningName => {
                const allTags = Object.values(data.tags).flat();
                const foundWarning = allTags.find(t => t.name.toLowerCase() === warningName.toLowerCase());
                if (foundWarning) {
                    console.log(`  ✓ Found warning "${warningName}" -> ID ${foundWarning.id}`);
                    return {id: foundWarning.id, name: foundWarning.name};
                } else {
                    console.log(`  ✗ NOT FOUND warning "${warningName}"`);
                    return {id: null, name: warningName};
                }
            });
            console.log('DEBUG: selectedWarnings after API lookup:', JSON.stringify(selectedWarnings));
        } else {
            console.log('DEBUG: No existing warnings from PHP');
        }
        
        // Load genres
        const genresHtml = data.genres.map(g => `
            <button type="button" class="genre-btn relative pr-8 p-3 border-2 border-emerald-300 dark:border-emerald-700 rounded-lg hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-sm font-medium transition text-emerald-900 dark:text-emerald-50" data-genre-id="${g.id}" data-genre-name="${g.name}">
                ${g.emoji || ''} ${g.name}
                <span class="genre-checkmark absolute right-1 top-1 text-green-500 font-bold text-2xl" style="display: none;">✓</span>
            </button>
        `).join('');
        document.getElementById('genres-container').innerHTML = genresHtml;
        
        // Pre-highlight existing genres after rendering
        if (selectedGenres.length > 0) {
            console.log('DEBUG: Pre-highlighting ' + selectedGenres.length + ' genres');
            document.querySelectorAll('.genre-btn').forEach(btn => {
                const genreId = btn.dataset.genreId;
                const genreName = btn.dataset.genreName;
                const isSelected = selectedGenres.some(g => (g.id && g.id == genreId) || g.name === genreName);
                if (isSelected) {
                    console.log('DEBUG: Highlighting genre: ' + genreName);
                    btn.classList.add('bg-emerald-200', 'dark:bg-emerald-900/60', 'border-emerald-600');
                    // Show checkmark
                    const checkmark = btn.querySelector('.genre-checkmark');
                    if (checkmark) checkmark.style.display = 'block';
                }
            });
            // Update the hidden input with current selections
            document.getElementById('selected-genres').value = JSON.stringify(selectedGenres);
        }
        
        // Genre selection (max 4)
        document.querySelectorAll('.genre-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.dataset.genreId;
                const name = this.dataset.genreName;
                const checkmark = this.querySelector('.genre-checkmark');
                
                if (this.classList.contains('bg-emerald-200')) {
                    this.classList.remove('bg-emerald-200', 'dark:bg-emerald-900/60', 'border-emerald-600');
                    selectedGenres = selectedGenres.filter(g => g.id != id);
                    // Hide checkmark
                    if (checkmark) checkmark.style.display = 'none';
                } else if (selectedGenres.length < 4) {
                    this.classList.add('bg-emerald-200', 'dark:bg-emerald-900/60', 'border-emerald-600');
                    selectedGenres.push({id, name});
                    // Show checkmark
                    if (checkmark) checkmark.style.display = 'block';
                } else {
                    alert('You can select up to 4 genres');
                    return;
                }
                
                document.getElementById('selected-genres').value = JSON.stringify(selectedGenres);
            });
        });
        
        // Separate tags by category
        const tagsHtml = Object.entries(data.tags).map(([category, tags]) => `
            <div>
                <h4 class="font-semibold text-emerald-700 dark:text-emerald-400 mb-2">${category === 'tag' ? '🏷️ Tags' : category === 'warning' ? '⚠️ Warnings' : '📌 Content'}</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    ${tags.map(t => `
                        <label class="tag-label flex items-center gap-2 text-sm cursor-pointer p-2 pr-8 rounded transition border-2 border-transparent hover:border-emerald-300 dark:hover:border-emerald-700 relative" data-tag-id="${t.id}" data-tag-name="${t.name}">
                            <input type="checkbox" class="tag-checkbox w-4 h-4 ${category === 'warning' ? 'warning-checkbox' : 'tag-checkbox'}" data-tag-id="${t.id}" data-tag-name="${t.name}" data-category="${category}">
                            <span class="text-emerald-900 dark:text-emerald-50">${t.name}</span>
                            <span class="tag-checkmark absolute right-1 top-1 text-green-500 font-bold text-2xl" style="display: none;">✓</span>
                        </label>
                    `).join('')}
                </div>
            </div>
        `).join('');
        document.getElementById('tags-container').innerHTML = tagsHtml;
        
        // Pre-check existing tags after rendering
        if (selectedTags.length > 0) {
            console.log('DEBUG: Pre-checking ' + selectedTags.length + ' tags');
            console.log('DEBUG: Full selectedTags:', JSON.stringify(selectedTags, null, 2));
            
            const checkboxes = document.querySelectorAll('.tag-checkbox:not(.warning-checkbox)');
            console.log('DEBUG: Found ' + checkboxes.length + ' checkboxes to compare');
            
            checkboxes.forEach(checkbox => {
                const checkboxId = parseInt(checkbox.dataset.tagId);
                const checkboxName = checkbox.dataset.tagName;
                console.log('DEBUG: Checkbox - ID: ' + checkboxId + ', Name: ' + checkboxName);
                
                let found = false;
                for (let i = 0; i < selectedTags.length; i++) {
                    const t = selectedTags[i];
                    const idMatch = t.id && parseInt(t.id) === checkboxId;
                    const nameMatch = t.name && t.name.toLowerCase() === checkboxName.toLowerCase();
                    if (idMatch || nameMatch) {
                        console.log('  ✓ MATCH! ID match: ' + idMatch + ', Name match: ' + nameMatch);
                        found = true;
                        break;
                    }
                }
                
                if (found) {
                    checkbox.checked = true;
                    const label = checkbox.closest('.tag-label');
                    label.classList.add('bg-emerald-100', 'dark:bg-emerald-900/30', 'border-emerald-400', 'dark:border-emerald-600');
                    const checkmark = label.querySelector('.tag-checkmark');
                    if (checkmark) {
                        checkmark.style.display = 'block';
                        console.log('  ✓ Checkmark shown');
                    }
                } else {
                    console.log('  ✗ No match');
                }
            });
            document.getElementById('selected-tags').value = JSON.stringify(selectedTags);
        } else {
            console.log('DEBUG: No selectedTags to pre-check');
        }
        
        // FINAL SAFETY CHECK: Make sure all checkmarks are visible if checkbox is checked
        setTimeout(() => {
            document.querySelectorAll('.tag-checkbox').forEach(checkbox => {
                const checkmark = checkbox.closest('.tag-label').querySelector('.tag-checkmark');
                if (checkbox.checked && checkmark && checkmark.style.display === 'none') {
                    console.log('SAFETY FIX: Showing checkmark for', checkbox.dataset.tagName);
                    checkmark.style.display = 'block';
                }
            });
        }, 100);
        
        // Pre-check existing warnings after rendering
        if (selectedWarnings.length > 0) {
            console.log('DEBUG: Pre-checking ' + selectedWarnings.length + ' warnings with IDs:', selectedWarnings.map(w => w.id || 'null'));
            document.querySelectorAll('.warning-checkbox').forEach(checkbox => {
                const checkboxId = parseInt(checkbox.dataset.tagId);
                const checkboxName = checkbox.dataset.tagName;
                
                const found = selectedWarnings.find(w => {
                    if (w.id && parseInt(w.id) === checkboxId) return true;
                    if (w.name && w.name.toLowerCase() === checkboxName.toLowerCase()) return true;
                    return false;
                });
                
                if (found) {
                    console.log('✓ MATCH: Warning ID ' + checkboxId + ' (' + checkboxName + ')');
                    checkbox.checked = true;
                    const label = checkbox.closest('.tag-label');
                    label.classList.add('bg-red-100', 'dark:bg-red-900/30', 'border-red-400', 'dark:border-red-600');
                    const checkmark = label.querySelector('.tag-checkmark');
                    if (checkmark) {
                        checkmark.style.display = 'block';
                        console.log('✓ Checkmark shown for warning: ' + checkboxName);
                    }
                } else {
                    console.log('✗ No match for Warning ID ' + checkboxId + ' (' + checkboxName + ')');
                }
            });
        } else {
            console.log('DEBUG: No selectedWarnings to pre-check');
        }
        
        // FINAL SAFETY CHECK: Make sure all checkmarks are visible if checkbox is checked
        setTimeout(() => {
            document.querySelectorAll('.warning-checkbox').forEach(checkbox => {
                const checkmark = checkbox.closest('.tag-label').querySelector('.tag-checkmark');
                if (checkbox.checked && checkmark && checkmark.style.display === 'none') {
                    console.log('SAFETY FIX: Showing checkmark for warning', checkbox.dataset.tagName);
                    checkmark.style.display = 'block';
                }
            });
        }, 100);
        
        // Tag selection with visual feedback
        document.querySelectorAll('.tag-checkbox:not(.warning-checkbox)').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.closest('.tag-label');
                const checkmark = label.querySelector('.tag-checkmark');
                console.log('Tag changed:', this.dataset.tagName, 'checked:', this.checked, 'checkmark:', checkmark);
                if (this.checked) {
                    selectedTags.push({id: this.dataset.tagId, name: this.dataset.tagName});
                    // Add highlight
                    label.classList.add('bg-emerald-100', 'dark:bg-emerald-900/30', 'border-emerald-400', 'dark:border-emerald-600');
                    // Show checkmark
                    if (checkmark) {
                        console.log('Showing checkmark for tag');
                        checkmark.style.display = 'block';
                    }
                } else {
                    selectedTags = selectedTags.filter(t => t.id != this.dataset.tagId);
                    // Remove highlight
                    label.classList.remove('bg-emerald-100', 'dark:bg-emerald-900/30', 'border-emerald-400', 'dark:border-emerald-600');
                    // Hide checkmark
                    if (checkmark) {
                        console.log('Hiding checkmark for tag');
                        checkmark.style.display = 'none';
                    }
                }
                document.getElementById('selected-tags').value = JSON.stringify(selectedTags);
            });
        });
        
        // Warning checkboxes with visual feedback
        document.querySelectorAll('.warning-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const label = this.closest('.tag-label');
                const checkmark = label.querySelector('.tag-checkmark');
                if (this.checked) {
                    selectedWarnings.push({id: this.dataset.tagId, name: this.dataset.tagName});
                    // Add highlight in red
                    label.classList.add('bg-red-100', 'dark:bg-red-900/30', 'border-red-400', 'dark:border-red-600');
                    // Show checkmark
                    if (checkmark) checkmark.style.display = 'block';
                } else {
                    selectedWarnings = selectedWarnings.filter(w => w.id != this.dataset.tagId);
                    // Remove highlight
                    label.classList.remove('bg-red-100', 'dark:bg-red-900/30', 'border-red-400', 'dark:border-red-600');
                    // Hide checkmark
                    if (checkmark) checkmark.style.display = 'none';
                }
                document.getElementById('selected-warnings').value = JSON.stringify(selectedWarnings);
            });
        });
        
        // Fanfiction toggle
        document.querySelectorAll('input[name="is_fanfiction"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('fanfic-source-input').classList.toggle('hidden', this.value === '0');
            });
        });
        
        // Form submission - save metadata after form is posted
        if (form) {
            form.addEventListener('submit', async function(e) {
                // Let the normal form submission happen, but then save metadata
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Get story ID from response or URL
                const urlParams = new URLSearchParams(window.location.search);
                const editId = parseInt(urlParams.get('edit')) || 0;
                
                if (editId) {
                    // Save metadata for edited story
                    const genreIds = selectedGenres.map(g => parseInt(g.id));
                    const tagIds = selectedTags.map(t => parseInt(t.id));
                    const warningIds = selectedWarnings.map(w => parseInt(w.id));
                    
                    try {
                        const metaResponse = await fetch('<?= site_url('/api/save-story-metadata.php') ?>', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                story_id: editId,
                                genres: genreIds,
                                tags: tagIds,
                                warnings: warningIds,
                                is_fanfiction: parseInt(document.querySelector('input[name="is_fanfiction"]:checked').value),
                                fanfic_source: document.getElementById('fanfic-source-input').value
                            })
                        });
                        const metaData = await metaResponse.json();
                        console.log('Metadata saved:', metaData);
                    } catch (err) {
                        console.error('Error saving metadata:', err);
                    }
                }
            });
        }
        
    } catch (error) {
        console.error('Error loading genres/tags:', error);
        // Fallback: render comprehensive static lists so the UI is usable even if API fails
        const fallbackGenres = ['Action','Adventure','Comedy','Contemporary','Crime','Drama','Fantasy','Forbidden Love','Ghost Stories','Historical','Horror','LGBTQ+','Magic','Mystery','Paranormal','Psychological','Romance','Sci-Fi','Short Story','Slice of Life','Superhero','Supernatural','Thriller','Time Travel','Tragedy','Urban Fantasy','Western','Wuxia','Cultivation'];
        document.getElementById('genres-container').innerHTML = fallbackGenres.map(g => `
                <button type="button" class="genre-btn p-3 border-2 border-emerald-300 dark:border-emerald-700 rounded-lg hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-sm font-medium transition text-emerald-900 dark:text-emerald-50" data-genre-name="${g}">${g}</button>`).join('');

        const fallbackTagsByCategory = {
            'tag': ['Anti-Hero Lead','Dragons','Elves','Enemies to Lovers','Female Lead','Forced Proximity','Gay Romance','Godly','HFY (Humanity First)','Harem','Immortal','Lesbian Romance','Litrpg','Magic System','Male Lead','Mutants','NonHuman','Possession','Progression','Reincarnation','Reverse Harem','Romance Subplot','Slow Burn','Slice of Life','Soul Mates','Space Opera','Steampunk','System Administrator','Time Travel','Transmigration','Underdog','Vampires','Werewolves'],
            'warning': ['Alcohol Abuse','Bullying','Child Abuse','Death','Domestic Violence','Drug Abuse','Eating Disorder','Genocide','Graphic Violence','Gun Violence','Incest','Kidnapping','Pedophilia','Profanity','Rape','Self-Harm','Sexual Assault','Sexual Content','Slavery','Suicide','Suicide Attempt','Torture','Trafficking','War']
        };
        
        document.getElementById('tags-container').innerHTML = Object.entries(fallbackTagsByCategory).map(([category, items]) => `
            <div>
                <h4 class="font-semibold text-emerald-700 dark:text-emerald-400 mb-2">${category === 'tag' ? '🏷️ Tags' : '⚠️ Content Warnings'}</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    ${items.map(t => `
                        <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-2 rounded transition">
                            <input type="checkbox" class="${category === 'warning' ? 'warning-checkbox' : 'tag-checkbox'} w-4 h-4" data-tag-name="${t}">
                            <span class="text-emerald-900 dark:text-emerald-50">${t}</span>
                        </label>
                    `).join('')}
                </div>
            </div>
        `).join('');

        // Re-apply selection handlers for fallback elements
        let selectedGenres = [];
        let selectedTags = [];
        let selectedWarnings = [];

        document.querySelectorAll('.genre-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const name = this.dataset.genreName;
                if (this.classList.contains('bg-emerald-200')) {
                    this.classList.remove('bg-emerald-200');
                    selectedGenres = selectedGenres.filter(g => g !== name);
                } else if (selectedGenres.length < 4) {
                    this.classList.add('bg-emerald-200');
                    selectedGenres.push(name);
                } else {
                    alert('You can select up to 4 genres');
                }
                document.getElementById('selected-genres').value = JSON.stringify(selectedGenres.map(g => ({name:g})));
            });
        });

        document.querySelectorAll('.tag-checkbox').forEach(cb => cb.addEventListener('change', function(){
            if (this.checked) selectedTags.push(this.dataset.tagName || this.dataset.tagName === undefined ? this.nextElementSibling?.textContent.trim() : '');
            else selectedTags = selectedTags.filter(t => t !== (this.dataset.tagName || this.nextElementSibling?.textContent.trim()));
            document.getElementById('selected-tags').value = JSON.stringify(selectedTags.map(t=>({name:t}))); 
        }));

        document.querySelectorAll('.warning-checkbox').forEach(cb => cb.addEventListener('change', function(){
            if (this.checked) selectedWarnings.push(this.dataset.tagName || this.nextElementSibling?.textContent.trim());
            else selectedWarnings = selectedWarnings.filter(t => t !== (this.dataset.tagName || this.nextElementSibling?.textContent.trim()));
            document.getElementById('selected-warnings').value = JSON.stringify(selectedWarnings.map(t=>({name:t}))); 
        }));
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
