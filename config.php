<?php
// Include your manual .env loader
require_once __DIR__ . '/load_env.php';

// Database Connection Configuration - now read from environment variables
// These will come from your .env file locally, or from Hostinger's environment variables on cloud.

// Using getenv() is generally preferred over $_ENV[] when dealing with putenv()
// as $_ENV is only populated based on server environment variables, not necessarily putenv()
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_path = ''; // Default for root deployment

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
define('BASE_URL', $protocol . '://' . $host . rtrim($base_path, '/') . '/');
// --- End Dynamically Determine BASE_URL ---
define('DB_SERVER', getenv('DB_SERVER'));
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_NAME', getenv('DB_NAME'));
// echo "Database connection settings loaded from environment variables.\n". 
//      "DB_SERVER: " . DB_SERVER . "\n" .
//      "DB_USERNAME: " . DB_USERNAME . "\n" .
//      "DB_NAME: " . DB_NAME . "\n";
// Establish database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>