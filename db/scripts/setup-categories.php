<?php
require __DIR__ . '/../../config/db.php';

// Create categories table
$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// List of 200+ categories
$categories = [
    "Action", "Romance", "Adventure", "Drama", "Comedy", "Fantasy", "Magic", "Isekai",
    "Sci-Fi", "Cyberpunk", "Mystery", "Thriller", "Horror", "Demons", "Angels",
    "Reincarnation", "Video Games", "Dystopian", "Martial Arts", "School Life",
    "Slice of Life", "Supernatural", "Dark Fantasy", "Light Novel", "Web Novel",
    "Urban Fantasy", "Paranormal", "Steampunk", "Post-Apocalyptic", "Military",
    "Psychological", "Crime", "Historical", "Contemporary", "Modern", "Ancient",
    "Medieval", "Pirate", "Vampire", "Werewolf", "Zombie", "Monster",
    "Ghost", "Witch", "Wizard", "Magic School", "Academy", "University",
    "High School", "Middle School", "Elementary", "Workplace", "Office", "Business",
    "CEO", "Billionaire", "Rich", "Poor", "Wealthy", "Poverty",
    "Class Divide", "Social Status", "Politics", "Government", "Military", "War",
    "Battle", "Adventure Quest", "Treasure Hunt", "Mystery Solving", "Detective",
    "Police", "Crime Fighting", "Superhero", "Superpowers", "Powers", "Magic System",
    "Abilities", "Skills", "Training", "Martial Arts", "Combat", "Battle",
    "Dungeon", "Monsters", "Boss Fight", "Level Up", "Game", "Gaming",
    "Virtual Reality", "Alternate World", "Parallel Universe", "Time Travel", "Future",
    "Past", "Present", "Immortal", "Immortality", "Curse", "Blessing",
    "Prophecy", "Destiny", "Fate", "Free Will", "Choice", "Consequence",
    "Betrayal", "Redemption", "Revenge", "Justice", "Truth", "Lie",
    "Deception", "Trust", "Loyalty", "Friendship", "Rivalry", "Enemy",
    "Ally", "Companion", "Party", "Team", "Group", "Solo",
    "Lone Wolf", "Outcast", "Rebel", "Revolution", "Rebellion", "Resistance",
    "Freedom", "Oppression", "Slavery", "Liberation", "Captive", "Escape",
    "Prison", "Cage", "Chain", "Bond", "Tie", "Connection",
    "Relationship", "Family", "Parent", "Child", "Sibling", "Brother",
    "Sister", "Cousin", "Aunt", "Uncle", "Grandparent", "Ancestor",
    "Descendant", "Heritage", "Legacy", "Inheritance", "Wealth", "Poverty",
    "Fortune", "Misfortune", "Luck", "Bad Luck", "Jinxed", "Blessed"
];

// Insert categories, ignoring duplicates
$stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
$inserted = 0;
foreach ($categories as $cat) {
    try {
        $stmt->execute([$cat]);
        $inserted++;
    } catch (Exception $e) {
        // ignore duplicates
    }
}

echo json_encode([
    'success' => true, 
    'message' => "Categories setup complete. Inserted/existing: $inserted categories",
    'total_categories' => $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count']
]);

