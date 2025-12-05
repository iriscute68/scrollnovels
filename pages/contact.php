<?php
// pages/contact.php - Contact support (merged; form log + email stub)
require_once __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';

$page_title = 'Contact Us';
$success = $error = '';

// Prefill subject/message when called from rules/report links
$report_type = $_GET['report'] ?? '';
if ($report_type === 'violation') {
    $_POST['subject'] = $_POST['subject'] ?? 'Report: Website Rule Violation';
    $_POST['message'] = $_POST['message'] ?? "I would like to report a violation of the website rules. Please include details here (link, screenshots, usernames, description).";
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = 'All fields required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } else {
            try {
                // Ensure contacts table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    message LONGTEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('new', 'read', 'resolved') DEFAULT 'new',
                    INDEX (status),
                    INDEX (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                
                // Log to DB
                $stmt = $pdo->prepare('INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, $subject, $message]);
                
                $success = 'Message sent! We\'ll reply soon.';
                
                // Clear form
                $_POST = [];
            } catch (PDOException $e) {
                error_log('Contact Error: ' . $e->getMessage());
                $error = 'Submit failed. Try again.';
            }
        }
    }
}

// Quick login stub (dev only; from quick-login.php)
$is_dev = false;  // Set false in prod - REMOVED DEV LOGIN
?>

<link rel="stylesheet" href="<?= asset_url('css/site-theme.compiled.css') ?>">
<main class="max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6 text-emerald-400">Contact Support</h1>
    <p class="text-gray-300 mb-8">Questions about rules, bugs, or features? We're here to help!</p>

    <?php if ($error): ?>
        <div class="bg-red-900/20 text-red-400 p-3 rounded mb-4 border border-red-500"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-900/20 text-green-400 p-3 rounded mb-4 border border-green-500"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-gray-800 p-6 rounded-lg border border-gray-700 space-y-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-sm font-medium mb-2 text-gray-300">Name</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium mb-2 text-gray-300">Email</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500">
            </div>
        </div>
        <div>
            <label for="subject" class="block text-sm font-medium mb-2 text-gray-300">Subject</label>
            <input type="text" id="subject" name="subject" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500">
        </div>
        <div>
            <label for="message" class="block text-sm font-medium mb-2 text-gray-300">Message</label>
            <textarea id="message" name="message" rows="6" required class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 rounded-md font-semibold text-white transition">Send Message</button>
    </form>

    <?php if ($is_dev): ?>
        <div class="mt-8 p-4 bg-yellow-900/20 border border-yellow-500 rounded text-center">
            <p class="text-yellow-400">Dev Quick Login: <a href="<?= SITE_URL ?>/quick-login.php" class="underline">Click Here</a></p>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
