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
