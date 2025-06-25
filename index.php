<?php
require_once 'config.php';
require_once 'functions.php';

start_session_once();

$login_error = "";

// Handle Logout
if (isset($_GET['logout'])) {
    // Log logout activity BEFORE destroying session
    if (is_logged_in()) {
        log_activity('LOGOUT', $_SESSION['username'] . ' logged out.');
    }
    $_SESSION = array();
    session_destroy();
    redirect(BASE_URL . 'index.php');
}

// Check if the user is already logged in
if (is_logged_in()) {
    // OPTIONAL: Log successful login if user wasn't just redirected from fresh login
    // This might be redundant if the login form submission also logs it.
    // For now, let's log only on form submission.
    redirect(BASE_URL . 'dashboard/index.php');
}

// Handle Login Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($input_password, $row['password'])) {
            // Login successful: Store user data
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; // Store the full 3-digit role string
            log_activity('LOGIN', $_SESSION['username'] . ' logged in successfully.', ['user_id' => $_SESSION['id']]);
            // --- NEW: Parse role string into individual boolean session flags ---
            $role_string = $row['role'];
            $_SESSION['has_read_access'] = (substr($role_string, 0, 1) === '1');
            $_SESSION['has_edit_access'] = (substr($role_string, 1, 1) === '1');
            $_SESSION['has_special_edit_access'] = (substr($role_string, 2, 1) === '1');
            // --- END NEW ---

            redirect('dashboard/index.php');
        } else {
            $login_error = "Invalid username or password.";
            log_activity('LOGIN_FAILED', 'Failed login attempt.', ['username_attempt' => $input_username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        }
    } else {
        $login_error = "Invalid username or password.";
    }

    $stmt->close();
}

$conn->close();
$page_title = "Please Sign In";
include 'partials/header.php';
?>

<div class="container" onclick="">
    <div class="top"></div>
    <div class="bottom"></div>
    <div class="center">
        <h2>Please Sign In</h2>

        <?php if (!empty($login_error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($login_error); ?></p>
        <?php endif; ?>

        <form action="index.php" method="POST" style="width:100%;">
            <input type="text" name="username" placeholder="username" required autocomplete="username">
            <input type="password" name="password" placeholder="password" required autocomplete="current-password">
            <h2>&nbsp;</h2>
            <button type="submit">Login</button>
        </form>
    </div>
</div>

<?php include 'partials/footer.php'; ?>