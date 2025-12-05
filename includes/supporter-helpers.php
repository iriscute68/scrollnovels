<?php
// includes/supporter-helpers.php - Helper functions for reading points and supporter ranks

/**
 * Get supporter title based on points
 */
function getSupporterTitle($points)
{
    if ($points >= 1000) return 'Ultra Fan';
    if ($points >= 500) return 'Diamond Supporter';
    if ($points >= 400) return 'Platinum Supporter';
    if ($points >= 300) return 'Gold Supporter';
    if ($points >= 200) return 'Silver Supporter';
    if ($points >= 100) return 'Bronze Supporter';
    return 'Reader';
}

/**
 * Get supporter title badge color/styling
 */
function getSupporterBadgeClass($points)
{
    if ($points >= 1000) return 'badge-ultra';
    if ($points >= 500) return 'badge-diamond';
    if ($points >= 400) return 'badge-platinum';
    if ($points >= 300) return 'badge-gold';
    if ($points >= 200) return 'badge-silver';
    if ($points >= 100) return 'badge-bronze';
    return 'badge-reader';
}

/**
 * Get supporter emoji/icon
 */
function getSupporterIcon($points)
{
    if ($points >= 1000) return '⭐⭐⭐⭐⭐';
    if ($points >= 500) return '💎';
    if ($points >= 400) return '💜';
    if ($points >= 300) return '🏆';
    if ($points >= 200) return '🥈';
    if ($points >= 100) return '🥉';
    return '📖';
}

/**
 * Calculate next tier threshold and progress
 */
function getNextTierInfo($points)
{
    $tiers = [100, 200, 300, 400, 500, 1000];
    
    foreach ($tiers as $tier) {
        if ($points < $tier) {
            $prev = ($tiers[array_search($tier, $tiers) - 1] ?? 0);
            return [
                'next' => $tier,
                'current' => $points - $prev,
                'needed' => $tier - $prev,
                'percentage' => ($points - $prev) / ($tier - $prev) * 100
            ];
        }
    }
    
    // At max tier
    return [
        'next' => null,
        'current' => $points - 1000,
        'needed' => 0,
        'percentage' => 100
    ];
}

/**
 * Get supporter tier info object (title, icon, color, points)
 */
function getSupporterTierInfo($points)
{
    $tiers = getSupporterTiers();
    
    foreach ($tiers as $tier) {
        if ($points >= $tier['min'] && ($tier['max'] === null || $points <= $tier['max'])) {
            return $tier;
        }
    }
    
    // Fallback to first tier
    return $tiers[0];
}

/**
 * Format points for display
 */
function formatPoints($points)
{
    if ($points >= 1000) {
        return number_format($points / 1000, 1) . 'K';
    }
    return number_format($points);
}

/**
 * Get all supporter tiers
 */
function getSupporterTiers()
{
    return [
        ['title' => 'Reader', 'min' => 0, 'max' => 99, 'icon' => '📖', 'color' => '#666'],
        ['title' => 'Bronze Supporter', 'min' => 100, 'max' => 199, 'icon' => '🥉', 'color' => '#CD7F32'],
        ['title' => 'Silver Supporter', 'min' => 200, 'max' => 299, 'icon' => '🥈', 'color' => '#C0C0C0'],
        ['title' => 'Gold Supporter', 'min' => 300, 'max' => 399, 'icon' => '🏆', 'color' => '#FFD700'],
        ['title' => 'Platinum Supporter', 'min' => 400, 'max' => 499, 'icon' => '💜', 'color' => '#E5E4E2'],
        ['title' => 'Diamond Supporter', 'min' => 500, 'max' => 999, 'icon' => '💎', 'color' => '#00CED1'],
        ['title' => 'Ultra Fan', 'min' => 1000, 'max' => null, 'icon' => '⭐⭐⭐⭐⭐', 'color' => '#FFD700'],
    ];
}
