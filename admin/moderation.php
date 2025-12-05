<?php
/**
 * Moderation Queue Dashboard
 * 
 * Admin/moderator interface for viewing and managing reports
 * Allows filtering, sorting, and taking moderation actions
 */

session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/roles_permissions.php';

// Require login
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
  header('Location: /login.php');
  exit;
}

// Require moderator permission
if (!is_moderator_or_above($pdo, $uid)) {
  http_response_code(403);
  die('Access Denied');
}

// Get filter parameters
$status = $_GET['status'] ?? 'open';
$sort = $_GET['sort'] ?? 'priority';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT r.*, u.username AS reporter_name, u.email AS reporter_email
          FROM reports r
          LEFT JOIN users u ON u.id = r.reporter_id
          WHERE 1=1";
$params = [];

if ($status && $status !== 'all') {
  $query .= " AND r.status = ?";
  $params[] = $status;
}

if ($search) {
  $query .= " AND (r.details LIKE ? OR u.username LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

// Sort
$validSorts = ['priority', 'created_at', 'updated_at'];
$sortCol = in_array($sort, $validSorts) ? $sort : 'priority';
$query .= " ORDER BY $sortCol DESC LIMIT 200";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$statusCounts = [];
foreach (['open', 'in_review', 'resolved', 'dismissed'] as $s) {
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = ?")->execute([$s]);
  $statusCounts[$s] = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = ?")->fetchColumn();
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Moderation Queue - ScrollNovels Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
  <div class="min-h-screen">
    <!-- Header -->
    <div class="bg-gray-800 border-b border-gray-700 p-6">
      <h1 class="text-3xl font-bold text-yellow-500">Moderation Queue</h1>
      <p class="text-gray-400 mt-1">Manage reported content and user violations</p>
    </div>

    <div class="max-w-7xl mx-auto p-6">
      <!-- Filters -->
      <div class="bg-gray-800 rounded-lg p-4 mb-6 border border-gray-700">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Status Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
            <select name="status" class="w-full bg-gray-700 text-white rounded px-3 py-2 border border-gray-600">
              <option value="all">All Statuses</option>
              <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open (<?= $statusCounts['open'] ?? 0 ?>)</option>
              <option value="in_review" <?= $status === 'in_review' ? 'selected' : '' ?>>In Review</option>
              <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
              <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            </select>
          </div>

          <!-- Sort -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Sort By</label>
            <select name="sort" class="w-full bg-gray-700 text-white rounded px-3 py-2 border border-gray-600">
              <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
              <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Newest</option>
              <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Recently Updated</option>
            </select>
          </div>

          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search reports..." 
                   class="w-full bg-gray-700 text-white rounded px-3 py-2 border border-gray-600">
          </div>

          <div class="md:col-span-3">
            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded font-medium">
              Filter
            </button>
          </div>
        </form>
      </div>

      <!-- Reports Table -->
      <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700">
        <table class="w-full">
          <thead class="bg-gray-700 border-b border-gray-600">
            <tr>
              <th class="px-6 py-3 text-left text-sm font-semibold">ID</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Target</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Reason</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Reporter</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Priority</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Status</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Date</th>
              <th class="px-6 py-3 text-left text-sm font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reports)): ?>
            <tr>
              <td colspan="8" class="px-6 py-4 text-center text-gray-400">No reports found</td>
            </tr>
            <?php endif; ?>

            <?php foreach ($reports as $report): ?>
            <tr class="border-b border-gray-700 hover:bg-gray-750 transition">
              <td class="px-6 py-4 font-mono text-sm">#<?= $report['id'] ?></td>
              <td class="px-6 py-4 text-sm">
                <span class="bg-blue-900 text-blue-100 px-2 py-1 rounded text-xs">
                  <?= htmlspecialchars($report['target_type']) ?> #<?= $report['target_id'] ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm"><?= htmlspecialchars($report['reason_code'] ?? 'N/A') ?></td>
              <td class="px-6 py-4 text-sm text-gray-300">
                <?= $report['reporter_name'] ? htmlspecialchars($report['reporter_name']) : 'Anonymous' ?>
              </td>
              <td class="px-6 py-4">
                <?php 
                  $priorityColor = $report['priority'] >= 4 ? 'bg-red-900 text-red-100' : 
                                  ($report['priority'] >= 3 ? 'bg-orange-900 text-orange-100' : 'bg-gray-600 text-gray-100');
                ?>
                <span class="<?= $priorityColor ?> px-2 py-1 rounded text-xs font-medium">
                  <?= ['Low', 'Medium', 'High', 'Critical'][$report['priority']] ?? 'N/A' ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <span class="<?= $report['status'] === 'resolved' ? 'text-green-400' : 
                            ($report['status'] === 'dismissed' ? 'text-gray-400' : 'text-yellow-400') ?>">
                  <?= ucfirst($report['status']) ?>
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-400">
                <?= date('M d, Y', strtotime($report['created_at'])) ?>
              </td>
              <td class="px-6 py-4 text-sm">
                <a href="/admin/moderation_detail.php?id=<?= $report['id'] ?>" 
                   class="text-yellow-500 hover:text-yellow-400">View</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-4 text-sm text-gray-400">
        Showing <?= count($reports) ?> of many reports
      </div>
    </div>
  </div>
</body>
</html>
<?php
