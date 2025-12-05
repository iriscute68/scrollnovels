<?php
// admin/inc/db.php
$config = include __DIR__ . '/config.php';
$db = $config['db'];

$dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
try {
  $pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo "DB Connection error: " . $e->getMessage();
  exit;
}

function isAdminLoggedIn() {
  return isset($_SESSION['admin_id']);
}

function getAdminUsername() {
  return $_SESSION['admin_username'] ?? 'Admin';
}
?>
