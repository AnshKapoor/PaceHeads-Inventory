<?php
// Include your manual .env loader
require_once __DIR__ . '/load_env.php';

ini_set('display_errors', 'Off'); // Do NOT show errors to users in production
ini_set('log_errors', 'On');     // Ensure PHP's default logging is ON too
ini_set('error_log', __DIR__ . '/logs/php_error.log'); // PHP's default error log file

// Database Connection Configuration - now read from environment variables
// These will come from your .env file locally, or from Hostinger's environment variables on cloud.

// Using getenv() is generally preferred over $_ENV[] when dealing with putenv()
// as $_ENV is only populated based on server environment variables, not necessarily putenv()
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

$document_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$project_root_abs_path = str_replace('\\', '/', __DIR__);
$project_root_url_segment = str_replace($document_root, '', $project_root_abs_path);

$project_root_url_segment = '/' . trim($project_root_url_segment, '/');
if ($project_root_url_segment !== '/') {
    $project_root_url_segment .= '/';
}
// Get the path portion of the current script, e.g., /PaceHeads/dashboard/index.php
$script_name = $_SERVER['SCRIPT_NAME'];

$project_folder_name_in_url = 'PaceHeads';

// Try to find the project folder name in the script path
$pos = strpos($script_name, '/' . $project_folder_name_in_url . '/');

if ($pos !== false) {
    // If found, the base path is up to and including the project folder name
    $base_path = substr($script_name, 0, $pos + strlen('/' . $project_folder_name_in_url . '/'));
} else {
    // If the project folder name is NOT found in the URL (e.g., it's deployed directly to example.com/)
    // then the base path is just the root '/'.
    $base_path = '/';
}

// Ensure BASE_URL always has a trailing slash
define('BASE_URL', $protocol . '://' . $host . $project_root_url_segment);
// --- End Dynamically Determine BASE_URL ---
define('DB_SERVER', getenv('DB_SERVER'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_NAME', getenv('DB_NAME'));

// --- Custom Error and Exception Handler ---
// Function to write custom log messages
function writeToAppLog($message, $level = 'ERROR') {
    $logFile = __DIR__ . '/logs/app_crashes.log'; // Dedicated custom error log
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] - $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Custom error handler (for non-fatal errors: E_WARNING, E_NOTICE, etc.)
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // These error types are often caught by set_error_handler
    $errorType = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];
    $level = $errorType[$errno] ?? 'UNKNOWN_ERROR';
    $message = "[$level] $errstr in $errfile on line $errline";
    writeToAppLog($message, 'PHP_ERROR');

    // Prevent PHP's default error handler from running for certain types
    return false; // False allows PHP's default error handler to continue (for display if enabled, or for log_errors)
}

// Custom exception handler (for uncaught exceptions)
function customExceptionHandler($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    writeToAppLog($message, 'UNCAUGHT_EXCEPTION');
    // For production, you might redirect to a friendly error page here
    // header('Location: /error.php'); exit();
}

// Shutdown function (crucial for catching fatal errors)
function customShutdownFunction() {
    $error = error_get_last();
    // Check if the last error was a fatal error (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR)
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
        $message = "Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        writeToAppLog($message, 'FATAL_ERROR');
        // Optionally, ensure a generic 500 response is sent if not already
        if (!headers_sent() && http_response_code() === 200) {
            http_response_code(500);
        }
    }
}

set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");
register_shutdown_function("customShutdownFunction");
// --- End Custom Error and Exception Handler ---

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
