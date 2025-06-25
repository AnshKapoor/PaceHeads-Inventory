<?php
require_once 'config.php';    // Include database connection
require_once 'functions.php'; // Include common functions

start_session_once();

// --- Admin Access Check ---
if (!is_super_admin()) {
    redirect(BASE_URL . 'index.php');
}

$add_user_error = "";
$add_user_success = "";

// Handle Add User Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = trim($_POST['username']);
    $input_email = trim($_POST['email']);
    $input_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Input validation (basic)
    if (empty($input_username) || empty($input_email) || empty($input_password) || empty($confirm_password)) {
        $add_user_error = "All fields are required.";
    } elseif ($input_password !== $confirm_password) {
        $add_user_error = "Passwords do not match.";
    } elseif (strlen($input_password) < 6) {
        $add_user_error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $add_user_error = "Invalid email format.";
    } else {
        // Hash the password securely
        $hashed_password = password_hash($input_password, PASSWORD_DEFAULT);

        // Check if username or email already exists using prepared statements
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $input_username, $input_email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $add_user_error = "Username or Email already exists.";
        } else {
            // Insert the new user into the database using prepared statements
            $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $input_username, $input_email, $hashed_password);

            if ($stmt_insert->execute()) {
                $add_user_success = "User '" . htmlspecialchars($input_username) . "' added successfully!";
                // Log the activity
                log_activity('USER_ADD', 'User ' . $input_username . ' added by ' . ($_SESSION['username'] ?? 'System'), [
                    'added_username' => $input_username,
                    'added_email' => $input_email,
                    'added_role' => $input_role,
                    'admin_user_id' => $_SESSION['id'] ?? null
                ]);
                $_POST = array(); // Clear form fields
            } else {
                $add_user_error = "Error: " . $stmt_insert->error;
                log_activity('USER_ADD_FAILED', 'Failed to add user ' . $input_username . ' by ' . ($_SESSION['username'] ?? 'System'), [
                    'attempted_username' => $input_username,
                    'error' => $stmt_insert->error,
                    'admin_user_id' => $_SESSION['id'] ?? null
                ]);
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

$conn->close(); // Close database connection
$page_title = "Add New User"; // Set page title for header
include 'partials/header.php'; // Include the header
?>

<div class="container">
    <div class="top"></div>
    <div class="bottom"></div>
    <div class="center">
        <h2>Add New User</h2>

        <?php if (!empty($add_user_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($add_user_error); ?></p>
        <?php endif; ?>
        <?php if (!empty($add_user_success)): ?>
            <p class="success-message"><?php echo htmlspecialchars($add_user_success); ?></p>
        <?php endif; ?>

        <form action="add_user.php" method="POST" style="width:100%;">
            <input type="text" name="username" placeholder="username" required autocomplete="off"
                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            <input type="email" name="email" placeholder="email" required autocomplete="off"
                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            <input type="password" name="password" placeholder="password" required autocomplete="new-password">
            <input type="password" name="confirm_password" placeholder="confirm password" required
                autocomplete="new-password">
            <h2>&nbsp;</h2>
            <button type="submit">Add User</button>
        </form>
        <a href="index.php" class="register-link">Go to Dashboard</a>
    </div>
</div>

<?php include 'partials/footer.php'; // Include the footer ?>