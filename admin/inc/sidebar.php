<?php
// admin/inc/sidebar.php
$active = $activeTab ?? basename($_SERVER['PHP_SELF'], '.php');
$site = $config['site'] ?? ['name' => 'Scroll Novels'];
?>
<div class="w-64 bg-[#0f1113] border-r border-[#1f2937] min-h-screen fixed left-0 top-0">
  <div class="p-4 border-b border-[#1f2937]">
    <h2 class="text-xl font-bold"><?= htmlspecialchars($site['name']) ?> <span class="text-sm text-gray-400">Admin</span></h2>
  </div>
  <nav class="p-4 space-y-1">
    <?php
    // Map sidebar items to dashboard tabs
    $menu = [
      'overview' => ['label'=>'Overview','icon'=>'🏠'],
      'users' => ['label'=>'Users','icon'=>'👥'],
      'stories' => ['label'=>'Stories','icon'=>'📚'],
      'chapters' => ['label'=>'Chapters','icon'=>'📖'],
      'comments' => ['label'=>'Comments','icon'=>'💬'],
      'tags' => ['label'=>'Tags','icon'=>'🏷️'],
      'reports' => ['label'=>'Reports','icon'=>'🚨'],
      'announcements' => ['label'=>'Plagiarism','icon'=>'🔍'],
      'blog' => ['label'=>'Moderation','icon'=>'⚖️'],
      'monetization' => ['label'=>'Donations','icon'=>'💳'],
      'analytics' => ['label'=>'Analytics','icon'=>'📈'],
      'settings' => ['label'=>'Settings','icon'=>'⚙️'],
      'staff' => ['label'=>'Admins','icon'=>'🔐'],
    ];
    
    // Get current tab from GET parameter or URL
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : basename($_SERVER['PHP_SELF'], '.php');
    
    foreach($menu as $tab_name => $item):
      $cls = ($current_tab === $tab_name) ? 'bg-[#17191b] text-white font-semibold' : 'text-gray-300 hover:bg-[#111316]';
    ?>
      <a href="dashboard.php?tab=<?= urlencode($tab_name) ?>" class="block p-2 rounded transition-colors <?= $cls ?>">
        <span class="mr-2"><?= $item['icon'] ?></span> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
    <hr class="my-4 border-[#1f2937]" />
    <a href="/scrollnovels/" class="block p-2 rounded text-emerald-400 hover:bg-[#111316]">🏠 Go to Homepage</a>
    <a href="logout.php" class="block p-2 rounded text-red-400 hover:bg-[#111316] mt-2">🚪 Logout</a>
  </nav>
</div>
