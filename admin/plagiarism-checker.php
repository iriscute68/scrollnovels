<?php
// admin/plagiarism-checker.php - Plagiarism scan (merged; similar_text vs DB)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if (!isApprovedAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
include __DIR__ . '/../includes/header.php';

$page_title = 'Plagiarism Checker';
$success = $error = $results = [];

// Handle scan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $text = trim($_POST['text'] ?? '');
        $threshold = (int)($_POST['threshold'] ?? 75);  // %

        if (empty($text)) {
            $error = 'Enter text to scan.';
        } else {
            try {
                // Fetch all published stories content (for scan)
                $stmt = $pdo->prepare('SELECT id, title, slug, content FROM stories WHERE status = "published" AND content IS NOT NULL');
                $stmt->execute();
                $stories = $stmt->fetchAll();

                $results = [];
                $words = preg_split('/\s+/', strtolower(strip_tags($text)));  // Clean words
                $text_len = count($words);

                foreach ($stories as $story) {
                    $story_words = preg_split('/\s+/', strtolower(strip_tags($story['content'] ?? '')));
                    $story_len = count($story_words);
                    if ($story_len < 50) continue;  // Skip short

                    $similarity = 0;
                    $matches = 0;
                    for ($i = 0; $i < min($text_len, $story_len); $i++) {
                        if ($words[$i] === $story_words[$i]) $matches++;
                    }
                    $similarity = ($matches / max($text_len, $story_len)) * 100;

                    if ($similarity >= $threshold) {
                        $results[] = [
                            'title' => $story['title'],
                            'slug' => $story['slug'],
                            'similarity' => round($similarity, 1),
                            'match_words' => $matches
                        ];
                    }
                }

                if (empty($results)) {
                    $success = "No matches above $threshold% – Original!";
                } else {
                    $success = count($results) . " potential matches found.";
                }
            } catch (PDOException $e) {
                error_log('Plagiarism Scan Error: ' . $e->getMessage());
                $error = 'Scan failed.';
            }
        }
    }
}

// Categories for context (optional)
$categories = $pdo->query('SELECT DISTINCT category FROM stories')->fetchAll(PDO::FETCH_COLUMN) ?? [];
?>

<link rel="stylesheet" href="<?= asset_url('css/site-theme.compiled.css') ?>">
<main class="max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6 text-emerald-400">Plagiarism Checker</h1>
    <p class="text-gray-300 mb-8">Paste content to scan against published stories (threshold: 75% similarity).</p>

    <?php if ($error): ?>
        <div class="bg-red-900/20 text-red-400 p-3 rounded mb-4 border border-red-500"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-900/20 text-green-400 p-3 rounded mb-4 border border-green-500"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Scan Form -->
    <form method="POST" class="bg-gray-800 p-6 rounded-lg border border-gray-700 space-y-4 mb-8">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div>
            <label for="text" class="block text-sm font-medium mb-2 text-gray-300">Content to Scan</label>
            <textarea id="text" name="text" rows="10" required class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md focus:ring-2 focus:ring-emerald-500" placeholder="Paste your story chapter here..."><?= htmlspecialchars($_POST['text'] ?? '') ?></textarea>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="threshold" class="block text-sm font-medium mb-2 text-gray-300">Similarity Threshold (%)</label>
                <input type="number" id="threshold" name="threshold" value="<?= htmlspecialchars($_POST['threshold'] ?? 75) ?>" min="50" max="100" class="w-full p-3 bg-gray-700 border rounded">
            </div>
            <div class="flex items-end">
                <label class="block text-sm font-medium mb-2 text-gray-300">Scan Scope</label>
                <p class="text-sm text-gray-400">All published stories (<?= count($pdo->query('SELECT COUNT(*) FROM stories WHERE status="published"')->fetchColumn()) ?> total)</p>
            </div>
        </div>
        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 rounded-md font-semibold text-white transition">Scan for Plagiarism</button>
    </form>

    <!-- Results Table (if scanned) -->
    <?php if (!empty($results)): ?>
        <h2 class="text-2xl font-bold mb-4">Potential Matches</h2>
        <div class="overflow-x-auto">
            <table class="w-full bg-gray-800 border border-gray-700 rounded">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="p-3 text-left">Story Title</th>
                        <th class="p-3 text-left">Similarity</th>
                        <th class="p-3 text-left">Matched Words</th>
                        <th class="p-3 text-center">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr class="border-t border-gray-700 hover:bg-gray-700">
                            <td class="p-3"><?= htmlspecialchars($result['title']) ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-xs bg-red-600 text-white"><?= $result['similarity'] ?>%</span>
                            </td>
                            <td class="p-3"><?= $result['match_words'] ?></td>
                            <td class="p-3 text-center">
                                <a href="<?= SITE_URL ?>/book/<?= htmlspecialchars($result['slug']) ?>" class="text-emerald-400 hover:underline">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-sm text-gray-400">Tip: Review matches manually. Threshold can be adjusted for sensitivity.</p>
    <?php endif; ?>
</main>

<script>
// JS: Async scan progress (merged from plagiarism-checker.js)
document.querySelector('form')?.addEventListener('submit', e => {
    const btn = e.target.querySelector('button[type="submit"]');
    btn.textContent = 'Scanning...';
    btn.disabled = true;
    // Full async in prod; here just visual
    setTimeout(() => {}, 2000);  // Sim delay
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>