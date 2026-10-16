<?php
/**
 * Application Bootstrap File
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 * 
 * This file initializes the application and loads all required components
 */

// Define access constant
define('APP_ACCESS', true);

// Start output buffering
ob_start();

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Create required directories if they don't exist
$requiredDirs = [
    ROOT_PATH . 'logs',
    ROOT_PATH . 'uploads',
    ROOT_PATH . 'uploads/documents',
    ROOT_PATH . 'uploads/photos'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Load core classes
require_once CLASSES_PATH . 'Database.php';
require_once CLASSES_PATH . 'Auth.php';

// Load business logic classes
$classFiles = [
    'RuleEngine.php',
    'LoanManager.php',
    'SavingsManager.php',
    'SHUManager.php',
    'TransactionManager.php',
    'NotificationManager.php',
    'ReportManager.php'
];

foreach ($classFiles as $file) {
    $filePath = CLASSES_PATH . $file;
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}

// Load utility functions
require_once INCLUDES_PATH . 'functions.php';

// Load admin helpers and shared admin layout
if (file_exists(INCLUDES_PATH . 'admin-functions.php')) {
    require_once INCLUDES_PATH . 'admin-functions.php';
}
if (file_exists(INCLUDES_PATH . 'admin-layout.php')) {
    require_once INCLUDES_PATH . 'admin-layout.php';
}

// Initialize global objects
try {
    $db = Database::getInstance();
    $dbHelper = new DatabaseHelper();
    $auth = new Auth();
} catch (Exception $e) {
    error_log("Initialization error: " . $e->getMessage());
    die("System initialization failed. Please contact administrator.");
}

// Set error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $error = "[" . date('Y-m-d H:i:s') . "] Error: {$message} in {$file}:{$line}" . PHP_EOL;
    file_put_contents(ROOT_PATH . 'logs/php_errors.log', $error, FILE_APPEND | LOCK_EX);
    
    // Don't execute PHP's internal error handler
    return true;
});

// Set exception handler
set_exception_handler(function($exception) {
    $error = "[" . date('Y-m-d H:i:s') . "] Uncaught Exception: " . $exception->getMessage() . 
             " in " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
    file_put_contents(ROOT_PATH . 'logs/exceptions.log', $error, FILE_APPEND | LOCK_EX);
    
    // Show generic error page with safe fallback if template is missing
    http_response_code(500);
    $errorTemplate = ROOT_PATH . 'templates/error.php';
    if (file_exists($errorTemplate)) {
        include $errorTemplate;
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Terjadi Kesalahan</title></head><body>';
        echo '<h2>Terjadi kesalahan pada sistem</h2>';
        echo '<p>Silakan coba lagi nanti atau hubungi administrator.</p>';
        echo '</body></html>';
    }
    exit;
});

?>
