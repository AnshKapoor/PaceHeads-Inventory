<?php
// Include config.php first to establish DB connection and BASE_URL
require_once '../../config.php';
require_once '../../functions.php';

/**
 * Map the submitted condition label to the database friendly value.
 *
 * @param string|null $conditionInput Condition value as sent from the add product form.
 * @return string|null Database compatible condition string or null when no selection was provided.
 */
function mapConditionInputToDatabase(?string $conditionInput): ?string
{
    // Normalize the user input to ensure consistent comparisons.
    $normalizedCondition = $conditionInput !== null ? strtolower(trim($conditionInput)) : null;

    switch ($normalizedCondition) {
        case 'new':
            return 'NEW';
        case 'used':
            return 'REFURBISHED';
        default:
            return null; // Fallback for unexpected values keeps the database safe.
    }
}

/**
 * Retrieve the category names from the database so the dropdown always reflects live data.
 *
 * @param mysqli $connection Active MySQLi connection used to run the select query.
 * @param string|null $errorMessage Reference that stores the error details if the query fails.
 * @return array<int, string> Alphabetically sorted list of category names.
 */
function fetchCategoryNames(mysqli $connection, ?string &$errorMessage): array
{
    $errorMessage = null; // Ensure the variable is reset for every invocation.
    $categoryNames = [];

    $query = 'SELECT name FROM categories ORDER BY name ASC';
    $result = $connection->query($query);

    if ($result === false) {
        // Capture the failure details so they can be surfaced to the UI in a friendly way.
        $errorMessage = 'Unable to load categories at the moment. Please try again later.';
        return $categoryNames;
    }

    while ($row = $result->fetch_assoc()) {
        if (isset($row['name'])) {
            $categoryNames[] = (string) $row['name']; // Cast to string to ensure consistent output.
        }
    }

    $result->free();

    return $categoryNames;
}

// Include the dashboard header, which handles login check and layout
include '../partials/header.php';

// Access control: Only users with edit or special edit access can add products
if (!has_edit_access() && !has_special_edit_access()) {
    redirect(BASE_URL . 'dashboard/index.php'); // Redirect if no permission
}

$page_title = "Add New Product"; // Specific page title
$add_product_error = "";
$add_product_success = "";
/** @var string|null $category_fetch_error Human readable message to show when fetching categories fails. */
$category_fetch_error = null;

// --- Define ALL PRODUCT COLUMNS (same as in update_product.php) ---
$all_product_columns = [
    'id', 'sku', 'article', 'category', 'ean', 'condition',
    'subscription_1_monthly', 'subscription_3_monthly', 'subscription_6_monthly', 'subscription_12_monthly',
    'subscription_1_upfront', 'subscription_3_upfront', 'subscription_6_upfront', 'subscription_12_upfront',
    'buyout_1_monthly', 'buyout_3_monthly', 'buyout_6_monthly', 'buyout_12_monthly',
    'buyout_1_upfront', 'buyout_3_upfront', 'buyout_6_upfront', 'buyout_12_upfront',
    'msrp_gross', 'msrp_net', 'outlet_warranty_gross', 'outlet_warranty_net',
    'outlet_no_warranty_gross', 'outlet_no_warranty_net', 'pp_gross', 'pp_net',
    'profit_eur', 'profit_percent',
    'created_by',
    'created_at',
    'updated_at',
    'updated_by'
];

// Define columns that are DECIMAL types
$decimal_columns = [
    'subscription_1_monthly', 'subscription_3_monthly', 'subscription_6_monthly', 'subscription_12_monthly',
    'subscription_1_upfront', 'subscription_3_upfront', 'subscription_6_upfront', 'subscription_12_upfront',
    'buyout_1_monthly', 'buyout_3_monthly', 'buyout_6_monthly', 'buyout_12_monthly',
    'buyout_1_upfront', 'buyout_3_upfront', 'buyout_6_upfront', 'buyout_12_upfront',
    'msrp_gross', 'msrp_net', 'outlet_warranty_gross', 'outlet_warranty_net',
    'outlet_no_warranty_gross', 'outlet_no_warranty_net',
    'pp_gross', 'pp_net', 'profit_eur', 'profit_percent'
];
// Define columns that are INTEGER types
$integer_columns = [
    'id', 'created_by', 'updated_by'
];
// --- END COLUMN DEFINITIONS ---

// Handle Form Submission for Adding Product
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_fields = $_POST; // Get all submitted form data

    // Convert the human friendly condition selection into the database format.
    if (array_key_exists('condition_display', $submitted_fields)) {
        $mappedCondition = mapConditionInputToDatabase($submitted_fields['condition_display']);

        if ($mappedCondition === null && ($submitted_fields['condition_display'] ?? '') !== '') {
            $add_product_error = "Please choose a valid condition option.";
        }

        $submitted_fields['condition'] = $mappedCondition;
    }

    $set_clauses = []; // This will hold column names for INSERT
    $params = [];      // This will hold values for INSERT
    $param_types = '';

    // Loop through all potential columns, validate, and build the INSERT query
    foreach ($all_product_columns as $field_name) {
        // --- FIX STARTS HERE ---
        // Skip ID (auto-increment), created_at/updated_at (DB managed),
        // and created_by/updated_by (handled explicitly below for current user)
        if (in_array($field_name, ['id', 'created_at', 'updated_at', 'created_by', 'updated_by'])) {
            continue; // These are system-managed, not from form
        }
        // --- FIX ENDS HERE ---

        $value = $submitted_fields[$field_name] ?? null; // Get value from POST, default to null

        // Convert empty strings to NULL for database
        if ($value === '') {
            $value = null;
        }

        $escaped_field_name = "`" . $field_name . "`"; // Escape column name

        // --- VALIDATION BASED ON COLUMN TYPES ---
        if (in_array($field_name, $decimal_columns)) {
            if ($value !== null && !is_numeric($value)) {
                $add_product_error = "Invalid numerical value for $field_name."; break;
            }
            $param_types .= 'd';
        } elseif (in_array($field_name, $integer_columns)) {
            if ($value !== null && !ctype_digit(strval($value))) {
                $add_product_error = "Invalid integer value for $field_name."; break;
            }
            $param_types .= 'i';
        } else {
            $param_types .= 's';
        }

        $set_clauses[] = $escaped_field_name; // Add column name to list
        $params[] = $value; // Add value to parameters
    }

    // If there was a validation error, stop here
    if (!empty($add_product_error)) {
        // Keep submitted values in form for user to correct
        foreach($submitted_fields as $key => $val) {
            $_POST[$key] = $val;
        }
    } else {
        // --- FIX STARTS HERE ---
        // Explicitly add created_by (current user) after processing form fields
        $set_clauses[] = '`created_by`';
        $params[] = $_SESSION['id'] ?? null; // Use current user's ID for creator
        $param_types .= 'i';

        // Explicitly add updated_by (current user) - also set on creation
        $set_clauses[] = '`updated_by`';
        $params[] = $_SESSION['id'] ?? null; // Use current user's ID for initial updater
        $param_types .= 'i';
        // --- FIX ENDS HERE ---

        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        $sql = "INSERT INTO products (" . implode(', ', $set_clauses) . ") VALUES (" . $placeholders . ")";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($param_types, ...$params);

            if ($stmt->execute()) {
                // ... (rest of success logic, logging, etc. - unchanged) ...
                $new_product_id = $stmt->insert_id; // Get the ID of the newly inserted product
                $add_product_success = "Product added successfully! ID: " . $new_product_id;
                log_activity('PRODUCT_ADD', 'New product ' . $new_product_id . ' added by ' . ($_SESSION['username'] ?? 'Unknown'), [
                    'product_id' => $new_product_id,
                    'added_data' => $submitted_fields, // Log all submitted data for the new product
                    'creator_user_id' => $_SESSION['id'] ?? null
                ]);
                $_POST = array(); // Clear form fields after successful addition
            } else {
                // ... (rest of error logic, logging - unchanged) ...
                $add_product_error = "Database error: " . $stmt->error;
                log_activity('PRODUCT_ADD_FAILED', 'Failed to add user ' . $input_username . ' by ' . ($_SESSION['username'] ?? 'System'), [
                    'attempted_data' => $submitted_fields,
                    'error' => $stmt->error,
                    'creator_user_id' => $_SESSION['id'] ?? null
                ]);
            }
            $stmt->close();
        } else {
            // ... (rest of prepare statement failure logic, logging - unchanged) ...
            $add_product_error = "Failed to prepare statement: " . $conn->error;
            log_activity('PRODUCT_ADD_PREPARE_FAILED', 'Failed to prepare add product statement by ' . ($_SESSION['username'] ?? 'Unknown'), [
                'submitted_data' => $submitted_fields,
                'error_message' => $conn->error,
                'creator_user_id' => $_SESSION['id'] ?? null
            ]);
        }
    }
}

/** @var array<int, string> $category_options */
$category_options = fetchCategoryNames($conn, $category_fetch_error); // Load categories for the dropdown.
if (empty($category_options) && $category_fetch_error === null) {
    $category_fetch_error = 'No categories are available yet. Please create a category before adding products.';
}

$conn->close(); // Close database connection
?>
<link rel="stylesheet" href="./css/add_product_styles.css"/>
<div class="dashboard-section add-product-section">
    <h2>Add New Product</h2>
    <p>Fill out the form below to add a new product to the inventory.</p>

    <?php if (!empty($add_product_error)): ?>
        <p class="error-message"><?php echo htmlspecialchars($add_product_error); ?></p>
    <?php endif; ?>
    <?php if (!empty($add_product_success)): ?>
        <p class="success-message"><?php echo htmlspecialchars($add_product_success); ?></p>
    <?php endif; ?>

    <form action="add.php" method="POST" class="add-product-form">
        <!-- Input fields for all 32 columns (excluding ID, created_at, updated_at, created_by, updated_by) -->
        <!-- Use name attributes that match your database column names -->
        <!-- Use value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" to retain values on error -->

        <div class="form-group"><label for="sku">SKU:</label><input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>"></div>
        <div class="form-group"><label for="article">Article:</label><input type="text" id="article" name="article" value="<?php echo htmlspecialchars($_POST['article'] ?? ''); ?>"></div>
        <div class="form-group">
            <label for="category">Category:</label>
            <select id="category" name="category">
                <option value="">Select a category</option>
                <?php foreach ($category_options as $category_name): ?>
                    <option value="<?php echo htmlspecialchars($category_name); ?>" <?php echo (($_POST['category'] ?? '') === $category_name) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category_name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($category_fetch_error)): ?>
                <small class="error-message"><?php echo htmlspecialchars($category_fetch_error); ?></small>
            <?php endif; ?>
        </div>
        <div class="form-group"><label for="ean">EAN:</label><input type="text" id="ean" name="ean" value="<?php echo htmlspecialchars($_POST['ean'] ?? ''); ?>"></div>
        <div class="form-group">
            <label for="condition_display">Condition:</label>
            <select id="condition_display" name="condition_display">
                <option value="">Select condition</option>
                <option value="new" <?php echo ((($_POST['condition_display'] ?? '') === 'new') ? 'selected' : ''); ?>>New</option>
                <option value="used" <?php echo ((($_POST['condition_display'] ?? '') === 'used') ? 'selected' : ''); ?>>Used</option>
            </select>
        </div>

        <div class="form-group"><label for="subscription_1_monthly" title="Monthly subscription price for a 1-month plan.">Sub 1M:</label><input type="number" step="0.01" id="subscription_1_monthly" name="subscription_1_monthly" value="<?php echo htmlspecialchars($_POST['subscription_1_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_3_monthly" title="Monthly subscription price for a 3-month plan.">Sub 3M:</label><input type="number" step="0.01" id="subscription_3_monthly" name="subscription_3_monthly" value="<?php echo htmlspecialchars($_POST['subscription_3_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_6_monthly" title="Monthly subscription price for a 6-month plan.">Sub 6M:</label><input type="number" step="0.01" id="subscription_6_monthly" name="subscription_6_monthly" value="<?php echo htmlspecialchars($_POST['subscription_6_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_12_monthly" title="Monthly subscription price for a 12-month plan.">Sub 12M:</label><input type="number" step="0.01" id="subscription_12_monthly" name="subscription_12_monthly" value="<?php echo htmlspecialchars($_POST['subscription_12_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_1_upfront" title="Upfront subscription amount for a 1-month plan.">Sub 1U:</label><input type="number" step="0.01" id="subscription_1_upfront" name="subscription_1_upfront" value="<?php echo htmlspecialchars($_POST['subscription_1_upfront'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_3_upfront" title="Upfront subscription amount for a 3-month plan.">Sub 3U:</label><input type="number" step="0.01" id="subscription_3_upfront" name="subscription_3_upfront" value="<?php echo htmlspecialchars($_POST['subscription_3_upfront'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_6_upfront" title="Upfront subscription amount for a 6-month plan.">Sub 6U:</label><input type="number" step="0.01" id="subscription_6_upfront" name="subscription_6_upfront" value="<?php echo htmlspecialchars($_POST['subscription_6_upfront'] ?? ''); ?>"></div>
        <div class="form-group"><label for="subscription_12_upfront" title="Upfront subscription amount for a 12-month plan.">Sub 12U:</label><input type="number" step="0.01" id="subscription_12_upfront" name="subscription_12_upfront" value="<?php echo htmlspecialchars($_POST['subscription_12_upfront'] ?? ''); ?>"></div>

        <div class="form-group"><label for="buyout_1_monthly" title="Monthly buyout price for a 1-month term.">Buy 1M:</label><input type="number" step="0.01" id="buyout_1_monthly" name="buyout_1_monthly" value="<?php echo htmlspecialchars($_POST['buyout_1_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_3_monthly" title="Monthly buyout price for a 3-month term.">Buy 3M:</label><input type="number" step="0.01" id="buyout_3_monthly" name="buyout_3_monthly" value="<?php echo htmlspecialchars($_POST['buyout_3_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_6_monthly" title="Monthly buyout price for a 6-month term.">Buy 6M:</label><input type="number" step="0.01" id="buyout_6_monthly" name="buyout_6_monthly" value="<?php echo htmlspecialchars($_POST['buyout_6_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_12_monthly" title="Monthly buyout price for a 12-month term.">Buy 12M:</label><input type="number" step="0.01" id="buyout_12_monthly" name="buyout_12_monthly" value="<?php echo htmlspecialchars($_POST['buyout_12_monthly'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_1_upfront" title="Upfront buyout amount for a 1-month term.">Buy 1U:</label><input type="number" step="0.01" id="buyout_1_upfront" name="buyout_1_upfront" value="<?php echo htmlspecialchars($_POST['buyout_1_upfront'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_3_upfront" title="Upfront buyout amount for a 3-month term.">Buy 3U:</label><input type="number" step="0.01" id="buyout_3_upfront" name="buyout_3_upfront" value="<?php echo htmlspecialchars($_POST['buyout_3_upfront'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_6_upfront" title="Upfront buyout amount for a 6-month term.">Buy 6U:</label><input type="number" step="0.01" id="buyout_6_upfront" name="buyout_6_upfront" value="<?php echo htmlspecialchars($_POST['buyout_6_upfront'] ?? ''); ?>"></div>
        <div class="form-group"><label for="buyout_12_upfront" title="Upfront buyout amount for a 12-month term.">Buy 12U:</label><input type="number" step="0.01" id="buyout_12_upfront" name="buyout_12_upfront" value="<?php echo htmlspecialchars($_POST['buyout_12_upfront'] ?? ''); ?>"></div>

        <div class="form-group"><label for="msrp_gross">MSRP Gross:</label><input type="number" step="0.01" id="msrp_gross" name="msrp_gross" value="<?php echo htmlspecialchars($_POST['msrp_gross'] ?? ''); ?>"></div>
        <div class="form-group"><label for="msrp_net">MSRP Net:</label><input type="number" step="0.01" id="msrp_net" name="msrp_net" value="<?php echo htmlspecialchars($_POST['msrp_net'] ?? ''); ?>"></div>
        <div class="form-group"><label for="outlet_warranty_gross">Outlet W. Gross:</label><input type="number" step="0.01" id="outlet_warranty_gross" name="outlet_warranty_gross" value="<?php echo htmlspecialchars($_POST['outlet_warranty_gross'] ?? ''); ?>"></div>
        <div class="form-group"><label for="outlet_warranty_net">Outlet W. Net:</label><input type="number" step="0.01" id="outlet_warranty_net" name="outlet_warranty_net" value="<?php echo htmlspecialchars($_POST['outlet_warranty_net'] ?? ''); ?>"></div>
        <div class="form-group"><label for="outlet_no_warranty_gross">Outlet No W. Gross:</label><input type="number" step="0.01" id="outlet_no_warranty_gross" name="outlet_no_warranty_gross" value="<?php echo htmlspecialchars($_POST['outlet_no_warranty_gross'] ?? ''); ?>"></div>
        <div class="form-group"><label for="outlet_no_warranty_net">Outlet No W. Net:</label><input type="number" step="0.01" id="outlet_no_warranty_net" name="outlet_no_warranty_net" value="<?php echo htmlspecialchars($_POST['outlet_no_warranty_net'] ?? ''); ?>"></div>
        <div class="form-group"><label for="pp_gross">PP Gross:</label><input type="number" step="0.0001" id="pp_gross" name="pp_gross" value="<?php echo htmlspecialchars($_POST['pp_gross'] ?? ''); ?>"></div>
        <div class="form-group"><label for="pp_net">PP Net:</label><input type="number" step="0.0001" id="pp_net" name="pp_net" value="<?php echo htmlspecialchars($_POST['pp_net'] ?? ''); ?>"></div>
        <div class="form-group"><label for="profit_eur">Profit EUR:</label><input type="number" step="0.0001" id="profit_eur" name="profit_eur" value="<?php echo htmlspecialchars($_POST['profit_eur'] ?? ''); ?>"></div>
        <div class="form-group"><label for="profit_percent">Profit Percent:</label><input type="number" step="0.0001" id="profit_percent" name="profit_percent" value="<?php echo htmlspecialchars($_POST['profit_percent'] ?? ''); ?>"></div>

        <button type="button" class="action-button" id="calculate-prices">Calculate Prices</button>
        <button type="submit" class="action-button">Add Product</button>
    </form>
</div>

<script type="module">
import { initializeAddProductPricingAutomation } from '../../js/add-product-pricing.js';

document.addEventListener('DOMContentLoaded', () => {
    const automation = initializeAddProductPricingAutomation();
    const calculateButton = document.getElementById('calculate-prices');

    if (automation && calculateButton) {
        calculateButton.addEventListener('click', (event) => {
            event.preventDefault();
            automation.triggerCalculation();
        });
    }
});
</script>

