<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<main class="admin-main">
    <?php require __DIR__ . '/topbar.php'; ?>
    <div class="max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">📊 Analytics & Insights</h1>

        <canvas id="dailyViewsChart" height="100"></canvas>
        <canvas id="donationTrendChart" height="100"></canvas>
        <canvas id="userDistributionChart" height="100"></canvas>
        <canvas id="storyStatusChart" height="100"></canvas>

        <h3 class="mt-6">🏆 Top 10 Supporters</h3>
        <table class="table w-full"><thead><tr><th>User</th><th>Total Support</th></tr></thead><tbody id="supportersTable"></tbody></table>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
async function loadAnalytics(range='daily'){
    const res = await fetch('analytics_data.php?range='+encodeURIComponent(range));
    const data = await res.json();
    updateCharts(data);
}

function updateCharts(data){
    // DAILY VIEWS
    new Chart(document.getElementById("dailyViewsChart"), {
        type: "line",
        data: { labels: data.dailyViews.map(d=>d.day), datasets:[{label:'Daily Story Views', data:data.dailyViews.map(d=>d.views), borderWidth:2}] }
    });

    // DONATIONS
    new Chart(document.getElementById("donationTrendChart"), {
        type: 'bar', data:{ labels:data.donations.map(d=>d.month), datasets:[{label:'Donations', data:data.donations.map(d=>d.total)}] }
    });

    // USERS distribution
    new Chart(document.getElementById("userDistributionChart"), { type:'pie', data:{ labels:data.users.map(u=>u.country), datasets:[{data:data.users.map(u=>u.total)}] } });

    // STORY STATUS
    new Chart(document.getElementById("storyStatusChart"), { type:'doughnut', data:{ labels:data.storyStatus.map(s=>s.status), datasets:[{data:data.storyStatus.map(s=>s.total)}] } });

    // Supporters table
    document.getElementById('supportersTable').innerHTML = (data.supporters||[]).map(s=>`<tr><td>${s.username}</td><td>$${s.total_amount||s.total_amount||0}</td></tr>`).join('')
}

loadAnalytics();
setInterval(()=>loadAnalytics(), 10000);
</script>
<?php
// admin/analytics.php
require_once 'inc/header.php';
$activeTab = 'analytics';
require_once 'inc/sidebar.php';

// Fetch data for charts
$views = $pdo->query("
  SELECT DATE(created_at) as d, SUM(views) as v 
  FROM stories 
  GROUP BY DATE(created_at) 
  ORDER BY d DESC 
  LIMIT 30
")->fetchAll() ?? [];

$dailyDonations = [];
try {
    $dailyDonations = $pdo->query("
      SELECT DATE(created_at) as d, SUM(amount) as amt 
      FROM donations 
      GROUP BY DATE(created_at) 
      ORDER BY d DESC 
      LIMIT 6
    ")->fetchAll() ?? [];
} catch (Exception $e) {
    // donations table may not exist or status column doesn't exist
}
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">Analytics & Insights</h2>
    <p class="text-gray-400">Platform metrics and statistics</p>
  </div>

  <!-- Charts Row 1 -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="card">
      <h3>Daily Story Views (Last 30 days)</h3>
      <canvas id="chart-views" height="100"></canvas>
    </div>

    <div class="card">
      <h3>Donation Trends (Last 6 months)</h3>
      <canvas id="chart-revenue" height="100"></canvas>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="card">
      <h3>User Distribution</h3>
      <canvas id="chart-users" height="100"></canvas>
    </div>

    <div class="card">
      <h3>Story Status Distribution</h3>
      <canvas id="chart-stories" height="100"></canvas>
    </div>
  </div>

  <!-- Top Supporters -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
      <h3>Top 10 Supporters</h3>
      <div class="space-y-2">
        <?php
        $topDonors = [];
        try {
            $topDonors = $pdo->query("
              SELECT d.donor_id, u.username, SUM(d.amount) as total, COUNT(*) as count 
              FROM donations d
              LEFT JOIN users u ON d.donor_id = u.id
              WHERE d.donor_id IS NOT NULL
              GROUP BY d.donor_id
              ORDER BY total DESC 
              LIMIT 10
            ")->fetchAll() ?? [];
        } catch (Exception $e) {
            $topDonors = [];
        }
        foreach($topDonors as $donor):
        ?>
          <div class="flex justify-between items-center py-2 border-b border-[#1f2937]">
            <div>
              <div class="text-sm font-semibold"><?= htmlspecialchars($donor['username'] ?? 'Anonymous Donor') ?></div>
              <div class="text-xs text-gray-500"><?= $donor['count'] ?> donation(s)</div>
            </div>
            <div class="text-green-400 font-bold">GHS <?= number_format($donor['total'], 2) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h3>Top Stories</h3>
      <div class="space-y-2">
        <?php
        $topStories = $pdo->query("
          SELECT id, title, views, author_id 
          FROM stories 
          ORDER BY views DESC 
          LIMIT 10
        ")->fetchAll() ?? [];
        foreach($topStories as $story):
        ?>
          <div class="flex justify-between items-center py-2 border-b border-[#1f2937]">
            <div class="text-sm font-semibold"><?= htmlspecialchars(substr($story['title'] ?? '', 0, 40)) ?></div>
            <div class="text-gray-400"><?= number_format($story['views'] ?? 0) ?> views</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<?php require_once 'inc/footer.php'; ?>
