<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'My PHP Project'; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header-left">
     <div class="fixed-logo">
        <img src="<?php echo BASE_URL; ?>images/logo.png" alt="PaceHeads Logo " width="40" height="40">
    </div>
    <h1 class="product-management">Product Management</h1>
</div>
</body>