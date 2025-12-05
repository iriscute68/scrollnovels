<?php
// competitions/index.php - Public competitions listing
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

// Get all active/upcoming competitions
$stmt = $pdo->query("
  SELECT c.*, u.username as created_by_name,
    (SELECT COUNT(*) FROM competition_entries WHERE competition_id = c.id) as entry_count
  FROM competitions c
  LEFT JOIN users u ON u.id = c.created_by
  WHERE c.status IN ('active', 'upcoming')
  ORDER BY c.start_date ASC
");
$competitions = $stmt->fetchAll();

require_once __DIR__ . '/../inc/header.php';
?>

<div class="container">
  <div class="page-header">
    <h1>🎯 Story Contests</h1>
    <p>Showcase your writing. Compete. Win!</p>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin: 30px 0;">
    <?php if (empty($competitions)): ?>
      <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
        <p style="color: var(--muted); font-size: 16px;">No active contests at the moment. Check back soon!</p>
      </div>
    <?php else: ?>
      <?php foreach ($competitions as $comp): ?>
        <div class="card" style="display: flex; flex-direction: column;">
          <div style="flex-grow: 1;">
            <h3 style="margin: 0 0 12px 0; color: var(--gold);">
              <?= htmlspecialchars($comp['title']) ?>
            </h3>
            
            <div style="font-size: 12px; color: var(--muted); margin-bottom: 12px;">
              <div>📅 <?= date('M d, Y', strtotime($comp['start_date'])) ?> — <?= date('M d, Y', strtotime($comp['end_date'])) ?></div>
              <div>👥 <?= (int)$comp['entry_count'] ?> entries</div>
              <div style="margin-top: 8px;">
                <span style="display: inline-block; padding: 4px 8px; background: <?= $comp['status'] === 'active' ? 'rgba(76, 175, 80, 0.2)' : 'rgba(212, 175, 55, 0.2)' ?>; border-radius: 4px; color: <?= $comp['status'] === 'active' ? '#4CB050' : 'var(--gold)' ?>; text-transform: uppercase; font-size: 10px; font-weight: 600;">
                  <?= htmlspecialchars($comp['status']) ?>
                </span>
              </div>
            </div>

            <div style="color: var(--ivory); font-size: 13px; line-height: 1.6; margin-bottom: 16px;">
              <?= nl2br(htmlspecialchars(substr($comp['description'], 0, 150))) ?>...
            </div>

            <?php if (!empty($comp['prize'])): ?>
              <div style="padding: 12px; background: rgba(212, 175, 55, 0.1); border-left: 3px solid var(--gold); margin-bottom: 12px; font-size: 13px;">
                <strong style="color: var(--gold);">Prize:</strong> <?= htmlspecialchars($comp['prize']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 8px; margin-top: auto;">
            <a href="/competitions/view.php?id=<?= (int)$comp['id'] ?>" class="btn" style="flex: 1; text-align: center;">View Details</a>
            <?php if (is_logged_in() && $comp['status'] === 'active'): ?>
              <a href="/competitions/submit.php?id=<?= (int)$comp['id'] ?>" class="btn btn-gold" style="flex: 1; text-align: center;">Submit</a>
            <?php elseif (!is_logged_in()): ?>
              <a href="/login.php" class="btn btn-gold" style="flex: 1; text-align: center;">Login to Enter</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<style>
  .page-header {
    text-align: center;
    margin: 40px 0 30px 0;
    padding: 30px 0;
    border-bottom: 1px solid rgba(212,175,55,0.2);
  }

  .page-header h1 {
    margin: 0 0 10px 0;
    color: var(--gold);
    font-size: 36px;
  }

  .page-header p {
    color: var(--muted);
    margin: 0;
    font-size: 16px;
  }
</style>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
