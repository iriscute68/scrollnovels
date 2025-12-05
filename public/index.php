<?php
/**
 * ScrollNovels - Laravel Entry Point with PHP Wrapper
 * Routes requests to your existing PHP structure
 */

define('LARAVEL_START', microtime(true));

// Get the requested URI
$baseUri = '/scrollnovels';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path
if (strpos($uri, $baseUri) === 0) {
    $uri = substr($uri, strlen($baseUri));
}
if (empty($uri)) $uri = '/';

$method = $_SERVER['REQUEST_METHOD'];

// Route to your existing PHP structure
$mapping = [
    // Home pages
    '/' => ['file' => '/index.php'],
    '/home' => ['file' => '/index.php'],
    
    // API endpoints
    '/api/reviews/submit' => ['file' => '/api/reviews/submit-review.php'],
    '/api/reviews/get' => ['file' => '/api/reviews/get-reviews.php'],
    '/api/reviews/delete' => ['file' => '/api/reviews/delete-review.php'],
    '/api/reviews/report' => ['file' => '/api/reviews/report-review.php'],
    
    // Admin pages
    '/admin' => ['file' => '/admin/index.php'],
    '/admin/dashboard' => ['file' => '/admin/dashboard.php'],
    '/admin/login' => ['file' => '/admin/login.php'],
    '/admin/logout' => ['file' => '/admin/logout.php'],
    '/admin/panel' => ['file' => '/admin/panel.php'],
    
    // Blog
    '/blog' => ['file' => '/blog/index.php'],
    
    // Guides
    '/guides' => ['file' => '/guides/index.php'],
    
    // Other pages
    '/about' => ['file' => '/about.php'],
    '/contact' => ['file' => '/contact.php'],
];

// Find matching route
$targetFile = null;
foreach ($mapping as $pattern => $route) {
    if ($uri === $pattern) {
        $targetFile = $route['file'];
        break;
    }
}

// If no exact match, try to find the file directly
if (!$targetFile) {
    // Check if file exists directly (e.g., /page.php)
    if (file_exists(__DIR__ . '/..' . $uri . '.php')) {
        $targetFile = $uri . '.php';
    }
}

// Include and execute the target file
if ($targetFile && file_exists(__DIR__ . '/..' . $targetFile)) {
    // Set up environment
    $_ENV['APP_BASE_URL'] = '/scrollnovels';
    $_ENV['APP_ROOT'] = __DIR__ . '/..';
    
    // Include the file
    require __DIR__ . '/..' . $targetFile;
} else {
    // If not found, try default Laravel bootstrap as fallback
    if (file_exists(__DIR__.'/../vendor/autoload.php')) {
        define('LARAVEL_BOOTSTRAP', true);
        require __DIR__.'/../vendor/autoload.php';
        
        // Try to load bootstrap
        if (file_exists(__DIR__.'/../bootstrap/app.php')) {
            try {
                $app = require __DIR__.'/../bootstrap/app.php';
                $app->handleRequest(\Illuminate\Http\Request::capture());
            } catch (Exception $e) {
                http_response_code(404);
                echo "404 - Page not found: $uri";
            }
        }
    } else {
        http_response_code(404);
        echo "404 - Page not found: $uri";
    }
}
