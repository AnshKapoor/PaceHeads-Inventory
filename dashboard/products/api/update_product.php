<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../../config.php';
require_once '../../../functions.php';

start_session_once();
if (!is_logged_in()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

$editor_user_id = $_SESSION['id']; // ID of the user performing the update
$can_edit = has_edit_access() || has_special_edit_access();

// --- Define ALL PRODUCT COLUMNS (based on your precise list + tracking columns) ---
// This list MUST match your database table column names exactly (case-sensitive if your DB is)
$all_product_columns = [
    'id', 'sku', 'article', 'category', 'ean', 'condition',
    'subscription_1_monthly', 'subscription_3_monthly', 'subscription_6_monthly', 'subscription_12_monthly',
    'subscription_1_upfront', 'subscription_3_upfront', 'subscription_6_upfront', 'subscription_12_upfront',
    'buyout_1_monthly', 'buyout_3_monthly', 'buyout_6_monthly', 'buyout_12_monthly',
    'buyout_1_upfront', 'buyout_3_upfront', 'buyout_6_upfront', 'buyout_12_upfront',
    'msrp_gross', 'msrp_net', 'outlet_warranty_gross', 'outlet_warranty_net',
    'outlet_no_warranty_gross', 'outlet_no_warranty_net', 'pp_gross', 'pp_net',
    'profit_eur', 'profit_percent',
    'created_by', // New column
    'created_at',
    'updated_at',
    'updated_by' // New column, replaces 'last_edited_by_user_id'
];

// Define columns that are DECIMAL types (use 'd' for bind_param)
$decimal_columns = [
    'subscription_1_monthly', 'subscription_3_monthly', 'subscription_6_monthly', 'subscription_12_monthly',
    'subscription_1_upfront', 'subscription_3_upfront', 'subscription_6_upfront', 'subscription_12_upfront',
    'buyout_1_monthly', 'buyout_3_monthly', 'buyout_6_monthly', 'buyout_12_monthly',
    'buyout_1_upfront', 'buyout_3_upfront', 'buyout_6_upfront', 'buyout_12_upfront',
    'msrp_gross', 'msrp_net', 'outlet_warranty_gross', 'outlet_warranty_net',
    'outlet_no_warranty_gross', 'outlet_no_warranty_net',
    'pp_gross', 'pp_net', 'profit_eur', 'profit_percent'
];
// Define columns that are INTEGER types (use 'i' for bind_param)
$integer_columns = [
    'id', 'created_by', 'updated_by' // Add other int columns if you have them, excluding those you want to treat as string for input
];


// --- Handle Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['draw']) && isset($_POST['start']) && isset($_POST['length'])) {
        // --- DataTables server-side processing POST request for fetching data ---
        $draw = $_POST['draw'];
        $start = $_POST['start'];
        $length = $_POST['length'];
        $search_value = $_POST['search']['value'] ?? '';
        $order_col_idx = $_POST['order'][0]['column'] ?? 0;
        $order_dir = $_POST['order'][0]['dir'] ?? 'asc';

        // Map DataTables column index to actual database column name for sorting/searching
        $dt_columns_map = [
            'id', 'sku', 'article', 'category', 'ean', 'condition',
            'subscription_1_monthly', 'subscription_3_monthly', 'subscription_6_monthly', 'subscription_12_monthly',
            'subscription_1_upfront', 'subscription_3_upfront', 'subscription_6_upfront', 'subscription_12_upfront',
            'buyout_1_monthly', 'buyout_3_monthly', 'buyout_6_monthly', 'buyout_12_monthly',
            'buyout_1_upfront', 'buyout_3_upfront', 'buyout_6_upfront', 'buyout_12_upfront',
            'msrp_gross', 'msrp_net', 'outlet_warranty_gross', 'outlet_warranty_net',
            'outlet_no_warranty_gross', 'outlet_no_warranty_net', 'pp_gross', 'pp_net',
            'profit_eur', 'profit_percent',
            'created_by_username', 'created_at', 'updated_at', 'updated_by_username'
        ];
        $order_by = $dt_columns_map[$order_col_idx] ?? 'id';

        $total_records = 0;
        $filtered_records = 0;
        $data = [];

        // Base query parts
        $select_cols_for_display = [];
        foreach ($all_product_columns as $col) {
            if ($col === 'created_by') {
                 $select_cols_for_display[] = 'u_created.username AS created_by_username';
            } elseif ($col === 'updated_by') {
                 $select_cols_for_display[] = 'u_updated.username AS updated_by_username';
            } else {
                 $select_cols_for_display[] = 'p.' . $col;
            }
        }
        $select_clause = implode(', ', $select_cols_for_display) . ", '' AS _dt_actions_col";

        $from_clause = "FROM products p
                        LEFT JOIN users u_created ON p.created_by = u_created.id
                        LEFT JOIN users u_updated ON p.updated_by = u_updated.id";

        // 1. Count total records
        $count_sql = "SELECT COUNT(p.id) " . $from_clause;
        $count_result = $conn->query($count_sql);
        if ($count_result) {
            $total_records = $count_result->fetch_row()[0];
        }

        // 2. Build WHERE clause for global search
        $where_clauses = [];
        $params = [];
        $param_types = '';

        if (!empty($search_value)) {
            $search_terms = array_filter(explode(' ', $search_value));
            foreach ($search_terms as $term) {
                $term_sql_parts = [];
                $term_params_parts = [];
                $term_types_parts = '';

                $searchable_db_columns = [
                    'p.sku', 'p.article', 'p.category', 'p.ean', 'p.condition',
                    'u_created.username',
                    'u_updated.username'
                ];

                foreach ($searchable_db_columns as $col_to_search_in_where) {
                    $term_sql_parts[] = "$col_to_search_in_where LIKE ?";
                    $term_params_parts[] = '%' . $term . '%';
                    $term_types_parts .= 's';
                }
                if (is_numeric($term)) {
                    foreach ($decimal_columns as $num_col) {
                        $term_sql_parts[] = "p.$num_col = ?";
                        $term_params_parts[] = (float)$term;
                        $term_types_parts .= 'd';
                    }
                    foreach ($integer_columns as $int_col) {
                        $term_sql_parts[] = "p.$int_col = ?";
                        $term_params_parts[] = (int)$term;
                        $term_types_parts .= 'i';
                    }
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

        $filtered_count_sql = "SELECT COUNT(p.id) " . $from_clause . " " . $where_sql;
        if ($stmt = $conn->prepare($filtered_count_sql)) {
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            $stmt->execute();
            $filtered_records = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
        }

        $sql = "SELECT $select_clause $from_clause $where_sql ORDER BY $order_by $order_dir LIMIT ?, ?";

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
                $data[] = $row;
            }
            $stmt->close();
        }

        echo json_encode([
            "draw" => intval($draw),
            "recordsTotal" => intval($total_records),
            "recordsFiltered" => intval($filtered_records),
            "data" => $data
        ]);
        $conn->close();
        exit();

    } else {
        // --- Custom POST request for updating a product (from modal form) ---
        if (!$can_edit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied to edit products.']);
            exit();
        }

        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        $product_id = $input['id'] ?? null;
        $submitted_fields = $input['data'] ?? []; // Renamed from $updated_fields for clarity
        $notes = $input['notes'] ?? null; // <-- Capture notes from modal
        if (!$product_id || !is_array($submitted_fields) || empty($submitted_fields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input data for update.']);
            exit();
        }

        // --- NEW: Fetch Original Data from Database ---
        $original_data = null;
        $select_cols_for_original_fetch = [];
        // Only select columns that could potentially be updated from the form
        foreach ($all_product_columns as $col) {
            // Exclude auto-generated/managed fields that aren't editable from the form
            // Also exclude ID and tracking fields (created_by, updated_by) if they are not to be fetched for comparison
            // However, we need to fetch them if they are part of the original_data that might be compared (e.g. for created_by)
            if (!in_array($col, ['created_at', 'updated_at'])) { // Exclude auto-timestamps for direct comparison
                 $select_cols_for_original_fetch[] = "`" . $col . "`"; // Escape column names
            }
        }
        $select_original_sql = "SELECT " . implode(', ', $select_cols_for_original_fetch) . " FROM products WHERE id = ?";

        if ($stmt_original = $conn->prepare($select_original_sql)) {
            $stmt_original->bind_param("i", $product_id);
            $stmt_original->execute();
            $result_original = $stmt_original->get_result();
            $original_data = $result_original->fetch_assoc();
            $stmt_original->close();
        }

        if (!$original_data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit();
        }
        // --- END NEW: Fetch Original Data ---

        $set_clauses = [];
        $params = [];
        $param_types = '';
        $changed_fields_for_log = []; // NEW: Array to store only changed fields for the log

        foreach ($submitted_fields as $field_name => $value) {
            // Skip invalid or system-managed fields that should NOT be updated from form data
            if (!in_array($field_name, $all_product_columns) || $field_name === 'id' || $field_name === 'created_at' || $field_name === 'updated_at' || $field_name === 'created_by') {
                continue; // Cannot edit ID, timestamps, or original creator directly via form
            }

            // Convert empty strings to NULL for database
            if ($value === '') {
                $value = null;
            }

            // --- NEW: Compare with Original Data to find actual CHANGES ---
            $original_value = $original_data[$field_name] ?? null;

            // Type casting for robust comparison
            $value_to_compare = $value;
            $original_value_to_compare = $original_value;

            if (in_array($field_name, $decimal_columns)) {
                $value_to_compare = ($value !== null) ? (float)$value : null;
                $original_value_to_compare = ($original_value !== null) ? (float)$original_value : null;
            } elseif (in_array($field_name, $integer_columns)) {
                $value_to_compare = ($value !== null) ? (int)$value : null;
                $original_value_to_compare = ($original_value !== null) ? (int)$original_value : null;
            } else {
                // For strings, consider trimming for comparison if whitespace isn't significant
                $value_to_compare = ($value !== null) ? (string)$value : null;
                $original_value_to_compare = ($original_value !== null) ? (string)$original_value : null;
            }
            
            // If value has genuinely changed (strict comparison, including null vs non-null)
            if ($value_to_compare !== $original_value_to_compare) {
                $changed_fields_for_log[$field_name] = [
                    'old' => $original_value_to_compare,
                    'new' => $value_to_compare
                ];
                // Build set clauses ONLY if value has changed
                $escaped_field_name = "`" . $field_name . "`";
                if (in_array($field_name, $decimal_columns)) {
                    $param_types .= 'd';
                } elseif (in_array($field_name, $integer_columns)) {
                    $param_types .= 'i';
                } else {
                    $param_types .= 's';
                }
                $set_clauses[] = "$escaped_field_name = ?";
                $params[] = $value;
            }
            // --- END NEW: Compare ---
        }

        // --- NEW: Handle "No Changes Detected" ---
        // If $set_clauses is empty, it means no actual changes were made to editable fields.
        if (empty($set_clauses)) {
            // Log that no changes were made and return success
            log_activity('PRODUCT_UPDATE_NO_CHANGE', 'Product ' . $product_id . ' submitted with no detected changes by ' . ($_SESSION['username'] ?? 'Unknown'), [
                'product_id' => $product_id,
                'editor_user_id' => $editor_user_id
            ]);
            http_response_code(200); // Return 200 OK because no error, just no changes
            echo json_encode(['success' => true, 'message' => 'No changes detected for product ' . $product_id . '.']);
            $conn->close();
            exit();
        }
        // --- END NEW: Handle "No Changes Detected" ---

        // Add updated_by (current user) - this is automatic for any edit
        $set_clauses[] = '`updated_by` = ?'; // Escaped
        $params[] = $editor_user_id;
        $param_types .= 'i';

        $params[] = $product_id;
        $param_types .= 'i';

        $sql = "UPDATE products SET " . implode(', ', $set_clauses) . " WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($param_types, ...$params);

            if ($stmt->execute()) {
                // Log product update activity with ONLY THE CHANGED FIELDS
                log_activity('PRODUCT_UPDATE', 'Product ' . $product_id . ' updated by ' . ($_SESSION['username'] ?? 'Unknown'), [
                    'product_id' => $product_id,
                    'changes' => $changed_fields_for_log, // Use the new array for the log
                    'editor_user_id' => $editor_user_id
                ],$notes);
                echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
            } else {
                // Log failed product update
                log_activity('PRODUCT_UPDATE_FAILED', 'Failed to update product ' . $product_id . ' by ' . ($_SESSION['username'] ?? 'Unknown'), [
                    'product_id' => $product_id,
                    'attempted_changes' => $changed_fields_for_log, // Log what was *attempted* to change
                    'error_message' => $stmt->error,
                    'editor_user_id' => $editor_user_id
                ]);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Log failed to prepare statement
            log_activity('PRODUCT_UPDATE_PREPARE_FAILED', 'Failed to prepare update statement for product ' . $product_id . ' by ' . ($_SESSION['username'] ?? 'Unknown'), [
                'product_id' => $product_id,
                'submitted_data' => $submitted_fields, // Log all submitted if SQL prep fails
                'error_message' => $conn->error,
                'editor_user_id' => $editor_user_id
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        }
        $conn->close();
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}
?>