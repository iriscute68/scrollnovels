<?php
/**
 * config-datasource.php - Data source fallback configuration
 * 
 * Enables MySQL → JSON file → Hardcoded data fallback pattern
 * Useful for caching, resilience, and offline capabilities
 */

// Define data source priority order
const DATA_SOURCE_ORDER = ['mysql', 'json', 'hardcoded'];
const DATA_DIR = __DIR__ . '/../data';

/**
 * Get data from preferred sources with fallback
 * 
 * @param string $type - Data type ('competitions', 'stories', etc.)
 * @return array - Data array
 */
function loadData($type) {
    foreach (DATA_SOURCE_ORDER as $source) {
        try {
            switch ($source) {
                case 'mysql':
                    return loadFromMySQL($type);
                case 'json':
                    return loadFromJSON($type);
                case 'hardcoded':
                    return loadHardcoded($type);
            }
        } catch (Exception $e) {
            error_log("Failed to load $type from $source: " . $e->getMessage());
            continue;
        }
    }
    
    // If all sources fail, return empty array
    error_log("Failed to load $type from all sources");
    return [];
}

/**
 * Load data from MySQL database
 */
function loadFromMySQL($type) {
    global $pdo;
    
    if (!isset($pdo)) {
        throw new Exception("Database connection not available");
    }

    switch ($type) {
        case 'competitions':
            $stmt = $pdo->query("SELECT * FROM competitions WHERE active = 1 ORDER BY start_date DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        case 'competitions_detail':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        case 'stories':
            $stmt = $pdo->query("SELECT * FROM stories WHERE status = 'published' ORDER BY created_at DESC LIMIT 100");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        case 'featured':
            $stmt = $pdo->query("
                SELECT * FROM stories 
                WHERE status = 'published' 
                ORDER BY views DESC 
                LIMIT 10
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        default:
            throw new Exception("Unknown data type: $type");
    }
}

/**
 * Load data from JSON file cache
 */
function loadFromJSON($type) {
    $file = DATA_DIR . "/{$type}.json";
    
    if (!file_exists($file)) {
        throw new Exception("JSON file not found: $file");
    }

    $json = file_get_contents($file);
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in {$file}: " . json_last_error_msg());
    }

    return $data ?: [];
}

/**
 * Load hardcoded data (last resort fallback)
 */
function loadHardcoded($type) {
    switch ($type) {
        case 'competitions':
            return [
                [
                    'id' => 1,
                    'title' => 'Featured Competition',
                    'description' => 'Participate in our featured writing competition',
                    'start_date' => date('Y-m-d', strtotime('-7 days')),
                    'end_date' => date('Y-m-d', strtotime('+23 days')),
                    'prize_pool' => 5000,
                    'max_entries' => 100,
                    'active' => 1
                ]
            ];
        
        case 'stories':
            return [
                [
                    'id' => 1,
                    'title' => 'Welcome to Scroll Novels',
                    'synopsis' => 'Explore our publishing platform',
                    'author' => 'System',
                    'views' => 1000,
                    'status' => 'published'
                ]
            ];
        
        case 'featured':
            return [
                [
                    'id' => 1,
                    'title' => 'Featured Story',
                    'synopsis' => 'A popular story on our platform',
                    'views' => 5000
                ]
            ];
        
        default:
            return [];
    }
}

/**
 * Save data to JSON cache
 */
function saveDataToJSON($type, $data) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    $file = DATA_DIR . "/{$type}.json";
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($file, $json) === false) {
        throw new Exception("Failed to write JSON file: $file");
    }

    return true;
}

/**
 * Refresh all data caches from MySQL
 */
function refreshAllCaches() {
    try {
        global $pdo;
        
        if (!isset($pdo)) {
            throw new Exception("Database not available");
        }

        // Cache competitions
        $competitions = $pdo->query("SELECT * FROM competitions ORDER BY start_date DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
        saveDataToJSON('competitions', $competitions);

        // Cache stories
        $stories = $pdo->query("SELECT * FROM stories WHERE status = 'published' ORDER BY created_at DESC LIMIT 100")
            ->fetchAll(PDO::FETCH_ASSOC);
        saveDataToJSON('stories', $stories);

        // Cache featured
        $featured = $pdo->query("SELECT * FROM stories WHERE status = 'published' ORDER BY views DESC LIMIT 10")
            ->fetchAll(PDO::FETCH_ASSOC);
        saveDataToJSON('featured', $featured);

        return ['ok' => true, 'message' => 'Caches refreshed'];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
?>
