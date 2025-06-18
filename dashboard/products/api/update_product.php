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
        // This order MUST match the 'columns' array in products_table.js, excluding the Actions column
        $dt_columns_map = [
            'id', 'sku', 'article', 'category', 'ean', 'condition',
            'subscription_1_monthly', 'subscription_3_monthly', 'subscription_6_monthly', 'subscription_12_monthly',
            'subscription_1_upfront', 'subscription_3_upfront', 'subscription_6_upfront', 'subscription_12_upfront',
            'buyout_1_monthly', 'buyout_3_monthly', 'buyout_6_monthly', 'buyout_12_monthly',
            'buyout_1_upfront', 'buyout_3_upfront', 'buyout_6_upfront', 'buyout_12_upfront',
            'msrp_gross', 'msrp_net', 'outlet_warranty_gross', 'outlet_warranty_net',
            'outlet_no_warranty_gross', 'outlet_no_warranty_net', 'pp_gross', 'pp_net',
            'profit_eur', 'profit_percent',
            'created_by_username', 'created_at', 'updated_at', 'updated_by_username' // Join names for display
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
        $select_clause = implode(', ', $select_cols_for_display);

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
                // Columns to search across for text-based global search
                $searchable_text_columns = ['sku', 'article', 'category', 'ean', 'condition', 'created_by_username', 'updated_by_username'];
                foreach ($searchable_text_columns as $col_to_search) {
                    $prefix = 'p.';
                    if (in_array($col_to_search, ['created_by_username'])) $prefix = 'u_created.';
                    if (in_array($col_to_search, ['updated_by_username'])) $prefix = 'u_updated.';

                    $term_sql_parts[] = "$prefix$col_to_search LIKE ?";
                    $term_params_parts[] = '%' . $term . '%';
                    $term_types_parts .= 's';
                }
                // Add numeric columns to search if search value is numeric
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

        // 3. Count filtered records
        $filtered_count_sql = "SELECT COUNT(p.id) " . $from_clause . " " . $where_sql;
        if ($stmt = $conn->prepare($filtered_count_sql)) {
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            $stmt->execute();
            $filtered_records = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
        }

        // 4. Fetch actual data with pagination and ordering
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
        $updated_fields = $input['data'] ?? [];

        if (!$product_id || !is_array($updated_fields) || empty($updated_fields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input data for update.']);
            exit();
        }

        $set_clauses = [];
        $params = [];
        $param_types = '';

        foreach ($updated_fields as $field_name => $value) {
            // Skip invalid or system-managed fields
            if (!in_array($field_name, $all_product_columns) || $field_name === 'id' || $field_name === 'created_at' || $field_name === 'updated_at' || $field_name === 'created_by' || $field_name === 'updated_by') {
                continue; // Cannot edit ID or auto-managed timestamps/user IDs directly via form
            }

            // --- VALIDATION BASED ON YOUR COLUMN TYPES ---
            if (in_array($field_name, $decimal_columns)) {
                if (!is_numeric($value)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Invalid numerical value for $field_name."]);
                    $conn->close(); exit();
                }
                $param_types .= 'd';
            } elseif (in_array($field_name, $integer_columns)) {
                if (!ctype_digit(strval($value)) && $value !== null && $value !== '') { // Allow empty string/null for nullable INTs
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Invalid integer value for $field_name."]);
                    $conn->close(); exit();
                }
                $value = ($value === null || $value === '') ? null : (int)$value; // Convert to int or null
                $param_types .= 'i';
            } else { // Default to string for varchar, text etc.
                $param_types .= 's';
            }

            $set_clauses[] = "$field_name = ?";
            $params[] = $value;
        }

        if (empty($set_clauses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid fields provided for update.']);
            $conn->close();
            exit();
        }

        // Add updated_by (current user) - this is automatic for any edit
        $set_clauses[] = 'updated_by = ?';
        $params[] = $editor_user_id;
        $param_types .= 'i';

        $params[] = $product_id;
        $param_types .= 'i';

        $sql = "UPDATE products SET " . implode(', ', $set_clauses) . " WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($param_types, ...$params);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
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