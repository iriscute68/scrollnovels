<?php
/**
 * ScrollNovels - Laravel Wrapper
 * This file bridges your existing PHP project with Laravel structure
 */

// Set base path
define('BASE_PATH', __DIR__);
define('LARAVEL_START', microtime(true));

// Load your existing config
require_once BASE_PATH . '/config.php';

// Create Laravel app instance simulator (optional - for compatibility)
class LaravelApp {
    public static $basePath = BASE_PATH;
    public static $config = [];
    
    public static function basePath($path = '') {
        return self::$basePath . ($path ? '/' . $path : '');
    }
    
    public static function config($key, $default = null) {
        return self::$config[$key] ?? $default;
    }
}

// Register autoloader
spl_autoload_register(function($class) {
    // Autoload your existing PHP classes
    $paths = [
        BASE_PATH . '/includes/',
        BASE_PATH . '/app/',
        BASE_PATH . '/app/Http/Controllers/',
        BASE_PATH . '/app/Models/',
    ];
    
    foreach ($paths as $path) {
        $file = $path . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

return LaravelApp::class;
