<?php
// api/get-genres-tags.php - Get all genres and tags for story creation

header('Content-Type: application/json');

// Defensive include: if DB config is missing or fails, return JSON error instead of causing a fatal error
$dbPath = dirname(__DIR__) . '/config/db.php';
if (file_exists($dbPath)) {
    // Use include_once so a parse error will still surface but we can check $pdo afterwards
    include_once $dbPath;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database configuration not found']);
    exit;
}

if (empty($pdo) || !($pdo instanceof \PDO)) {
    // If DB isn't available (e.g., during restore or local dev), provide comprehensive fallback lists
    $fallbackGenres = [
        ['id'=>1,'name'=>'Action','emoji'=>'⚔️'],
        ['id'=>2,'name'=>'Adventure','emoji'=>'🗺️'],
        ['id'=>3,'name'=>'Comedy','emoji'=>'😂'],
        ['id'=>4,'name'=>'Contemporary','emoji'=>'🏙️'],
        ['id'=>5,'name'=>'Crime','emoji'=>'🔍'],
        ['id'=>6,'name'=>'Drama','emoji'=>'🎭'],
        ['id'=>7,'name'=>'Fantasy','emoji'=>'🧙'],
        ['id'=>8,'name'=>'Forbidden Love','emoji'=>'❤️‍🔥'],
        ['id'=>9,'name'=>'Ghost Stories','emoji'=>'👻'],
        ['id'=>10,'name'=>'Historical','emoji'=>'📜'],
        ['id'=>11,'name'=>'Horror','emoji'=>'😱'],
        ['id'=>12,'name'=>'LGBTQ+','emoji'=>'🌈'],
        ['id'=>13,'name'=>'Magic','emoji'=>'✨'],
        ['id'=>14,'name'=>'Mystery','emoji'=>'🕵️'],
        ['id'=>15,'name'=>'Paranormal','emoji'=>'👁️'],
        ['id'=>16,'name'=>'Psychological','emoji'=>'🧠'],
        ['id'=>17,'name'=>'Romance','emoji'=>'💕'],
        ['id'=>18,'name'=>'Sci-Fi','emoji'=>'🚀'],
        ['id'=>19,'name'=>'Short Story','emoji'=>'📕'],
        ['id'=>20,'name'=>'Slice of Life','emoji'=>'🌾'],
        ['id'=>21,'name'=>'Superhero','emoji'=>'🦸'],
        ['id'=>22,'name'=>'Supernatural','emoji'=>'🌙'],
        ['id'=>23,'name'=>'Thriller','emoji'=>'😲'],
        ['id'=>24,'name'=>'Time Travel','emoji'=>'⏰'],
        ['id'=>25,'name'=>'Tragedy','emoji'=>'💔'],
        ['id'=>26,'name'=>'Urban Fantasy','emoji'=>'🌃'],
        ['id'=>27,'name'=>'Western','emoji'=>'🤠'],
        ['id'=>28,'name'=>'Wuxia','emoji'=>'🥊'],
        ['id'=>29,'name'=>'Cultivation','emoji'=>'🌟'],
    ];
    $fallbackTags = [
        'tag' => [
            ['id'=>1,'name'=>'Anti-Hero Lead'],
            ['id'=>2,'name'=>'Dragons'],
            ['id'=>3,'name'=>'Elves'],
            ['id'=>4,'name'=>'Enemies to Lovers'],
            ['id'=>5,'name'=>'Female Lead'],
            ['id'=>6,'name'=>'Forced Proximity'],
            ['id'=>7,'name'=>'Gay Romance'],
            ['id'=>8,'name'=>'Godly'],
            ['id'=>9,'name'=>'HFY (Humanity First)'],
            ['id'=>10,'name'=>'Harem'],
            ['id'=>11,'name'=>'Immortal'],
            ['id'=>12,'name'=>'Lesbian Romance'],
            ['id'=>13,'name'=>'Litrpg'],
            ['id'=>14,'name'=>'Magic System'],
            ['id'=>15,'name'=>'Male Lead'],
            ['id'=>16,'name'=>'Mutants'],
            ['id'=>17,'name'=>'NonHuman'],
            ['id'=>18,'name'=>'Possession'],
            ['id'=>19,'name'=>'Progression'],
            ['id'=>20,'name'=>'Reincarnation'],
            ['id'=>21,'name'=>'Reverse Harem'],
            ['id'=>22,'name'=>'Romance Subplot'],
            ['id'=>23,'name'=>'Slow Burn'],
            ['id'=>24,'name'=>'Slice of Life'],
            ['id'=>25,'name'=>'Soul Mates'],
            ['id'=>26,'name'=>'Space Opera'],
            ['id'=>27,'name'=>'Steampunk'],
            ['id'=>28,'name'=>'System Administrator'],
            ['id'=>29,'name'=>'Time Travel'],
            ['id'=>30,'name'=>'Transmigration'],
            ['id'=>31,'name'=>'Underdog'],
            ['id'=>32,'name'=>'Vampires'],
            ['id'=>33,'name'=>'Werewolves'],
        ],
        'warning' => [
            ['id'=>101,'name'=>'Alcohol Abuse'],
            ['id'=>102,'name'=>'Bullying'],
            ['id'=>103,'name'=>'Child Abuse'],
            ['id'=>104,'name'=>'Death'],
            ['id'=>105,'name'=>'Domestic Violence'],
            ['id'=>106,'name'=>'Drug Abuse'],
            ['id'=>107,'name'=>'Eating Disorder'],
            ['id'=>108,'name'=>'Genocide'],
            ['id'=>109,'name'=>'Graphic Violence'],
            ['id'=>110,'name'=>'Gun Violence'],
            ['id'=>111,'name'=>'Incest'],
            ['id'=>112,'name'=>'Kidnapping'],
            ['id'=>113,'name'=>'Pedophilia'],
            ['id'=>114,'name'=>'Profanity'],
            ['id'=>115,'name'=>'Rape'],
            ['id'=>116,'name'=>'Self-Harm'],
            ['id'=>117,'name'=>'Sexual Assault'],
            ['id'=>118,'name'=>'Sexual Content'],
            ['id'=>119,'name'=>'Slavery'],
            ['id'=>120,'name'=>'Suicide'],
            ['id'=>121,'name'=>'Suicide Attempt'],
            ['id'=>122,'name'=>'Torture'],
            ['id'=>123,'name'=>'Trafficking'],
            ['id'=>124,'name'=>'War'],
        ]
    ];
    echo json_encode(['success' => true, 'genres' => $fallbackGenres, 'tags' => $fallbackTags]);
    exit;
}

try {
    // Get all genres
    $stmt = $pdo->query("SELECT id, name, emoji FROM genres ORDER BY name");
    $genres = $stmt->fetchAll();
    
    // If no genres in DB, use fallback
    if (empty($genres)) {
        $genres = [
            ['id'=>1,'name'=>'Action','emoji'=>'⚔️'],
            ['id'=>2,'name'=>'Adventure','emoji'=>'🗺️'],
            ['id'=>3,'name'=>'Comedy','emoji'=>'😂'],
            ['id'=>4,'name'=>'Contemporary','emoji'=>'🏙️'],
            ['id'=>5,'name'=>'Drama','emoji'=>'🎭'],
            ['id'=>6,'name'=>'Fantasy','emoji'=>'🧙'],
            ['id'=>7,'name'=>'Historical','emoji'=>'📜'],
            ['id'=>8,'name'=>'Horror','emoji'=>'😱'],
            ['id'=>9,'name'=>'Mystery','emoji'=>'🕵️'],
            ['id'=>10,'name'=>'Romance','emoji'=>'💕'],
            ['id'=>11,'name'=>'Sci-Fi','emoji'=>'🚀'],
            ['id'=>12,'name'=>'Thriller','emoji'=>'😲'],
        ];
    }
    
    // Get all tags grouped by category
    $stmt = $pdo->query("SELECT id, name, slug, category FROM story_tags ORDER BY category, name");
    $tags = $stmt->fetchAll();
    
    // Group tags by category
    $tagsByCategory = [];
    foreach ($tags as $tag) {
        $cat = $tag['category'] ?? 'tag';
        if (!isset($tagsByCategory[$cat])) {
            $tagsByCategory[$cat] = [];
        }
        $tagsByCategory[$cat][] = $tag;
    }
    
    echo json_encode([
        'success' => true,
        'genres' => $genres,
        'tags' => $tagsByCategory
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
