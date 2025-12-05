<?php
// pages/top-supporters.php - Leaderboard showing top supporters with tiers and badges

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/supporter-helpers.php';

$page_title = 'Top Supporters - Scroll Novels';

// Fetch top supporters
$stmt = $pdo->prepare("
    SELECT id, username, supporter_points
    FROM users
    WHERE supporter_points > 0
    ORDER BY supporter_points DESC
    LIMIT 200
");
$stmt->execute();
$supporters = $stmt->fetchAll();

// Calculate page rank (for display)
$supporters_with_rank = [];
foreach ($supporters as $idx => $supporter) {
    $rank = $idx + 1;
    $tier = getSupporterTierInfo($supporter['supporter_points']);
    $supporters_with_rank[] = array_merge($supporter, ['rank' => $rank, 'tier' => $tier]);
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-1">
    <div style="max-width: 1000px; margin: 0 auto; padding: 20px;">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 40px;">
            <h1>⭐ Top Supporters</h1>
            <p style="color: #6b7280; margin: 0;">
                Our most dedicated community members who support authors through ads and reading
            </p>
        </div>

        <!-- Tier Info -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 40px;">
            <?php foreach (getSupporterTiers() as $tier): ?>
                <div style="padding: 12px; background: <?= $tier['color'] ?>; border-radius: 8px; text-align: center;">
                    <div style="font-size: 20px; margin-bottom: 5px;"><?= $tier['icon'] ?></div>
                    <p style="margin: 0; font-size: 12px; font-weight: bold; color: <?= $tier['textColor'] ?>;">
                        <?= htmlspecialchars($tier['name']) ?>
                    </p>
                    <p style="margin: 3px 0 0 0; font-size: 11px; color: <?= $tier['textColor'] ?>;">
                        <?= $tier['minPoints'] ?>+ pts
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Leaderboard Table -->
        <?php if (empty($supporters_with_rank)): ?>
            <div style="padding: 40px; text-align: center; background: #f3f4f6; border-radius: 8px;">
                <p style="color: #6b7280; margin: 0;">
                    👀 No supporters yet. <a href="<?= site_url('/pages/ads/create.php') ?>">Create an ad</a> or keep reading to start earning points!
                </p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 15px; text-align: left; font-weight: bold; color: #374151;">🏆</th>
                            <th style="padding: 15px; text-align: left; font-weight: bold; color: #374151;">Username</th>
                            <th style="padding: 15px; text-align: center; font-weight: bold; color: #374151;">Tier</th>
                            <th style="padding: 15px; text-align: right; font-weight: bold; color: #374151;">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supporters_with_rank as $supporter): ?>
                            <tr style="border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s;">
                                <td style="padding: 15px; font-weight: bold; font-size: 18px;">
                                    <?php if ($supporter['rank'] === 1): ?>
                                        🥇
                                    <?php elseif ($supporter['rank'] === 2): ?>
                                        🥈
                                    <?php elseif ($supporter['rank'] === 3): ?>
                                        🥉
                                    <?php else: ?>
                                        #<?= $supporter['rank'] ?>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px; color: #1f2937;">
                                    <strong><?= htmlspecialchars($supporter['username']) ?></strong>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <span style="display: inline-block; padding: 6px 12px; background: <?= $supporter['tier']['color'] ?>; border-radius: 6px; font-size: 14px;">
                                        <?= $supporter['tier']['icon'] ?> <?= htmlspecialchars($supporter['tier']['name']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: right; color: #1f2937;">
                                    <strong><?= number_format($supporter['supporter_points']) ?></strong> ⭐
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Stats Section -->
            <div style="margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="padding: 20px; background: #ecfdf5; border: 1px solid #d1fae5; border-radius: 8px;">
                    <p style="margin: 0; color: #065f46; font-size: 12px; text-transform: uppercase; font-weight: bold;">Total Supporters</p>
                    <p style="margin: 8px 0 0 0; font-size: 28px; font-weight: bold; color: #059669;">
                        <?= count($supporters_with_rank) ?>
                    </p>
                </div>
                <div style="padding: 20px; background: #dbeafe; border: 1px solid #bfdbfe; border-radius: 8px;">
                    <p style="margin: 0; color: #0c4a6e; font-size: 12px; text-transform: uppercase; font-weight: bold;">Total Points Earned</p>
                    <p style="margin: 8px 0 0 0; font-size: 28px; font-weight: bold; color: #0284c7;">
                        <?= number_format(array_sum(array_column($supporters_with_rank, 'supporter_points'))) ?>
                    </p>
                </div>
            </div>

            <!-- How to Join -->
            <div style="margin-top: 40px; padding: 20px; background: #fef3c7; border: 1px solid #fde047; border-radius: 8px;">
                <h3 style="margin-top: 0;">💡 How to Earn Points</h3>
                <ul style="margin: 10px 0; padding-left: 20px; color: #92400e;">
                    <li><strong>Read Books:</strong> Earn 3 points for every 10 minutes of reading</li>
                    <li><strong>Create Ads:</strong> Support authors by boosting their books</li>
                    <li><strong>Climb Tiers:</strong> Unlock badges and recognition as you earn more points</li>
                </ul>
                <div style="margin-top: 15px;">
                    <a href="<?= site_url('/pages/browse.php') ?>" class="btn" style="background: #f59e0b; color: white; display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                        📚 Browse Books
                    </a>
                    <a href="<?= site_url('/pages/ads/create.php') ?>" class="btn" style="background: #f59e0b; color: white; display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-left: 10px;">
                        📢 Create Ad
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
