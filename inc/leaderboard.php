<?php
/**
 * Leaderboard Helper Functions
 * 
 * Redis-backed leaderboard utilities for competitions
 * Maintains top scores, rankings, and automatic updates
 * 
 * Usage:
 *   leaderboard_add_score(1, 42, 95.5);
 *   $top = leaderboard_top(1, 10);
 *   $rank = leaderboard_rank(1, 'book:42');
 */

require_once __DIR__ . '/redis_client.php';

/**
 * Add or update score in competition leaderboard
 * 
 * @param int $competitionId Competition ID
 * @param int $bookId Story/book ID
 * @param float $score Score value (higher is better)
 * @return bool Success
 */
function leaderboard_add_score($competitionId, $bookId, $score): bool {
  $rc = new RedisClient();
  if (!$rc->isConnected()) {
    return false;
  }
  
  $key = 'competition:' . $competitionId . ':leaderboard';
  $member = 'book:' . $bookId;
  
  $result = $rc->zadd($key, floatval($score), $member);
  return $result !== false;
}

/**
 * Get top N entries from leaderboard
 * 
 * @param int $competitionId Competition ID
 * @param int $limit Number of top entries (default: 10)
 * @return array Top entries with scores, e.g., [['book_id' => 42, 'score' => 95]]
 */
function leaderboard_top($competitionId, $limit = 10): array {
  $rc = new RedisClient();
  if (!$rc->isConnected()) {
    return [];
  }
  
  $key = 'competition:' . $competitionId . ':leaderboard';
  $results = $rc->zrevrangeWithScores($key, 0, $limit - 1, true);
  
  // Transform format
  $formatted = [];
  if (is_array($results)) {
    $rank = 1;
    foreach ($results as $member => $score) {
      $bookId = str_replace('book:', '', $member);
      $formatted[] = [
        'rank' => $rank++,
        'book_id' => intval($bookId),
        'member' => $member,
        'score' => floatval($score)
      ];
    }
  }
  
  return $formatted;
}

/**
 * Get rank (position) of book in leaderboard
 * 
 * @param int $competitionId Competition ID
 * @param int $bookId Story/book ID
 * @return int|false Rank (1 = first) or false if not found
 */
function leaderboard_rank($competitionId, $bookId) {
  $rc = new RedisClient();
  if (!$rc->isConnected()) {
    return false;
  }
  
  $key = 'competition:' . $competitionId . ':leaderboard';
  $member = 'book:' . $bookId;
  
  $rank = $rc->zrevrank($key, $member);
  if ($rank === false) {
    return false;
  }
  
  return intval($rank) + 1; // Redis is 0-indexed, we want 1-indexed
}

/**
 * Get score of book in leaderboard
 * 
 * @param int $competitionId Competition ID
 * @param int $bookId Story/book ID
 * @return float|false Score or false if not found
 */
function leaderboard_score($competitionId, $bookId) {
  $rc = new RedisClient();
  if (!$rc->isConnected()) {
    return false;
  }
  
  $key = 'competition:' . $competitionId . ':leaderboard';
  $member = 'book:' . $bookId;
  
  return $rc->zscore($key, $member);
}

/**
 * Get book's position and stats in leaderboard
 * 
 * @param int $competitionId Competition ID
 * @param int $bookId Story/book ID
 * @return array|false Entry data with rank and score, or false
 */
function leaderboard_entry($competitionId, $bookId) {
  $rank = leaderboard_rank($competitionId, $bookId);
  $score = leaderboard_score($competitionId, $bookId);
  
  if ($rank === false || $score === false) {
    return false;
  }
  
  return [
    'book_id' => $bookId,
    'rank' => $rank,
    'score' => $score
  ];
}

/**
 * Clear entire leaderboard
 * 
 * @param int $competitionId Competition ID
 * @return bool Success
 */
function leaderboard_clear($competitionId): bool {
  $rc = new RedisClient();
  if (!$rc->isConnected()) {
    return false;
  }
  
  $key = 'competition:' . $competitionId . ':leaderboard';
  return $rc->delete($key) >= 0;
}

/**
 * Get leaderboard size (number of entries)
 * 
 * @param int $competitionId Competition ID
 * @return int Number of entries
 */
function leaderboard_count($competitionId): int {
  $rc = new RedisClient();
  if (!$rc->isConnected()) {
    return 0;
  }
  
  $key = 'competition:' . $competitionId . ':leaderboard';
  try {
    return $rc->redis->zCard($key);
  } catch (Exception $e) {
    error_log("Redis zcard failed: " . $e->getMessage());
    return 0;
  }
}

?>
