<?php
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

$comp_id = intval($_GET['comp_id'] ?? 0);

// Get winners / entries to payout
$sql = "SELECT ce.id, ce.story_id, ce.user_id, ce.total_score, ce.status,
               s.title, u.username, u.email
        FROM competition_entries ce
        JOIN stories s ON s.id = ce.story_id
        JOIN users u ON u.id = ce.user_id
        WHERE ce.competition_id = ?
        ORDER BY ce.total_score DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$comp_id]);
$entries = $stmt->fetchAll();
?>

<div class="p-6">
  <h1 class="text-2xl font-bold">Prize Payouts</h1>

  <div class="space-y-4 mt-6">
    <?php foreach ($entries as $e): ?>
      <div class="card p-4 flex gap-4 items-center">
        <div class="flex-1">
          <h3 class="font-bold"><?= htmlspecialchars($e['title']) ?></h3>
          <p class="text-sm text-gray-400"><?= htmlspecialchars($e['username']) ?> • Score: <?= number_format($e['total_score'], 2) ?></p>
        </div>

        <div>
          <label>Amount (NGN)</label>
          <input type="number" class="input payout-amount" value="0" data-entry-id="<?= $e['id'] ?>" data-user-id="<?= $e['user_id'] ?>">
        </div>

        <button class="btn btn-gold" onclick="createPayout(<?= $e['id'] ?>, <?= $e['user_id'] ?>)">Create Payout</button>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-8">
    <button class="btn btn-gold" onclick="initiateBulkTransfers()">Initiate Paystack Transfers</button>
  </div>
</div>

<script>
const compId = <?= $comp_id ?>;

function createPayout(entryId, userId) {
  const input = document.querySelector(`[data-entry-id="${entryId}"]`);
  const amount = parseFloat(input.value);

  if (amount <= 0) {
    alert('Enter valid amount');
    return;
  }

  fetch('payout_create.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'comp_id=' + compId + '&entry_id=' + entryId + '&user_id=' + userId + '&amount=' + amount
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      alert('Payout created!');
      location.reload();
    } else {
      alert('Error: ' + (data.error || 'unknown'));
    }
  });
}

function initiateBulkTransfers() {
  if (!confirm('Initiate Paystack transfers for all pending payouts?')) return;

  fetch('payout_trigger.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'comp_id=' + compId
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message || 'Transfers initiated');
    location.reload();
  });
}
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
