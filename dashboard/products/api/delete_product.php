<?php
require_once '../../../config.php';
require_once '../../../functions.php';

header('Content-Type: application/json');

start_session_once();
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

if (!has_edit_access() && !has_special_edit_access()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied to delete products.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$product_id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

if (!$product_id || $product_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid product ID is required.']);
    exit();
}

$product = null;
$select_stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
if (!$select_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare the product lookup.']);
    exit();
}

$select_stmt->bind_param('i', $product_id);
if (!$select_stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to look up the product.']);
    $select_stmt->close();
    $conn->close();
    exit();
}

$select_result = $select_stmt->get_result();
$product = $select_result ? $select_result->fetch_assoc() : null;
$select_stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit();
}

$delete_stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
if (!$delete_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare the product deletion.']);
    exit();
}

$delete_stmt->bind_param('i', $product_id);

if ($delete_stmt->execute() && $delete_stmt->affected_rows === 1) {
    log_activity(
        'PRODUCT_DELETE',
        'Product ' . $product_id . ' deleted by ' . ($_SESSION['username'] ?? 'Unknown'),
        [
            'product_id' => $product_id,
            'deleted_product' => $product,
            'deleted_by_user_id' => $_SESSION['id'] ?? null
        ]
    );

    echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
} else {
    $error_message = $delete_stmt->error ?: 'The product could not be deleted.';

    log_activity(
        'PRODUCT_DELETE_FAILED',
        'Failed to delete product ' . $product_id . ' by ' . ($_SESSION['username'] ?? 'Unknown'),
        [
            'product_id' => $product_id,
            'error_message' => $error_message,
            'deleted_by_user_id' => $_SESSION['id'] ?? null
        ]
    );

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete product: ' . $error_message]);
}

$delete_stmt->close();
$conn->close();
