<?php
// Function to start a session safely
function start_session_once() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// --- User & Role Management Functions ---

// Function to check if a user is logged in
function is_logged_in() {
    start_session_once();
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// Function to get the logged-in user's role string (the '000' or '111' string)
function get_user_role() {
    start_session_once();
    return $_SESSION['role'] ?? '000'; // Default to '000' if not set
}

// Access Right Checks - now directly checking session flags
function has_read_access() {
    start_session_once(); // Ensure session is started before accessing
    return $_SESSION['has_read_access'] ?? false;
}

function has_edit_access() {
    start_session_once();
    return $_SESSION['has_edit_access'] ?? false;
}

function has_special_edit_access() {
    start_session_once();
    return $_SESSION['has_special_edit_access'] ?? false;
}

// Function to check if the logged-in user is a super admin
// (Still relies on 'special edit' access)
function is_super_admin() {
    return is_logged_in() && has_special_edit_access();
}
// Function for logging user activities
function log_activity($action_type, $description = null, $details = null,$notes = null) {
    global $conn; // Access the global database connection

    if (!$conn) {
        // If DB connection isn't available, log to app_crashes.log instead
        // This prevents infinite loops if DB itself is the problem
        error_log("Failed to log activity: DB connection not available. Type: $action_type, Desc: $description", 3, __DIR__ . '/../logs/app_crashes.log');
        return;
    }

    $user_id = $_SESSION['id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $details_json = ($details !== null) ? json_encode($details) : null;

    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, description, details,notes, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    return;
}
    // Use 's' for string types for fields that can be NULL
    $stmt->bind_param("issssss", $user_id, $action_type, $description, $details_json,$notes, $ip_address, $user_agent);

    $stmt->execute();
    $stmt->close();
}