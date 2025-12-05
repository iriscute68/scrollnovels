# Ranking System - Technical Reference Guide

## Quick Start

### For Developers
```php
// Initialize ranking service
require_once 'includes/RankingService.php';
$rankingService = new RankingService($pdo);

// Get story rankings
$rankings = $rankingService->getStoryRankings('daily', 50);

// Get top writers
$topWriters = $rankingService->getTopWriters('monthly', 12);

// Record a stat when user reads
$rankingService->recordStat($storyId, 'views', 1);
```

### For API Integration
```javascript
// JavaScript Example
fetch('/api/rankings/stories.php?period=daily&limit=10')
  .then(r => r.json())
  .then(data => {
    console.log('Top story:', data.items[0]);
    // {rank: 1, story_id: 42, score: 0.87, ...}
  });
```

---

## RankingService API Reference

### Constructor
```php
$rankingService = new RankingService($pdo);
```
- **Parameter**: `$pdo` - PDO database connection
- **Initializes**: Cache directory, weights, TTL
- **Returns**: RankingService instance

### getStoryRankings()
```php
$rankings = $rankingService->getStoryRankings($period, $limit);
```

**Parameters**:
- `$period` (string): 'daily', 'weekly', or 'monthly'
- `$limit` (int): 1-50 (default: 50)

**Returns** (array):
```php
[
    0 => [
        'rank' => 1,
        'story_id' => 42,
        'story_title' => 'Dragon\'s Legacy',
        'author' => 'ProWriter',
        'score' => 0.8725,
        'metrics' => [
            'views' => 1000,
            'unique_views' => 750,
            'favorites' => 150,
            'comments' => 45,
            'reading_seconds' => 54000,
            'boosts' => 5
        ]
    ],
    // ... more stories
]
```

**Example**:
```php
$dailyRankings = $rankingService->getStoryRankings('daily', 10);
foreach ($dailyRankings as $story) {
    echo "{$story['rank']}. {$story['story_title']} ({$story['score']})";
}
```

### getTopWriters()
```php
$writers = $rankingService->getTopWriters($period, $limit);
```

**Parameters**:
- `$period` (string): 'daily', 'weekly', or 'monthly'
- `$limit` (int): 1-200 (default: 200)

**Returns** (array):
```php
[
    0 => [
        'rank' => 1,
        'author_id' => 5,
        'username' => 'ProWriter',
        'total_views' => 150000
    ],
    // ... more writers
]
```

### recordStat()
```php
$rankingService->recordStat($storyId, $metric, $value);
```

**Parameters**:
- `$storyId` (int): Story ID to record stat for
- `$metric` (string): 'views', 'unique_views', 'favorites', 'comments', 'reading_seconds', 'boosts'
- `$value` (int): Value to add/record

**Returns**: true on success, throws exception on error

**Example**:
```php
// Record when user views story
$rankingService->recordStat(42, 'views', 1);

// Record when user favorites
$rankingService->recordStat(42, 'favorites', 1);

// Record reading time (in seconds)
$rankingService->recordStat(42, 'reading_seconds', 300);
```

**Notes**:
- Increments existing values
- Invalidates cache on new stat
- Auto-creates stats row if needed

---

## Weighted Scoring Algorithm

### Formula
```
Score = Σ(normalized_metric × weight)

Where:
- normalized_metric = metric_value / max_metric_in_period
- Clipped to 0-1 range
- Each metric normalized independently
```

### Weights
| Metric | Weight | Importance |
|--------|--------|-----------|
| Views | 30% | Primary popularity indicator |
| Unique Views | 20% | Distinct reader engagement |
| Favorites | 20% | Strong reader preference |
| Comments | 15% | Community engagement |
| Reading Time | 10% | Content consumption depth |
| Boosts | 5% | Paid promotion bonus |

### Example Calculation

Story has:
- Views: 150 (of 500 max in period)
- Unique Views: 100 (of 400 max)
- Favorites: 20 (of 100 max)
- Comments: 15 (of 50 max)
- Reading Seconds: 10000 (of 36000 max)
- Boosts: 2 (of 10 max)

**Calculation**:
```
Views:       150/500 × 0.30 = 0.09
Unique:      100/400 × 0.20 = 0.05
Favorites:   20/100  × 0.20 = 0.04
Comments:    15/50   × 0.15 = 0.045
ReadTime:    10000/36000 × 0.10 = 0.0278
Boosts:      2/10    × 0.05 = 0.01

TOTAL SCORE: 0.3428 = 34.28%
```

---

## Caching System

### How It Works
1. **Key Generation**: Hash period + limit parameters
2. **Check Cache**: Look for file in temp directory
3. **Validate TTL**: Check if file is < 5 minutes old
4. **Return**: If valid cache, return cached data
5. **Compute**: If no cache, calculate rankings
6. **Store**: Save to cache file with timestamp

### Cache Location
- **Directory**: `sys_get_temp_dir() . '/scroll_novels_rankings/'`
- **Windows**: Usually `C:\Windows\Temp\scroll_novels_rankings\`
- **Linux**: Usually `/tmp/scroll_novels_rankings/`

### Cache Files
```
scroll_novels_rankings/
├── 8f14e45fceea167a5a36dedd4bea2543.cache  # daily rankings
├── 5d41402abc4b2a76b9719d911017c592.cache  # weekly rankings
└── 6512bd43d9caa6e02c990b0a82652dca.cache  # monthly rankings
```

### Cache Keys
```php
// Story rankings cache key
"rankings:stories:{period}:limit:{limit}"
// Example: "rankings:stories:daily:limit:50"

// Writer rankings cache key
"rankings:writers:{period}:limit:{limit}"
// Example: "rankings:writers:monthly:limit:200"
```

### TTL
- **Default**: 300 seconds (5 minutes)
- **Reason**: Balances freshness with performance
- **Customizable**: Modify `$cacheTTL` property

### Invalidation
Cache is automatically invalidated when:
- `recordStat()` called (new data recorded)
- Cache file expires (5 minutes)
- Manual call to `clearCache()` (if implemented)

---

## Period Filtering

### Period Definitions

**Daily**: Last 24 hours
```php
FROM = today at 00:00:00
TO = today at 23:59:59
```

**Weekly**: Last 7 days
```php
FROM = today - 7 days
TO = today
```

**Monthly**: Last 30 days
```php
FROM = today - 30 days
TO = today
```

### Implementation
```php
private function getFromDate($period) {
    $date = new DateTime();
    
    switch ($period) {
        case 'daily':
            return $date->format('Y-m-d'); // Today
        case 'weekly':
            $date->modify('-7 days');
            return $date->format('Y-m-d');
        case 'monthly':
        default:
            $date->modify('-30 days');
            return $date->format('Y-m-d');
    }
}
```

### Query Impact
```sql
-- Daily
SELECT * FROM story_stats WHERE date = CURDATE()

-- Weekly
SELECT * FROM story_stats WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)

-- Monthly
SELECT * FROM story_stats WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
```

---

## Database Schema

### story_stats Table
```sql
CREATE TABLE story_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    date DATE NOT NULL,
    views INT DEFAULT 0,
    unique_views INT DEFAULT 0,
    favorites INT DEFAULT 0,
    comments INT DEFAULT 0,
    reading_seconds INT DEFAULT 0,
    boosts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_stat (story_id, date),
    INDEX idx_date (date),
    INDEX idx_story_date (story_id, date)
);
```

### Schema Design Rationale

**Columns**:
- `id`: Auto-increment primary key
- `story_id`: FK to stories table
- `date`: Aggregation date (daily stats)
- Metrics: 6 engagement metrics
- `created_at`: Timestamp for audit
- Indexes: Optimized for common queries

**Key Decisions**:
- One row per story per day
- Composite unique key prevents duplicates
- Date index for period queries
- Story+date composite for specific lookups

### Query Performance
```
Rows: 1000 stories × 365 days = 365,000 rows/year
Size: ~10 MB (with indexes)
Query Time: <10ms for daily rankings (with indexes)
```

---

## API Endpoints Reference

### GET /api/rankings/stories.php

**Request**:
```
GET /api/rankings/stories.php?period=daily&limit=50
```

**Parameters**:
| Name | Type | Default | Valid Values |
|------|------|---------|--------------|
| period | string | daily | daily, weekly, monthly |
| limit | int | 50 | 1-200 |

**Response (Success)**:
```json
{
  "success": true,
  "period": "daily",
  "items": [
    {
      "rank": 1,
      "story_id": 42,
      "story_title": "Dragon's Legacy",
      "author": "ProWriter",
      "score": 0.8725,
      "metrics": {
        "views": 1000,
        "unique_views": 750,
        "favorites": 150,
        "comments": 45,
        "reading_seconds": 54000,
        "boosts": 5
      }
    }
  ]
}
```

**Response (Empty)**:
```json
{
  "success": true,
  "period": "daily",
  "message": "No rankings available for this period",
  "items": []
}
```

**Response (Error)**:
```json
{
  "success": false,
  "error": "Invalid period. Use: daily, weekly, monthly"
}
```

### GET /api/rankings/writers.php

**Request**:
```
GET /api/rankings/writers.php?period=monthly&limit=200
```

**Parameters**:
| Name | Type | Default | Valid Values |
|------|------|---------|--------------|
| period | string | monthly | daily, weekly, monthly |
| limit | int | 200 | 1-200 |

**Response (Success)**:
```json
{
  "success": true,
  "period": "monthly",
  "items": [
    {
      "rank": 1,
      "author_id": 5,
      "username": "ProWriter",
      "total_views": 150000
    }
  ]
}
```

---

## Integration Examples

### Example 1: Display Top 10 Stories (HTML)
```php
<?php
$rankingService = new RankingService($pdo);
$rankings = $rankingService->getStoryRankings('daily', 10);
?>
<div class="rankings">
  <?php foreach ($rankings as $story): ?>
    <div class="ranking-item">
      <span class="rank">#<?= $story['rank'] ?></span>
      <h3><?= htmlspecialchars($story['story_title']) ?></h3>
      <p>by <?= htmlspecialchars($story['author']) ?></p>
      <div class="score" style="width: <?= ($story['score'] * 100) ?>%">
        <?= number_format($story['score'] * 100, 1) ?>%
      </div>
    </div>
  <?php endforeach; ?>
</div>
```

### Example 2: Record Story View (PHP)
```php
<?php
// When user views a story
$storyId = $_GET['story_id'];
$rankingService = new RankingService($pdo);

try {
    $rankingService->recordStat($storyId, 'views', 1);
    $rankingService->recordStat($storyId, 'unique_views', 1);
} catch (Exception $e) {
    error_log("Failed to record stats: " . $e->getMessage());
}
?>
```

### Example 3: Top Writers Widget (PHP)
```php
<?php
$rankingService = new RankingService($pdo);
$topWriters = $rankingService->getTopWriters('monthly', 5);
?>
<aside class="top-writers">
  <h3>Top Writers This Month</h3>
  <ol>
    <?php foreach ($topWriters as $writer): ?>
      <li>
        <strong><?= htmlspecialchars($writer['username']) ?></strong>
        <span class="views"><?= number_format($writer['total_views']) ?> views</span>
      </li>
    <?php endforeach; ?>
  </ol>
</aside>
```

### Example 4: AJAX Update (JavaScript)
```javascript
// Update rankings every 5 minutes
function updateRankings() {
  fetch('/api/rankings/stories.php?period=daily&limit=5')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.items.length > 0) {
        const html = data.items.map(story => `
          <li>
            <span class="rank">#${story.rank}</span>
            <strong>${story.story_title}</strong>
            <small>${story.author}</small>
            <span class="score">${(story.score * 100).toFixed(1)}%</span>
          </li>
        `).join('');
        document.getElementById('rankings').innerHTML = html;
      }
    })
    .catch(error => console.error('Error:', error));
}

// Poll every 5 minutes
setInterval(updateRankings, 300000);
updateRankings(); // Initial load
```

---

## Troubleshooting

### Issue: Rankings Show Empty

**Causes**:
1. story_stats table not created
2. No stats recorded yet
3. Date range has no data

**Solutions**:
```php
// Check if table exists
$result = $pdo->query("DESCRIBE story_stats");
echo $result ? "Table exists" : "Table missing";

// Check if data exists
$result = $pdo->query("SELECT COUNT(*) FROM story_stats");
$count = $result->fetchColumn();
echo "Stats records: $count";

// Insert test data
$pdo->exec("INSERT INTO story_stats (story_id, date, views, unique_views, favorites) 
            VALUES (1, CURDATE(), 100, 75, 10)");
```

### Issue: API Returns 500 Error

**Debug Steps**:
```php
// Check PDO connection
try {
    $result = $pdo->query("SELECT 1");
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}

// Check file paths
echo realpath('includes/RankingService.php');

// Check cache directory
echo is_writable(sys_get_temp_dir()) ? "Cache dir writable" : "Cache dir not writable";
```

### Issue: Slow Response Times

**Performance Check**:
```php
// Measure query time
$start = microtime(true);
$rankings = $rankingService->getStoryRankings('daily', 50);
$time = microtime(true) - $start;
echo "Query time: " . ($time * 1000) . "ms";

// Check if cached
$cached = $rankingService->getFromCache('rankings:stories:daily:limit:50');
echo $cached ? "Using cache" : "Cache miss - computing";
```

### Issue: Cache Not Working

**Verify**:
```php
// Check temp directory
$tempDir = sys_get_temp_dir() . '/scroll_novels_rankings';
echo "Cache dir: $tempDir\n";
echo "Exists: " . (is_dir($tempDir) ? "yes" : "no") . "\n";
echo "Writable: " . (is_writable($tempDir) ? "yes" : "no") . "\n";

// Check cache files
$files = glob($tempDir . '/*.cache');
echo "Cache files: " . count($files);
```

---

## Performance Tuning

### Optimize Query Speed
```sql
-- Verify indexes exist
SHOW INDEXES FROM story_stats;

-- If missing, add indexes
ALTER TABLE story_stats ADD INDEX idx_date (date);
ALTER TABLE story_stats ADD INDEX idx_story_date (story_id, date);

-- Check query explain plan
EXPLAIN SELECT * FROM story_stats WHERE date >= '2024-01-01';
```

### Optimize Memory Usage
```php
// Reduce limit for large result sets
$rankings = $rankingService->getStoryRankings('daily', 25); // Was 50

// Process in batches if needed
$limit = 100;
$offset = 0;
while (true) {
    // Process each batch
    $offset += $limit;
}
```

### Monitor Cache Effectiveness
```php
// Add cache statistics
class RankingService {
    private $cacheHits = 0;
    private $cacheMisses = 0;
    
    public function getCacheStats() {
        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'hit_rate' => $this->cacheHits / ($this->cacheHits + $this->cacheMisses)
        ];
    }
}
```

---

## Security Considerations

### SQL Injection Prevention
```php
// ✅ SAFE - Uses prepared statements
$stmt = $pdo->prepare("SELECT * FROM story_stats WHERE story_id = ? AND date >= ?");
$stmt->execute([$storyId, $fromDate]);

// ❌ UNSAFE - String concatenation
$query = "SELECT * FROM story_stats WHERE story_id = " . $_GET['id'];
```

### Input Validation
```php
// Validate period parameter
$validPeriods = ['daily', 'weekly', 'monthly'];
$period = $_GET['period'] ?? 'daily';
if (!in_array($period, $validPeriods)) {
    throw new InvalidArgumentException("Invalid period");
}

// Validate limit
$limit = (int)($_GET['limit'] ?? 50);
if ($limit < 1 || $limit > 200) {
    throw new InvalidArgumentException("Limit out of range");
}
```

### API Security
- ✅ Input validation on all parameters
- ✅ Prepared statements for all queries
- ✅ Error messages don't expose database details
- ✅ JSON response format prevents injection
- ✅ Ready for rate limiting integration

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Current | Initial release |

---

## Support & Maintenance

### Maintenance Tasks
- [ ] Weekly: Check cache hit rates
- [ ] Monthly: Archive old stats (keep 12 months)
- [ ] Quarterly: Review weights vs actual performance
- [ ] Quarterly: Check database size and optimize if needed

### Monitoring
- Query response times
- Cache hit rates
- Database index fragmentation
- Storage usage

### Scaling Considerations
- If 1M+ stories: Consider partitioning table by date
- If traffic spikes: Implement Redis caching
- If compute heavy: Add background job for aggregation

---

## License & Attribution

This ranking system is part of the Scroll Novels project.

---

**Last Updated**: Current Session
**Maintained By**: Development Team
**Status**: Production Ready
