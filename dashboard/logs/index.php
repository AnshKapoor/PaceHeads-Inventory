<?php
// FIX: Explicitly include config.php here
require_once '../../config.php'; // Path from dashboard/logs/ to config.php
require_once '../../functions.php';

// Access control: Only super admins can view this page
if (!has_special_edit_access()) {
    redirect(BASE_URL . 'dashboard/index.php'); // Redirect if not super admin
}

$page_title = "System Activity Log"; // Specific page title for this view
/** @var string $page_title Holds the human-readable title for the activity log page. */

// Reuse the shared dashboard header so we get the standard navigation and layout.
include '../partials/header.php';

?>

<div class="dashboard-section activity-log-section">
    <h2>System Activity Log</h2>
    <p>Displays a record of user actions and system events.</p>

    <table id="activityLogTable" class="display compact" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Action Type</th>
                <th>Description</th>
                <th>Details</th>
                 <th>Notes</th>
                <th>IP Address</th>
                
                <th>User Agent</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            </tbody>
    </table>
    <div style="margin: 10px 0;">
    <button id="editNotesBtn" class="action-button">Edit Notes</button>
    <button id="saveNotesBtn" class="action-button" disabled>Save Changes</button>
</div>


</div>

<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Log Entry Details</h2>
        <div id="jsonDetailsContent" style="background-color: #f0f0f0; padding: 15px; border-radius: 5px; max-height: 70vh; overflow-y: auto;"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="../css/dashboard_style.css">
<script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>

<div id="app-config" data-base-url="<?php echo htmlspecialchars(BASE_URL); ?>" style="display: none;"></div>
<script>
    // Read BASE_URL from the hidden div
    const BASE_URL_JS = document.getElementById('app-config').dataset.baseUrl;
</script>
<script src="../../js/activity_log.js"></script>
