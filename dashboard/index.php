<?php
// Include the database configuration and common functions
// Note: functions.php is also included by dashboard_header.php, but it's good practice
// to explicitly include config.php here if you directly use $conn for queries.
require_once '../config.php';
require_once '../functions.php';

// Include the dashboard-specific header.
// This partial handles starting the session, checking if the user is logged in,
// and setting up the top-right user info and access rights display.
include './partials/header.php';

// --- Fetch Total Registered Users Count ---
$total_users = 0;
$total_products = 0;
$new_log_entries_7days = 0;
if ($conn) { // Check if $conn is available (it should be from config.php)
    $sql = "SELECT COUNT(id) AS user_count FROM users";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_users = $row['user_count'];
    }

    // Fetch Total Product Count
    $sql_products = "SELECT COUNT(id) AS total_products FROM products";
    $result_products = $conn->query($sql_products);
    if ($result_products && $result_products->num_rows > 0) {
        $row_products = $result_products->fetch_assoc();
        $total_products = $row_products['total_products'];
    }

    // Fetch New Log Entries in Last 7 Days
    $sql_logs = "SELECT COUNT(id) AS new_logs FROM activity_log WHERE timestamp >= NOW() - INTERVAL 7 DAY";
    $result_logs = $conn->query($sql_logs);
    if ($result_logs && $result_logs->num_rows > 0) {
        $row_logs = $result_logs->fetch_assoc();
        $new_log_entries_7days = $row_logs['new_logs'];
    }
    // It's good practice to close the connection after you're done with queries on this page.
    // If you plan more queries later on this page, you might close it at the very end.
    $conn->close();
}
// --- End Fetch Total Registered Users Count ---

// The dashboard header (dashboard_header.php) is included above.
// Now, we'll render the main content of the dashboard within the <main> tags
// opened by the dashboard_header.php.
?>
  <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="./css/dashboard_style.css">
    <link rel="stylesheet" href="./css/dashboard_header_style.css">
<div class="dashboard-section welcome-dashboard-section">
    <h2>Welcome to Your Dashboard!</h2>
    <p>Hello, <span class="highlight-username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!</p>
    <p>You have successfully logged in.</p>
</div>

<div class="dashboard-section user-count-section">
    <h2>Registered Users</h2>
    <p>There are currently <span class="highlight-count"><?php echo $total_users; ?></span> registered users in the system.</p>
</div>

<?php
// Example of conditional sections based on access rights.
// These sections will only be displayed if the logged-in user
// has the corresponding access defined in their session role.

if (has_read_access()): // Checks $_SESSION['has_read_access']
?>
<div class="dashboard-section">
    <h2>Overview Data (Read Access)</h2>
    <p>This section displays general information that users with read access can view.</p>
    <ul>
        <li>Total Products: <strong><?php echo $total_products; ?></strong></li>
        <li>Log Entries(Last 7 Days): <strong><?php echo $new_log_entries_7days; ?></strong></li>
    </ul>
</div>
<?php else: ?>
<div class="dashboard-section no-access">
    <h2>Overview Data</h2>
    <p>You do not have read access to this section.</p>
</div>
<?php endif; ?>

<?php if (has_edit_access()): // Checks $_SESSION['has_edit_access']
?>
<div class="dashboard-section">
    <h2>Data Management</h2>
    <p>View and manage product inventory.</p>
    <ul>
        <li><a href="products/index.php" class="admin-link">Manage Products</a></li>
        <li><a href="products/add.php" class="admin-link">Add New Product</a></li> 
    </ul>
</div>
<?php else: ?>
<div class="dashboard-section no-access">
    <h2>Quick Actions</h2>
    <p>You do not have edit access to this section.</p>
</div>
<?php endif; ?>

<?php if (has_special_edit_access()): // Checks $_SESSION['has_special_edit_access']
?>
<div class="dashboard-section admin-tools-section">
    <h2>Admin Tools (Special Edit Access)</h2>
    <p>Access special administrative functions.</p>
    <ul>
        <li><a href="../add_user.php" class="admin-link">Add New User</a></li>
        <li>System Configuration</li>
        <li><a href="logs/index.php" class="admin-link">View Activity Log</a></li>
    </ul>
</div>
<?php else: ?>
<div class="dashboard-section no-access">
    <h2>Admin Tools</h2>
    <p>You do not have special edit access for administration tools.</p>
</div>
<?php endif; ?>


<?php
// Include the dashboard-specific footer, which closes the <main> and <body> tags.
// include 'partials/dashboard_footer.php';
?>