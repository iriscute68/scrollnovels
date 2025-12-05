<?php
// admin/donations.php
require_once 'inc/header.php';
$activeTab = 'donations';
require_once 'inc/sidebar.php';
$config = include __DIR__ . '/inc/config.php';

$page = intval($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$donations = [];
$totalDonations = 0;
$totalAmount = 0;
try {
    $has = $pdo->query("SHOW TABLES LIKE 'donations'")->fetchAll();
    if (!empty($has)) {
        $donations = $pdo->query("
          SELECT * FROM donations 
          ORDER BY created_at DESC 
          LIMIT $limit OFFSET $offset
        ")->fetchAll() ?? [];
        $totalDonations = $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn() ?? 0;
        $totalAmount = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM donations WHERE status='success'")->fetchColumn() ?? 0;
    }
} catch (Exception $e) {
    $donations = [];
    $totalDonations = 0;
    $totalAmount = 0;
}
$totalPages = ceil($totalDonations / $limit);
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">Donations (Paystack)</h2>
    <p class="text-gray-400">Total Raised: GHS <?= number_format($totalAmount, 2) ?></p>
  </div>

  <!-- Quick Init Form -->
  <div class="card mb-6">
    <h3>Initialize New Donation</h3>
    <form method="post" action="donate.php" class="flex gap-3 mt-4">
      <input name="donor_name" placeholder="Donor name" required class="flex-1" />
      <input name="donor_email" placeholder="Email" type="email" required class="flex-1" />
      <input name="amount" placeholder="Amount (GHS)" type="number" step="0.01" min="1" required class="flex-1" />
      <button type="submit" class="btn btn-primary px-6">Init Payment</button>
    </form>
  </div>

  <div class="card">
    <table class="w-full table text-sm">
      <thead>
        <tr>
          <th>Donor</th>
          <th>Email</th>
          <th>Amount</th>
          <th>Reference</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($donations as $d): ?>
        <tr>
          <td class="font-semibold"><?= htmlspecialchars($d['donor_name'] ?? 'N/A') ?></td>
          <td class="text-gray-400"><?= htmlspecialchars($d['donor_email'] ?? 'N/A') ?></td>
          <td class="text-green-400 font-semibold">GHS <?= number_format($d['amount'] ?? 0, 2) ?></td>
          <td class="text-gray-400 font-mono text-xs"><?= htmlspecialchars(substr($d['reference'] ?? '', 0, 20)) ?></td>
          <td><span class="badge <?= $d['status'] === 'success' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($d['status'] ?? 'pending') ?></span></td>
          <td class="text-gray-400"><?= date('M d, Y', strtotime($d['created_at'] ?? 'now')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination mt-4">
      <?php if ($page > 1): ?>
        <a href="?page=1">&laquo; First</a>
        <a href="?page=<?= $page - 1 ?>">Previous</a>
      <?php endif; ?>

      <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>">Next</a>
        <a href="?page=<?= $totalPages ?>">Last &raquo;</a>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once 'inc/footer.php'; ?>
