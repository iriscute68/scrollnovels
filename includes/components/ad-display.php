<?php
// includes/ad-display.php
function showAds($pdo, $placement, $story_id = null) {
    try {
        $today = date('Y-m-d');
        $sql = "SELECT id, title, description, image_url FROM ads WHERE placement = ? AND status = 'active' AND created_at <= NOW() ORDER BY RAND() LIMIT 1";
        $params = [$placement];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ad) {
            echo '<div class="ad mb-3 p-3 border rounded bg-light">';
            if (!empty($ad['image_url'])) {
                echo '<img src="' . htmlspecialchars($ad['image_url']) . '" style="width: 100%; max-height: 200px; object-fit: cover; margin-bottom: 10px;" alt="Ad">';
            }
            if (!empty($ad['title'])) {
                echo '<h5>' . htmlspecialchars($ad['title']) . '</h5>';
            }
            if (!empty($ad['description'])) {
                echo '<p>' . htmlspecialchars($ad['description']) . '</p>';
            }
            echo '</div>';
        }
    } catch (Exception $e) {
        // Silently fail if ads table or query fails
    }
}
?>