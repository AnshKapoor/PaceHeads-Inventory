$(document).ready(function() {
    const activityLogTable = $('#activityLogTable').DataTable({
        "processing": true,
        "serverSide": true,
        "scrollX": true, // Enable horizontal scrolling if needed
        "ajax": {
            "url": BASE_URL_JS + "dashboard/logs/api/fetch_logs.php", // Endpoint to fetch data
            "type": "POST",
            "data": function (d) {
                return d;
            }
        },
        "columns": [
            { "data": "id" },
            { "data": "username", "defaultContent": "System/N/A" }, // User's username
            { "data": "action_type" },
            { "data": "description" },
            {
                "data": "details", // Raw JSON string
                "orderable": false,
                "searchable": false, // Don't search raw JSON directly
                "render": function (data, type, row) {
                    if (data) {
                        // Display a clickable link/button to show details
                        return '<button class="view-details-button action-button" data-id="' + row.id + '">View Details</button>';
                    }
                    return ''; // No details
                }
            },
            { "data": "ip_address", "defaultContent": "N/A" },
            { "data": "user_agent", "defaultContent": "N/A" },
            { "data": "timestamp" }
        ],
        "order": [[7, 'desc']] // Default sort by timestamp, newest first
    });

    // --- Details Modal Logic ---
    const detailsModal = $('#detailsModal');
    const closeDetailsButton = detailsModal.find('.close-button');
    const jsonDetailsContent = $('#jsonDetailsContent');

    // Open modal on "View Details" button click
    $('#activityLogTable tbody').on('click', '.view-details-button', function() {
        const row = $(this).parents('tr');
        const rowData = activityLogTable.row(row).data();

        if (rowData && rowData.details) {
            try {
                // Parse the JSON string and pretty-print it
                const parsedJson = JSON.parse(rowData.details);
                jsonDetailsContent.text(JSON.stringify(parsedJson, null, 2)); // 2-space indentation
            } catch (e) {
                jsonDetailsContent.text("Error parsing JSON: " + rowData.details);
                console.error("Error parsing JSON for log details:", e);
            }
            detailsModal.css('display', 'flex'); // Show the modal
        }
    });

    // Close modal when 'x' is clicked
    closeDetailsButton.on('click', function() {
        detailsModal.css('display', 'none');
    });

    // Close modal when clicking outside of it
    $(window).on('click', function(event) {
        if (event.target == detailsModal[0]) {
            detailsModal.css('display', 'none');
        }
    });
});