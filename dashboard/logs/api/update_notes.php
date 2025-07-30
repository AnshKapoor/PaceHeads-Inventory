<?php
// PHP error reporting for debugging (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includes your config and functions files (relative path from api/ folder)
require_once '../../../config.php';
require_once '../../../functions.php';
header('Content-Type: application/json; charset=utf-8');
start_session_once();

// --- Access Control for Log API ---
// Only allow users with special_edit_access (super admins) to access this API.
if (!is_logged_in() || !has_special_edit_access()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Permission denied to access log data.']);
    exit();
}

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

if (!isset($data['updates']) || !is_array($data['updates'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$successCount = 0;
$stmt = $conn->prepare("UPDATE activity_log SET notes = ? WHERE id = ?");

foreach ($data['updates'] as $update) {
    $logId = intval($update['id']);
    $notes = $update['notes'] ?? '';
    
    $stmt->bind_param("si", $notes, $logId);
    if ($stmt->execute()) {
        $successCount++;
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'updated' => $successCount
]);