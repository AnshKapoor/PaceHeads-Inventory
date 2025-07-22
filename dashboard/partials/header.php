<?php
require_once  __DIR__ . '/../../functions.php'; // Correct relative path to functions.php on root
require_once  __DIR__ . '/../../config.php';
start_session_once();

if (!is_logged_in()) {
    redirect('../index.php'); // Redirect to login if not logged in
}

$current_username = $_SESSION['username'];
$current_user_role_string = $_SESSION['role'];

$read_access_display = $_SESSION['has_read_access'] ? '✅' : '❌';
$edit_access_display = $_SESSION['has_edit_access'] ? '✅' : '❌';
$special_edit_access_display = $_SESSION['has_special_edit_access'] ? '✅' : '❌';

$page_title = isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../css/dashboard_style.css">
    <link rel="stylesheet" href="../css/dashboard_header_style.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
      <h1><a href="<?php echo BASE_URL; ?>dashboard/index.php" class="dashboard-link">Dashboard</a></h1>
        </div>
        <div class="header-right">
            <span>Welcome, <?php echo htmlspecialchars($current_username); ?>!</span>
            <div class="access-rights" title="R: Read Access | E: Edit Access | SE: Special Edit Access">
    Access:
    (R: <?php echo $read_access_display; ?>
    E: <?php echo $edit_access_display; ?>
    SE: <?php echo $special_edit_access_display; ?>)
</div>

            <a href="../index.php?logout=true" class="logout-button">Logout</a>
        </div>
    </header>
    <main class="dashboard-main-content">