<?php
declare(strict_types=1);
// inc/db_pdo.php - single place to create a PDO connection

// Attempt to pull DB credentials from constants defined by config/db.php or from env.
$dbHost = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
$dbName = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'scroll_novels');
$dbUser = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
$dbPass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

// Create PDO
try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Log and present a generic error
    error_log("DB connection failed: " . $e->getMessage());
    // In production, do not echo DB errors
    if (php_sapi_name() === 'cli') {
        throw $e;
    }
    http_response_code(500);
    exit('Database connection error');
}

// Helper to fetch single row quickly
function db_fetch_one(PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Helper to fetch all rows
function db_fetch_all(PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Helper to execute (insert/update/delete)
function db_execute(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
?>
