<?php
/**
 * Redis Client Wrapper
 * 
 * Provides simple interface for Redis operations:
 * - Publishing notifications to channels
 * - Managing leaderboards (sorted sets)
 * - Generic key-value operations
 * 
 * Usage:
 *   $rc = new RedisClient();
 *   $rc->publish('notifications:user:123', json_encode($notification));
 *   $rc->zadd('leaderboard:1', 95, 'book:42');
 */

class RedisClient {
  private $redis;
  private $connected = false;

  /**
   * Create Redis connection
   * 
   * @param string $host Redis host (default: 127.0.0.1)
   * @param int $port Redis port (default: 6379)
   * @param int $timeout Connection timeout in seconds
   */
  public function __construct($host = '127.0.0.1', $port = 6379, $timeout = 2) {
    try {
      $this->redis = new Redis();
      $this->redis->connect($host, $port, $timeout);
      $this->connected = $this->redis->ping();
    } catch (Exception $e) {
      error_log("Redis connection failed: " . $e->getMessage());
      $this->connected = false;
    }
  }

  /**
   * Check if Redis is connected
   */
  public function isConnected(): bool {
    return $this->connected;
  }

  /**
   * Publish message to channel
   * 
   * @param string $channel Channel name
   * @param mixed $message Message (string or array to JSON encode)
   * @return int Number of subscribers that received the message
   */
  public function publish($channel, $message): int {
    if (!$this->connected) return 0;
    
    try {
      $payload = is_string($message) ? $message : json_encode($message);
      return $this->redis->publish($channel, $payload);
    } catch (Exception $e) {
      error_log("Redis publish failed: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Add score to sorted set (leaderboard)
   * 
   * @param string $key Leaderboard key (e.g., 'competition:1:leaderboard')
   * @param float $score Score value
   * @param string $member Member name (e.g., 'book:42')
   * @return int 1 if added, 0 if updated
   */
  public function zadd($key, $score, $member): int {
    if (!$this->connected) return 0;
    
    try {
      return $this->redis->zadd($key, $score, $member);
    } catch (Exception $e) {
      error_log("Redis zadd failed: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Get top N members from sorted set with scores
   * 
   * @param string $key Leaderboard key
   * @param int $start Start index (0 = top)
   * @param int $end End index (-1 = all)
   * @param bool $withScores Include scores in result
   * @return array Members with scores, e.g., ['book:42' => 95, 'book:5' => 87]
   */
  public function zrevrangeWithScores($key, $start = 0, $end = 9, $withScores = true): array {
    if (!$this->connected) return [];
    
    try {
      return $this->redis->zRevRange($key, $start, $end, $withScores);
    } catch (Exception $e) {
      error_log("Redis zrevrange failed: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get rank (position) of member in sorted set
   * 
   * @param string $key Leaderboard key
   * @param string $member Member name
   * @return int|false Rank (0 = first) or false if not found
   */
  public function zrevrank($key, $member) {
    if (!$this->connected) return false;
    
    try {
      return $this->redis->zRevRank($key, $member);
    } catch (Exception $e) {
      error_log("Redis zrevrank failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get score of member in sorted set
   * 
   * @param string $key Leaderboard key
   * @param string $member Member name
   * @return float|false Score or false if not found
   */
  public function zscore($key, $member) {
    if (!$this->connected) return false;
    
    try {
      return $this->redis->zScore($key, $member);
    } catch (Exception $e) {
      error_log("Redis zscore failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Generic SET operation
   * 
   * @param string $key Key name
   * @param mixed $value Value (string or will be JSON encoded)
   * @param int $ttl Time to live in seconds (0 = no expiry)
   * @return bool Success
   */
  public function set($key, $value, $ttl = 0): bool {
    if (!$this->connected) return false;
    
    try {
      $val = is_string($value) ? $value : json_encode($value);
      if ($ttl > 0) {
        return $this->redis->setex($key, $ttl, $val);
      }
      return $this->redis->set($key, $val);
    } catch (Exception $e) {
      error_log("Redis set failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Generic GET operation
   * 
   * @param string $key Key name
   * @param bool $json Parse as JSON
   * @return mixed Value or false if not found
   */
  public function get($key, $json = false) {
    if (!$this->connected) return false;
    
    try {
      $value = $this->redis->get($key);
      if ($value === false) return false;
      return $json ? json_decode($value, true) : $value;
    } catch (Exception $e) {
      error_log("Redis get failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Delete key
   * 
   * @param string $key Key name
   * @return int Number of keys deleted
   */
  public function delete($key): int {
    if (!$this->connected) return 0;
    
    try {
      return $this->redis->delete($key);
    } catch (Exception $e) {
      error_log("Redis delete failed: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Increment counter
   * 
   * @param string $key Key name
   * @param int $amount Amount to increment
   * @return int New value
   */
  public function incr($key, $amount = 1): int {
    if (!$this->connected) return 0;
    
    try {
      if ($amount === 1) {
        return $this->redis->incr($key);
      }
      return $this->redis->incrBy($key, $amount);
    } catch (Exception $e) {
      error_log("Redis incr failed: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Close connection
   */
  public function close() {
    if ($this->redis) {
      try {
        $this->redis->close();
      } catch (Exception $e) {
        error_log("Redis close failed: " . $e->getMessage());
      }
    }
  }

  /**
   * Destructor - close connection
   */
  public function __destruct() {
    $this->close();
  }
}

?>
