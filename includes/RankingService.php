<?php
/**
 * includes/RankingService.php - Ranking algorithm service
 * 
 * Implements weighted scoring algorithm with normalization for:
 * - Daily / Weekly / Monthly story rankings
 * - Top writers rankings
 * - Caching for performance
 */

class RankingService {
    private $pdo;
    private $weights = [
        'views' => 0.30,
        'unique_views' => 0.20,
        'favorites' => 0.20,
        'comments' => 0.15,
        'reading_seconds' => 0.10,
        'boosts' => 0.05,
    ];
    
    private $cacheDir = null;
    private $cacheTTL = 300; // 5 minutes
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        // Use system temp dir for caching
        $this->cacheDir = sys_get_temp_dir() . '/scroll_novels_rankings';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }
    
    /**
     * Get story rankings for period
     * 
     * @param string $period 'daily', 'weekly', or 'monthly'
     * @param int $limit Max results to return
     * @return array Ranked stories with scores and metrics
     */
    public function getStoryRankings($period = 'daily', $limit = 50) {
        $cacheKey = "rankings:stories:{$period}:limit:{$limit}";
        
        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $from = $this->getFromDate($period);
        
        // Aggregate stats per story within period
        // First check if story_stats table exists and has data
        try {
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'story_stats'")->fetch();
            if (!$tableCheck) {
                // Table doesn't exist - return empty rankings
                return [];
            }
            
            $query = "
                SELECT 
                    story_id,
                    SUM(views) as views,
                    SUM(unique_views) as unique_views,
                    SUM(favorites) as favorites,
                    SUM(comments) as comments,
                    SUM(reading_seconds) as reading_seconds,
                    SUM(boosts) as boosts
                FROM story_stats
                WHERE date >= ?
                GROUP BY story_id
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$from->format('Y-m-d')]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If query fails (missing columns, etc), return empty
            error_log("RankingService query error: " . $e->getMessage());
            return [];
        }
        
        if (empty($rows)) {
            return [];
        }
        
        // Calculate max values for normalization
        $max = [
            'views' => 0,
            'unique_views' => 0,
            'favorites' => 0,
            'comments' => 0,
            'reading_seconds' => 0,
            'boosts' => 0,
        ];
        
        foreach ($rows as $row) {
            foreach ($max as $key => &$value) {
                $value = max($value, (int)$row[$key]);
            }
        }
        
        // Score each story
        $ranked = [];
        foreach ($rows as $row) {
            // Normalize each metric (0-1 scale)
            $nv = [];
            foreach ($this->weights as $metric => $_) {
                $nv[$metric] = $max[$metric] > 0 
                    ? (int)$row[$metric] / $max[$metric]
                    : 0;
            }
            
            // Calculate weighted score
            $score = 0;
            foreach ($this->weights as $metric => $weight) {
                $score += ($nv[$metric] ?? 0) * $weight;
            }
            
            $ranked[] = [
                'story_id' => (int)$row['story_id'],
                'score' => round($score, 4),
                'metrics' => [
                    'views' => (int)$row['views'],
                    'unique_views' => (int)$row['unique_views'],
                    'favorites' => (int)$row['favorites'],
                    'comments' => (int)$row['comments'],
                    'reading_seconds' => (int)$row['reading_seconds'],
                    'boosts' => (int)$row['boosts'],
                ],
            ];
        }
        
        // Sort by score descending
        usort($ranked, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Limit results
        $top = array_slice($ranked, 0, $limit);
        
        // Attach story & author info
        $storyIds = array_column($top, 'story_id');
        if (!empty($storyIds)) {
            $placeholders = implode(',', array_fill(0, count($storyIds), '?'));
            $storyQuery = "
                SELECT s.id, s.title, u.id as author_id, u.username as author_name
                FROM stories s
                LEFT JOIN users u ON s.author_id = u.id
                WHERE s.id IN ($placeholders)
            ";
            $storyStmt = $this->pdo->prepare($storyQuery);
            $storyStmt->execute($storyIds);
            $stories = [];
            foreach ($storyStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $stories[$s['id']] = $s;
            }
            
            foreach ($top as &$item) {
                $story = $stories[$item['story_id']] ?? null;
                $item['story_title'] = $story ? $story['title'] : 'Untitled';
                $item['author'] = $story ? [
                    'id' => $story['author_id'],
                    'name' => $story['author_name'],
                ] : null;
            }
        }
        
        // Cache result
        $this->setCache($cacheKey, $top);
        
        return $top;
    }
    
    /**
     * Get top writers for period
     * 
     * @param string $period 'daily', 'weekly', or 'monthly'
     * @param int $limit Max writers to return
     * @return array Top writers with total views
     */
    public function getTopWriters($period = 'monthly', $limit = 200) {
        $cacheKey = "rankings:writers:{$period}:limit:{$limit}";
        
        // Check cache
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $from = $this->getFromDate($period);
        
        $query = "
            SELECT 
                s.author_id,
                SUM(ss.views) as total_views
            FROM story_stats ss
            JOIN stories s ON ss.story_id = s.id
            WHERE s.created_at >= ?
            GROUP BY s.author_id
            ORDER BY total_views DESC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$from->format('Y-m-d'), $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return [];
        }
        
        // Get author info
        $authorIds = array_column($rows, 'author_id');
        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));
        $authorQuery = "SELECT id, username FROM users WHERE id IN ($placeholders)";
        $authorStmt = $this->pdo->prepare($authorQuery);
        $authorStmt->execute($authorIds);
        $authors = [];
        foreach ($authorStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $authors[$a['id']] = $a;
        }
        
        // Format result
        $result = [];
        foreach ($rows as $row) {
            $author = $authors[$row['author_id']] ?? null;
            $result[] = [
                'author_id' => (int)$row['author_id'],
                'username' => $author ? $author['username'] : 'Unknown',
                'total_views' => (int)$row['total_views'],
            ];
        }
        
        // Cache
        $this->setCache($cacheKey, $result);
        
        return $result;
    }
    
    /**
     * Record a story stat event (called after read, like, comment, etc)
     */
    public function recordStat($storyId, $metric, $value = 1) {
        $today = date('Y-m-d');
        
        try {
            $query = "
                INSERT INTO story_stats (story_id, date, $metric)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE $metric = $metric + VALUES($metric)
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$storyId, $today, $value]);
            
            // Invalidate cache
            $this->clearCache();
            
            return true;
        } catch (Exception $e) {
            error_log("RankingService::recordStat error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get from date based on period
     */
    private function getFromDate($period) {
        $today = new DateTime();
        
        switch ($period) {
            case 'daily':
                $today->modify('-1 day');
                break;
            case 'weekly':
                $today->modify('-7 days');
                break;
            case 'monthly':
                $today->modify('-30 days');
                break;
            default:
                $today->modify('-30 days');
        }
        
        return $today;
    }
    
    /**
     * Cache helpers
     */
    private function getFromCache($key) {
        $file = $this->cacheDir . '/' . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        // Check if expired
        if (time() - filemtime($file) > $this->cacheTTL) {
            @unlink($file);
            return null;
        }
        
        $data = file_get_contents($file);
        return json_decode($data, true);
    }
    
    private function setCache($key, $data) {
        $file = $this->cacheDir . '/' . md5($key) . '.cache';
        file_put_contents($file, json_encode($data));
    }
    
    private function clearCache() {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
