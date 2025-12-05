<?php
require 'config/db.php';

echo "=== Checking Recommended Content Query ===\n\n";

// Check if tags table has the LGBTQ+ and female protagonist tags
$tags = $pdo->query("
    SELECT DISTINCT name FROM tags 
    WHERE name IN ('LGBTQ+', 'BL', 'GL', 'Sapphic', 'Female Protagonist', 'FemPro', 'Female Lead', 'Women Lead')
    ORDER BY name
")->fetchAll();

echo "Available Tags:\n";
if (!empty($tags)) {
    foreach ($tags as $tag) {
        echo "  - " . $tag['name'] . "\n";
    }
} else {
    echo "  (No recommended tags found - may need to be created)\n";
}

// Check stories with recommended tags
$recommended = $pdo->query("
    SELECT s.id, s.title, s.author_id, u.username, s.views, s.rating,
           GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as tags
    FROM stories s
    JOIN users u ON s.author_id = u.id
    LEFT JOIN story_tags st ON s.id = st.story_id
    LEFT JOIN tags t ON st.tag_id = t.id
    WHERE t.name IN ('LGBTQ+', 'BL', 'GL', 'Sapphic', 'Female Protagonist', 'FemPro', 'Female Lead', 'Women Lead')
    GROUP BY s.id
    ORDER BY s.views DESC, s.rating DESC
    LIMIT 10
")->fetchAll();

echo "\nRecommended Stories:\n";
if (!empty($recommended)) {
    foreach ($recommended as $story) {
        echo "  - " . $story['title'] . " by " . $story['username'] . " (Views: " . $story['views'] . ", Rating: " . $story['rating'] . ")\n";
    }
} else {
    echo "  (No stories with recommended tags yet)\n";
}

// Check if we can get stories by genres too
$genres = $pdo->query("
    SELECT DISTINCT name FROM genres 
    WHERE name IN ('LGBTQ+', 'Romance', 'Fiction')
    ORDER BY name
")->fetchAll();

echo "\nAvailable Genres:\n";
if (!empty($genres)) {
    foreach ($genres as $genre) {
        echo "  - " . $genre['name'] . "\n";
    }
} else {
    echo "  (No recommended genres found)\n";
}

echo "\n=== Check Complete ===\n";
?>
