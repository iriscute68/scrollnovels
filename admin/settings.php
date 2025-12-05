<?php
// admin/settings.php
require_once 'inc/header.php';
$activeTab = 'settings';
require_once 'inc/sidebar.php';
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">Site Settings</h2>
    <p class="text-gray-400">Configure platform-wide settings</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
      <h3>General Settings</h3>
      <form class="space-y-4 mt-4">
        <div>
          <label class="block text-sm font-semibold mb-2">Site Name</label>
          <label class="block text-sm font-semibold text-gray-100 mb-2">Site Name</label>
          <input type="text" value="Scroll Novels" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-100 mb-2">Site URL</label>
          <input type="url" value="https://scrollnovels.com" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-100 mb-2">Support Email</label>
          <input type="email" value="support@scrollnovels.com" class="w-full" />
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </form>
    </div>

    <div class="card">
      <h3>Payment Settings</h3>
      <form class="space-y-4 mt-4">
        <div>
          <label class="block text-sm font-semibold mb-2">Paystack Public Key</label>
          <input type="password" value="pk_live_***" class="w-full" readonly />
        </div>
        <div>
          <label class="block text-sm font-semibold mb-2">Paystack Secret Key</label>
          <input type="password" value="sk_live_***" class="w-full" readonly />
        </div>
        <div>
          <label class="block text-sm font-semibold mb-2">Callback URL</label>
          <input type="url" value="https://scrollnovels.com/admin/paystack_callback.php" class="w-full" readonly />
        </div>
        <p class="text-xs text-gray-500 mt-2">⚠️ Keys are encrypted and cannot be viewed here for security.</p>
      </form>
    </div>

    <div class="card">
      <h3>Content Moderation</h3>
      <form class="space-y-4 mt-4">
        <label class="flex items-center">
          <input type="checkbox" class="mr-2" checked />
          <span class="text-sm">Require admin approval for new stories</span>
        </label>
        <label class="flex items-center">
          <input type="checkbox" class="mr-2" checked />
          <span class="text-sm">Auto-suspend users with 40+ reports</span>
        </label>
        <label class="flex items-center">
          <input type="checkbox" class="mr-2" />
          <span class="text-sm">Enable donation system</span>
        </label>
        <button type="submit" class="btn btn-primary">Update</button>
      </form>
    </div>

    <div class="card">
      <h3>System Info</h3>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between"><span>PHP Version:</span><span class="text-gray-400"><?= phpversion() ?></span></div>
        <div class="flex justify-between"><span>Database:</span><span class="text-gray-400">MySQL</span></div>
        <div class="flex justify-between"><span>Current Time:</span><span class="text-gray-400"><?= date('Y-m-d H:i:s') ?></span></div>
        <div class="flex justify-between"><span>Timezone:</span><span class="text-gray-400"><?= date_default_timezone_get() ?></span></div>
      </div>
    </div>
  </div>
</main>

<?php require_once 'inc/footer.php';

$page_title = 'Site Settings';

// Ensure table
try {
	$pdo->exec("CREATE TABLE IF NOT EXISTS site_contents (k VARCHAR(100) PRIMARY KEY, v LONGTEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

function get_content($pdo, $k) {
	$s = $pdo->prepare('SELECT v FROM site_contents WHERE k = ? LIMIT 1');
	$s->execute([$k]);
	return $s->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
	if (!verify_csrf($_POST['csrf'] ?? '')) { $msg = 'Invalid token'; }
	else {
    $keys = ['privacy','terms','dmca','content_policy','contact_info','socials','website_rules'];
		foreach ($keys as $k) {
			$val = $_POST[$k] ?? '';
			$up = $pdo->prepare('INSERT INTO site_contents (k,v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
			$up->execute([$k, $val]);
		}
		$msg = 'Saved';
	}
}

$privacy = get_content($pdo, 'privacy') ?: '';
$terms = get_content($pdo, 'terms') ?: '';
$dmca = get_content($pdo, 'dmca') ?: '';
$content_policy = get_content($pdo, 'content_policy') ?: '';
$contact_info = get_content($pdo, 'contact_info') ?: '';
$socials = get_content($pdo, 'socials') ?: '';
$website_rules = get_content($pdo, 'website_rules') ?: '';

?>
<main class="max-w-5xl mx-auto p-6">
	<h1 class="text-2xl font-bold mb-4">Site Settings</h1>
	<?php if (!empty($msg)): ?><div class="mb-4 p-3 bg-green-900/20 text-green-300 rounded"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
	<form method="post">
		<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
		<div class="mb-4">
			<label class="block mb-2">Privacy Policy (HTML)</label>
			<textarea name="privacy" rows="6" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($privacy) ?></textarea>
		</div>
		<div class="mb-4">
			<label class="block mb-2">Terms of Service (HTML)</label>
			<textarea name="terms" rows="6" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($terms) ?></textarea>
		</div>
		<div class="mb-4">
			<label class="block mb-2">DMCA (HTML)</label>
			<textarea name="dmca" rows="4" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($dmca) ?></textarea>
		</div>
		<div class="mb-4">
			<label class="block mb-2">Content Policy (HTML)</label>
			<textarea name="content_policy" rows="6" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($content_policy) ?></textarea>
		</div>
		<div class="mb-4">
			<label class="block mb-2">Contact Info / Footer (HTML)</label>
			<textarea name="contact_info" rows="3" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($contact_info) ?></textarea>
		</div>
    <div class="mb-4">
      <label class="block mb-2">Website Rules / Guidelines (HTML)</label>
      <textarea name="website_rules" rows="12" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($website_rules) ?></textarea>
    </div>
		<div class="mb-4">
			<label class="block mb-2">Socials (JSON or HTML)</label>
			<textarea name="socials" rows="3" class="w-full p-2 bg-gray-800 rounded"><?= htmlspecialchars($socials) ?></textarea>
		</div>
		<button name="save_settings" class="px-4 py-2 bg-emerald-600 text-white rounded">Save</button>
	</form>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

