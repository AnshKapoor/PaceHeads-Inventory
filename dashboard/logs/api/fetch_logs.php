<?php
// PHP error reporting for debugging (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Includes your config and functions files (relative path from api/ folder)
require_once '../../../config.php';
require_once '../../../functions.php';

start_session_once();

// --- Access Control for Log API ---
// Only allow users with special_edit_access (super admins) to access this API.
if (!is_logged_in() || !has_special_edit_access()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Permission denied to access log data.']);
    exit();
}

// --- DataTables Server-Side Processing Logic ---
// This API expects a POST request from DataTables
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search_value = $_POST['search']['value'] ?? '';
    $order_col_idx = $_POST['order'][0]['column'] ?? 0;
    $order_dir = $_POST['order'][0]['dir'] ?? 'desc'; // Default to newest first

    // Map DataTables column index to actual database column name for sorting/searching
    // This order MUST match the 'columns' array in activity_log.js
    $dt_columns_map = [
        'id', 'username', 'action_type', 'description', 'details','notes', 'ip_address', 'user_agent', 'timestamp'
    ];
    $order_by = $dt_columns_map[$order_col_idx] ?? 'timestamp'; // Default order by timestamp

    $total_records = 0;
    $filtered_records = 0;
    $data = [];

    // Base query parts
    $select_clause = "SELECT al.id, u.username, al.action_type, al.description, al.details, al.notes, al.ip_address, al.user_agent, al.timestamp";
    $from_clause = "FROM activity_log al LEFT JOIN users u ON al.user_id = u.id";

    // 1. Count total records
    $count_sql = "SELECT COUNT(al.id) " . $from_clause;
    $count_result = $conn->query($count_sql);
    if ($count_result) {
        $total_records = $count_result->fetch_row()[0];
    }

    // 2. Build WHERE clause for global search
    $where_clauses = [];
    $params = [];
    $param_types = '';
    // This where clause added on 22 July to avoid getting unnecessary logs for loggin in and logging
    $where_clauses[] = "al.action_type NOT IN ('LOGIN', 'LOGOUT')";
    if (!empty($search_value)) {
        $search_terms = array_filter(explode(' ', $search_value));
        foreach ($search_terms as $term) {
            $term_sql_parts = [];
            $term_params_parts = [];
            $term_types_parts = '';
            // Columns to search across for text-based global search
            $searchable_columns = ['u.username', 'al.action_type', 'al.description', 'al.ip_address', 'al.user_agent', 'al.details','al.notes' ];
            foreach ($searchable_columns as $col) {
                $term_sql_parts[] = "$col LIKE ?";
                $term_params_parts[] = '%' . $term . '%';
                $term_types_parts .= 's';
            }

            if (!empty($term_sql_parts)) {
                $where_clauses[] = '(' . implode(' OR ', $term_sql_parts) . ')';
                $params = array_merge($params, $term_params_parts);
                $param_types .= $term_types_parts;
            }
        }
    }

    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }

    // 3. Count filtered records
    $filtered_count_sql = "SELECT COUNT(al.id) " . $from_clause . " " . $where_sql;
    if ($stmt = $conn->prepare($filtered_count_sql)) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $filtered_records = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
    }

    // 4. Fetch actual data with pagination and ordering
    $sql = "$select_clause $from_clause $where_sql ORDER BY $order_by $order_dir LIMIT ?, ?";

    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) {
            $combined_params = array_merge($params, [$start, $length]);
            $stmt->bind_param($param_types . 'ii', ...$combined_params);
        } else {
            $stmt->bind_param('ii', $start, $length);
        }
        $stmt->execute();
        $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
    // Decode JSON details to check for product_id
    $details = json_decode($row['details'], true);

    if (in_array($row['action_type'], ['PRODUCT_UPDATE', 'PRODUCT_UPDATE_FAILED']) && isset($details['product_id'])) {
        $product_id = $details['product_id'];

        // Prepare query to fetch product name (article)
        $stmt_product = $conn->prepare("SELECT article FROM products WHERE id = ?");
        if ($stmt_product) {
            $stmt_product->bind_param("i", $product_id);
            $stmt_product->execute();
            $result_product = $stmt_product->get_result();
            if ($product_row = $result_product->fetch_assoc()) {
                // Inject the product name into the details JSON
                $details['product_name'] = $product_row['article'];
                $row['details'] = json_encode($details);
            }
            $stmt_product->close();
        }
    }

    $data[] = $row;
}

        $stmt->close();
    }

    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => intval($total_records),
        "recordsFiltered" => intval( $filtered_records ), // Cast to int
        "data" => $data
    ]);
    $conn->close();
    exit();
} else {
    // Handle other request methods (e.g., if someone tries to access via GET)
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}
