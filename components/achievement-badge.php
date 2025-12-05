<?php
/**
 * Achievement Badge Component
 * 
 * Displays a single achievement card with flip animation
 * 
 * @param array $achievement - Achievement data (id, name, description, icon, badge_color)
 * @param bool $is_unlocked - Whether the achievement is unlocked
 * @param int $progress - Current progress (0-100)
 * @param string $unlocked_date - Date unlocked (ISO format)
 */
function render_achievement_badge($achievement, $is_unlocked = false, $progress = 0, $unlocked_date = null) {
    $id = htmlspecialchars($achievement['id'] ?? '');
    $name = htmlspecialchars($achievement['name'] ?? 'Achievement');
    $description = htmlspecialchars($achievement['description'] ?? '');
    $icon = htmlspecialchars($achievement['icon'] ?? '🏆');
    $color = htmlspecialchars($achievement['badge_color'] ?? 'gold');
    
    $progress_percent = min(100, max(0, intval($progress)));
    $unlocked_class = $is_unlocked ? 'unlocked' : 'locked';
    
    ?>
    <div class="achievement-card <?= $unlocked_class ?>" data-achievement-id="<?= $id ?>">
        <div class="achievement-inner">
            <!-- Front: Icon and Title -->
            <div class="achievement-front">
                <div class="achievement-icon"><?= $icon ?></div>
                <h3 class="achievement-name"><?= $name ?></h3>
                <p class="achievement-description"><?= $description ?></p>
            </div>

            <!-- Back: Progress or Unlock Info -->
            <div class="achievement-back">
                <?php if ($is_unlocked): ?>
                    <div class="achievement-unlocked">
                        <p class="text-success">✓ Unlocked</p>
                        <?php if ($unlocked_date): ?>
                            <p class="text-xs text-muted">
                                <?= date('M d, Y', strtotime($unlocked_date)) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="achievement-progress">
                        <p class="text-xs text-muted mb-2">Progress: <?= $progress_percent ?>%</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progress_percent ?>%"></div>
                        </div>
                        <p class="text-xs text-muted mt-2">Keep going!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
