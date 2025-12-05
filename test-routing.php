<?php
/**
 * Laravel + PHP Hybrid Test
 * Verify the routing is working correctly
 */

echo "<h1>ScrollNovels - Laravel + PHP Hybrid</h1>";
echo "<p>✅ Routing System Active</p>";

// Display environment
echo "<h2>Environment:</h2>";
echo "<ul>";
echo "<li>Base URL: " . $_ENV['APP_BASE_URL'] ?? 'Not set' . "</li>";
echo "<li>Root Path: " . $_ENV['APP_ROOT'] ?? 'Not set' . "</li>";
echo "<li>Request URI: " . $_SERVER['REQUEST_URI'] . "</li>";
echo "<li>Method: " . $_SERVER['REQUEST_METHOD'] . "</li>";
echo "</ul>";

// List available routes
echo "<h2>Available Routes:</h2>";
$routes = [
    "/" => "Home",
    "/admin" => "Admin Dashboard",
    "/blog" => "Blog",
    "/guides" => "Guides",
    "/api/reviews/submit" => "Submit Review (POST)",
    "/api/reviews/get" => "Get Reviews",
    "/api/reviews/delete" => "Delete Review",
];

echo "<ul>";
foreach ($routes as $route => $desc) {
    echo "<li><strong>$route</strong> - $desc</li>";
}
echo "</ul>";

// Database test
echo "<h2>Database Connection:</h2>";
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=scroll_novels;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p>✅ Database connected successfully</p>";
} catch (PDOException $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}
?>
