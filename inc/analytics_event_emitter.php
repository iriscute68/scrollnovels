<?php
/**
 * Analytics Event Emitter
 * 
 * Log application events for analytics, monitoring, and reporting
 * Stores events in database and optionally publishes to Redis for real-time processing
 * 
 * Event types: book_read, book_comment, user_follow, story_published, competition_entry,
 *              competition_submission, user_signup, payment_received, etc.
 * 
 * Usage:
 *   emit_event($pdo, 'book_read', $userId, $bookId, ['location' => 'homepage']);
 *   emit_event($pdo, 'competition_entry', $userId, $entryId, $meta, true);
 */

/**
 * Emit event for analytics
 * 
 * @param PDO $pdo Database connection
 * @param string $type Event type (e.g., 'book_read', 'user_follow', 'competition_entry')
 * @param int|null $userId User ID (null for anonymous events)
 * @param int|null $objId Related object ID (book, competition, etc.)
 * @param array|null $meta Additional metadata as associative array
 * @param bool $publishRedis Whether to publish to Redis for real-time processing (default: true)
 * @return int|false Event ID if successful, false if failed
 */
function emit_event(PDO $pdo, string $type, ?int $userId = null, ?int $objId = null, $meta = null, bool $publishRedis = true) {
  try {
    // Insert event to database
    $stmt = $pdo->prepare("INSERT INTO events (user_id, type, obj_id, meta, created_at) 
                          VALUES (?, ?, ?, ?, NOW())");
    
    $metaJson = null;
    if ($meta) {
      if (is_string($meta)) {
        $metaJson = $meta;
      } else if (is_array($meta)) {
        $metaJson = json_encode($meta);
      }
    }
    
    $stmt->execute([$userId, $type, $objId, $metaJson]);
    $eventId = $pdo->lastInsertId();
    
    // Publish to Redis if enabled
    if ($publishRedis) {
      try {
        $rc = new RedisClient();
        if ($rc->isConnected()) {
          $payload = [
            'id' => $eventId,
            'type' => $type,
            'user_id' => $userId,
            'obj_id' => $objId,
            'meta' => is_array($meta) ? $meta : ($meta ? json_decode($meta, true) : null),
            'created_at' => date('c')
          ];
          $rc->publish('events', json_encode($payload));
          $rc->close();
        }
      } catch (Exception $e) {
        // Redis not available or failed - continue without it
        error_log("Event Redis publish failed: " . $e->getMessage());
      }
    }
    
    return $eventId;
    
  } catch (PDOException $e) {
    error_log("Event emission database error: " . $e->getMessage());
    return false;
  }
}

/**
 * Get recent events with filtering
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Maximum events to return
 * @param int $offset Pagination offset
 * @param string|null $type Filter by event type
 * @param int|null $userId Filter by user ID
 * @return array Events array
 */
function get_events(PDO $pdo, int $limit = 50, int $offset = 0, ?string $type = null, ?int $userId = null): array {
  try {
    $query = "SELECT id, user_id, type, obj_id, meta, created_at FROM events WHERE 1=1";
    $params = [];
    
    if ($type) {
      $query .= " AND type = ?";
      $params[] = $type;
    }
    
    if ($userId) {
      $query .= " AND user_id = ?";
      $params[] = $userId;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
  } catch (PDOException $e) {
    error_log("Get events error: " . $e->getMessage());
    return [];
  }
}

/**
 * Count events with optional filters
 * 
 * @param PDO $pdo Database connection
 * @param string|null $type Filter by event type
 * @param int|null $userId Filter by user ID
 * @return int Event count
 */
function count_events(PDO $pdo, ?string $type = null, ?int $userId = null): int {
  try {
    $query = "SELECT COUNT(*) FROM events WHERE 1=1";
    $params = [];
    
    if ($type) {
      $query .= " AND type = ?";
      $params[] = $type;
    }
    
    if ($userId) {
      $query .= " AND user_id = ?";
      $params[] = $userId;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return intval($stmt->fetchColumn());
    
  } catch (PDOException $e) {
    error_log("Count events error: " . $e->getMessage());
    return 0;
  }
}

/**
 * Get event summary/statistics
 * 
 * @param PDO $pdo Database connection
 * @param DateTime|null $fromDate Filter events from this date (default: 7 days ago)
 * @param DateTime|null $toDate Filter events until this date (default: now)
 * @return array Statistics with event counts by type
 */
function get_events_summary(PDO $pdo, ?DateTime $fromDate = null, ?DateTime $toDate = null): array {
  try {
    if (!$fromDate) {
      $fromDate = new DateTime('-7 days');
    }
    if (!$toDate) {
      $toDate = new DateTime();
    }
    
    $query = "SELECT type, COUNT(*) as count, COUNT(DISTINCT user_id) as unique_users, 
                     MIN(created_at) as first_event, MAX(created_at) as last_event
              FROM events 
              WHERE created_at BETWEEN ? AND ? 
              GROUP BY type 
              ORDER BY count DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fromDate->format('Y-m-d H:i:s'), $toDate->format('Y-m-d H:i:s')]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
  } catch (PDOException $e) {
    error_log("Events summary error: " . $e->getMessage());
    return [];
  }
}

/**
 * Record daily competition metrics
 * 
 * @param PDO $pdo Database connection
 * @param int $competitionId Competition ID
 * @param int $registrations New registrations count
 * @param int $submissions New submissions count
 * @param int $readers New readers count
 * @param int $clicks Click interactions count
 * @param DateTime|null $day Date for metrics (default: today)
 * @return bool Success
 */
function record_competition_metrics(PDO $pdo, int $competitionId, int $registrations = 0, 
                                   int $submissions = 0, int $readers = 0, int $clicks = 0, 
                                   ?DateTime $day = null): bool {
  try {
    if (!$day) {
      $day = new DateTime();
    }
    
    $dayStr = $day->format('Y-m-d');
    
    // Try to update existing record
    $stmt = $pdo->prepare("UPDATE daily_competition_metrics 
                          SET registrations = registrations + ?,
                              submissions = submissions + ?,
                              readers = readers + ?,
                              clicks = clicks + ?
                          WHERE competition_id = ? AND day = ?");
    $stmt->execute([$registrations, $submissions, $readers, $clicks, $competitionId, $dayStr]);
    
    // If no rows updated, insert new record
    if ($stmt->rowCount() === 0) {
      $stmt = $pdo->prepare("INSERT INTO daily_competition_metrics 
                            (competition_id, day, registrations, submissions, readers, clicks, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
      $stmt->execute([$competitionId, $dayStr, $registrations, $submissions, $readers, $clicks]);
    }
    
    return true;
    
  } catch (PDOException $e) {
    error_log("Record metrics error: " . $e->getMessage());
    return false;
  }
}

/**
 * Get competition metrics for date range
 * 
 * @param PDO $pdo Database connection
 * @param int $competitionId Competition ID
 * @param DateTime|null $fromDate Start date
 * @param DateTime|null $toDate End date
 * @return array Daily metrics data
 */
function get_competition_metrics(PDO $pdo, int $competitionId, ?DateTime $fromDate = null, ?DateTime $toDate = null): array {
  try {
    $query = "SELECT day, registrations, submissions, readers, clicks 
              FROM daily_competition_metrics 
              WHERE competition_id = ?";
    $params = [$competitionId];
    
    if ($fromDate) {
      $query .= " AND day >= ?";
      $params[] = $fromDate->format('Y-m-d');
    }
    
    if ($toDate) {
      $query .= " AND day <= ?";
      $params[] = $toDate->format('Y-m-d');
    }
    
    $query .= " ORDER BY day ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
  } catch (PDOException $e) {
    error_log("Get metrics error: " . $e->getMessage());
    return [];
  }
}

// Require Redis client for event publishing
require_once __DIR__ . '/redis_client.php';

?>
