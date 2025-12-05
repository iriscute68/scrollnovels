<?php
// pages/points-purchase.php - Buy points via chat with admin

if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Buy Points';
require_once dirname(__DIR__) . '/includes/header.php';

// Get user info
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get packages
$packages = [
    1 => ['price' => 10, 'points' => 1100, 'name' => '1,100 Points'],
    2 => ['price' => 25, 'points' => 3000, 'name' => '3,000 Points'],
    3 => ['price' => 50, 'points' => 6500, 'name' => '6,500 Points'],
    4 => ['price' => 100, 'points' => 14000, 'name' => '14,000 Points'],
];

// Get or create purchase chat room
$package_id = (int)($_GET['package'] ?? 0);
if (!isset($packages[$package_id])) {
    $package_id = 1;
}
$package = $packages[$package_id];

// Create a purchase request/chat room
try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_purchase_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        admin_id INT,
        package_id INT NOT NULL,
        points INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        payment_proof VARCHAR(500),
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_purchase_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT,
        image_url VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES point_purchase_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Check if there's an existing pending request
    $stmt = $pdo->prepare("
        SELECT id FROM point_purchase_requests 
        WHERE user_id = ? AND status = 'pending' 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $existing_request = $stmt->fetch();
    
    if (!$existing_request) {
        // Create new request
        $stmt = $pdo->prepare("
            INSERT INTO point_purchase_requests (user_id, package_id, points, price, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $package_id, $package['points'], $package['price']]);
        $request_id = $pdo->lastInsertId();
    } else {
        $request_id = $existing_request['id'];
    }
    
    // Get messages for this request
    $stmt = $pdo->prepare("
        SELECT ppm.*, u.username
        FROM point_purchase_messages ppm
        LEFT JOIN users u ON ppm.user_id = u.id
        WHERE ppm.request_id = ?
        ORDER BY ppm.created_at ASC
    ");
    $stmt->execute([$request_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get request details
    $stmt = $pdo->prepare("SELECT * FROM point_purchase_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Points purchase error: ' . $e->getMessage());
    $request_id = 0;
    $messages = [];
    $request = null;
}
?>

<main class="flex-1">
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">💳 Buy Points</h1>
        <p class="text-gray-600 dark:text-gray-400">Chat with admin to verify payment and add points</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Package Selection -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <h2 class="text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-4">📦 Packages</h2>
                <div class="space-y-2">
                    <?php foreach ($packages as $id => $pkg): ?>
                    <a href="<?= site_url('/pages/points-purchase.php?package=' . $id) ?>" 
                       class="block p-3 rounded-lg border-2 <?= $package_id == $id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30' : 'border-gray-200 dark:border-gray-700 hover:border-emerald-300' ?> text-gray-900 dark:text-white transition">
                        <div class="font-bold"><?= $pkg['name'] ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">$<?= $pkg['price'] ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-emerald-200 dark:border-emerald-900 flex flex-col h-96">
                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3" id="messagesContainer">
                    <?php if (empty($messages)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>👋 Start the conversation!</p>
                        <p class="text-sm">Send a message to begin discussing your point purchase.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <div class="<?= $msg['user_id'] == $user_id ? 'text-right' : '' ?>">
                            <div class="<?= $msg['user_id'] == $user_id ? 'bg-blue-500 text-white rounded-lg rounded-tr-none' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg rounded-tl-none' ?> px-3 py-2 max-w-xs">
                                <p class="font-semibold text-xs opacity-75"><?= htmlspecialchars($msg['username'] ?? 'Unknown') ?></p>
                                <p><?= htmlspecialchars($msg['message']) ?></p>
                                <?php if (!empty($msg['image_url'])): ?>
                                <img src="<?= htmlspecialchars($msg['image_url']) ?>" alt="Proof" class="mt-2 max-w-32 rounded">
                                <?php endif; ?>
                                <p class="text-xs opacity-50 mt-1"><?= date('M d, H:i', strtotime($msg['created_at'])) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Message Input -->
                <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                    <form id="messageForm" class="space-y-2">
                        <textarea id="messageText" placeholder="Describe your payment details or ask questions..." class="w-full p-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none" rows="2"></textarea>
                        <div class="flex gap-2">
                            <input type="file" id="proofImage" accept="image/*" class="flex-1 text-sm">
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<script>
const requestId = <?= json_encode($request_id) ?>;
const userId = <?= json_encode($user_id) ?>;

document.getElementById('messageForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const messageText = document.getElementById('messageText').value.trim();
    const fileInput = document.getElementById('proofImage');
    
    if (!messageText && !fileInput.files.length) {
        alert('Please enter a message or attach an image');
        return;
    }
    
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('message', messageText);
    if (fileInput.files.length > 0) {
        formData.append('image', fileInput.files[0]);
    }
    
    try {
        const response = await fetch('<?= site_url('/api/send-points-message.php') ?>', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        if (data.success) {
            document.getElementById('messageText').value = '';
            fileInput.value = '';
            
            // Reload messages
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to send message'));
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error sending message: ' + e.message);
    }
});

// Auto scroll to bottom
const container = document.getElementById('messagesContainer');
if (container) {
    container.scrollTop = container.scrollHeight;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
