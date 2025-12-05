<?php
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

// Create proclamations tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        images JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (proclamation_id, user_id),
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_proclamation (proclamation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Tables already exist or error
}

// Fetch proclamations from current user AND users they follow
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.profile_image, u.id as author_id,
               COUNT(DISTINCT pl.id) as like_count,
               (SELECT COUNT(*) FROM proclamation_likes WHERE proclamation_id = p.id AND user_id = ?) as user_liked,
               COUNT(DISTINCT pr.id) as reply_count
        FROM proclamations p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN proclamation_likes pl ON p.id = pl.proclamation_id
        LEFT JOIN proclamation_replies pr ON p.id = pr.proclamation_id
        WHERE p.user_id = ? 
           OR p.user_id IN (SELECT following_id FROM followers WHERE follower_id = ?)
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $proclamations = $stmt->fetchAll();
} catch (Exception $e) {
    $proclamations = [];
    error_log('Error fetching proclamations: ' . $e->getMessage());
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 py-8">
    <div class="max-w-2xl mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-8">📢 Proclamations</h1>

        <!-- Create Proclamation Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8 border border-emerald-200 dark:border-emerald-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Share Your Announcement</h2>
            <form id="proclamationForm" class="space-y-4">
                <textarea id="proclamationContent" name="content" 
                          placeholder="What do you want to announce?..."
                          class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                          rows="4" required></textarea>
                
                <!-- Image Upload Section -->
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                    <input type="file" id="proclamationImages" name="images" multiple accept="image/*" class="hidden">
                    <button type="button" onclick="document.getElementById('proclamationImages').click()" 
                            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                        🖼️ Add Images
                    </button>
                    <div id="imagePreview" class="mt-4 grid grid-cols-3 gap-3"></div>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition">
                    📢 Post Proclamation
                </button>
            </form>
        </div>

        <!-- Proclamations List -->
        <div id="proclamationsList" class="space-y-6">
            <?php if (empty($proclamations)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center border border-gray-200 dark:border-gray-700">
                    <p class="text-gray-600 dark:text-gray-400">No proclamations yet. Follow authors to see their announcements!</p>
                </div>
            <?php else: ?>
                <?php foreach ($proclamations as $proc): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-emerald-200 dark:border-emerald-900">
                        <!-- Header -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-200 dark:bg-emerald-900 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($proc['profile_image'])): ?>
                                    <img src="<?= htmlspecialchars($proc['profile_image']) ?>" alt="Avatar" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-lg">👤</span>
                                <?php endif; ?>
                            </div>
                                        <div class="flex-1">
                                            <a href="<?= site_url('/pages/profile.php?user_id=' . (int)$proc['author_id']) ?>" 
                                               class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                                <?= htmlspecialchars($proc['username']) ?>
                                            </a>
                                            <p class="text-xs text-gray-600 dark:text-gray-400"><?= date('M d, Y H:i', strtotime($proc['created_at'])) ?></p>
                                        </div>
                        </div>

                        <!-- Content -->
                        <p class="text-gray-700 dark:text-gray-300 mb-4 whitespace-pre-wrap"><?= htmlspecialchars($proc['body'] ?? '') ?></p>

                        <!-- Images: only shown if column exists and contains JSON -->
                        <?php if (isset($proc['images']) && !empty($proc['images'])): ?>
                            <?php $images = json_decode($proc['images'], true); ?>
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <?php foreach ((array)$images as $image): ?>
                                    <img src="<?= htmlspecialchars($image) ?>" alt="Proclamation image" class="rounded-lg w-full h-auto max-h-300px object-cover">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Stats -->
                        <div class="flex gap-6 py-3 border-t border-b border-gray-200 dark:border-gray-700 mb-4 text-sm text-gray-600 dark:text-gray-400">
                            <span>👍 <?= (int)$proc['like_count'] ?> Likes</span>
                            <span>💬 <?= (int)$proc['reply_count'] ?> Replies</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-4 mb-4">
                            <button onclick="toggleLike(<?= $proc['id'] ?>)" 
                                    class="flex-1 px-4 py-2 rounded-lg font-medium transition <?= $proc['user_liked'] ? 'bg-pink-600 hover:bg-pink-700 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' ?>">
                                <?= $proc['user_liked'] ? '❤️ Liked' : '🤍 Like' ?>
                            </button>
                            <button onclick="toggleReplyForm(<?= $proc['id'] ?>)" 
                                    class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                                💬 Reply
                            </button>
                        </div>

                        <!-- Reply Form -->
                        <div id="reply-form-<?= $proc['id'] ?>" class="hidden bg-gray-50 dark:bg-gray-900 p-4 rounded-lg mb-4 border border-gray-200 dark:border-gray-700">
                            <textarea id="reply-text-<?= $proc['id'] ?>" placeholder="Write your reply..."
                                      class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm resize-none" rows="3"></textarea>
                            <div class="flex gap-2 mt-3">
                                <button onclick="submitReply(<?= $proc['id'] ?>)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">Post Reply</button>
                                <button onclick="toggleReplyForm(<?= $proc['id'] ?>)" class="px-4 py-2 bg-gray-400 hover:bg-gray-500 text-white rounded-lg font-medium transition">Cancel</button>
                            </div>
                        </div>

                        <!-- Replies -->
                        <div id="replies-<?= $proc['id'] ?>" class="space-y-3 bg-gray-50 dark:bg-gray-900 p-4 rounded-lg"></div>
                        <button onclick="loadReplies(<?= $proc['id'] ?>)" class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline mt-2">Show Replies</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
let selectedImages = [];

// Image preview with proper file handling
document.getElementById('proclamationImages').addEventListener('change', function(e) {
    selectedImages = Array.from(e.target.files);
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    selectedImages.forEach((file, idx) => {
        const reader = new FileReader();
        reader.onload = function(event) {
            const div = document.createElement('div');
            div.className = 'relative group';
            div.innerHTML = `
                <img src="${event.target.result}" alt="Preview" class="w-full h-24 object-cover rounded-lg">
                <button type="button" class="absolute top-1 right-1 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition" onclick="removeImage(${idx}); return false;">×</button>
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});

function removeImage(idx) {
    selectedImages.splice(idx, 1);
    document.getElementById('proclamationImages').value = '';
    document.getElementById('proclamationImages').dispatchEvent(new Event('change'));
}

// Submit proclamation with proper FormData
document.getElementById('proclamationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const content = document.getElementById('proclamationContent').value.trim();
    if (!content) {
        alert('Please write something to proclaim!');
        return;
    }
    
    const formData = new FormData();
    formData.append('content', content);
    
    // Add all selected images
    selectedImages.forEach((file, idx) => {
        formData.append('images[]', file);
    });
    
    try {
        const response = await fetch('<?= site_url('/api/proclamations.php') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            alert('✓ Proclamation posted!');
            document.getElementById('proclamationContent').value = '';
            selectedImages = [];
            document.getElementById('imagePreview').innerHTML = '';
            document.getElementById('proclamationImages').value = '';
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('✗ ' + (data.error || 'Error posting proclamation'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

function toggleReplyForm(procId) {
    const form = document.getElementById(`reply-form-${procId}`);
    form.classList.toggle('hidden');
    if (!form.classList.contains('hidden')) {
        document.getElementById(`reply-text-${procId}`).focus();
    }
}

function submitReply(procId) {
    const text = document.getElementById(`reply-text-${procId}`).value.trim();
    if (!text) {
        alert('Please write a reply');
        return;
    }
    
    fetch('<?= site_url('/api/proclamation-replies.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({proclamation_id: procId, content: text})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            alert('✓ Reply posted!');
            document.getElementById(`reply-text-${procId}`).value = '';
            toggleReplyForm(procId);
            loadReplies(procId);
        } else {
            alert('✗ ' + (data.error || 'Error posting reply'));
        }
    });
}

function loadReplies(procId) {
    fetch(`<?= site_url('/api/get-replies.php') ?>?proclamation_id=${procId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const repliesDiv = document.getElementById(`replies-${procId}`);
                if (data.replies && data.replies.length > 0) {
                    repliesDiv.innerHTML = data.replies.map(reply => `
                        <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex gap-2 mb-2">
                                <div class="w-8 h-8 rounded-full bg-blue-200 dark:bg-blue-900 flex items-center justify-center text-sm">👤</div>
                                <div class="flex-1">
                                    <p class="font-semibold text-sm text-gray-900 dark:text-white">${escapeHtml(reply.username)}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">${new Date(reply.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(reply.content)}</p>
                        </div>
                    `).join('');
                } else {
                    repliesDiv.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-sm">No replies yet</p>';
                }
            }
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleLike(procId) {
    fetch('<?= site_url('/api/proclamation-like.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({proclamation_id: procId})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
