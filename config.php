<?php
// Include your manual .env loader
require_once __DIR__ . '/load_env.php';

// Database Connection Configuration - now read from environment variables
// These will come from your .env file locally, or from Hostinger's environment variables on cloud.

// Using getenv() is generally preferred over $_ENV[] when dealing with putenv()
// as $_ENV is only populated based on server environment variables, not necessarily putenv()
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