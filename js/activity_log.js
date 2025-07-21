$(document).ready(function () {
    const activityLogTable = $("#activityLogTable").DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: BASE_URL_JS + "dashboard/logs/api/fetch_logs.php", // Use BASE_URL_JS here
            type: "POST",
            data: function (d) {
                return d;
            },
        },
        columns: [
            { data: "id" },
            { data: "username", defaultContent: "System/N/A" },
            { data: "action_type" },
            { data: "description" },
            {
                data: "details", // Raw JSON string from API
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    if (data) {
                        // Display a clickable link/button to show details in a modal
                        return (
                            '<button class="view-details-button action-button" data-id="' +
                            row.id +
                            '">View Details</button>'
                        );
                    }
                    return ""; // No details
                },
            },
            { data: "ip_address", defaultContent: "N/A" },
            { data: "user_agent", defaultContent: "N/A" },
            { data: "timestamp" },
        ],
        order: [[7, "desc"]],
    });

    // --- Details Modal Logic ---
    const detailsModal = $("#detailsModal");
    const closeDetailsButton = detailsModal.find(".close-button");
    const jsonDetailsContent = $("#jsonDetailsContent"); // This will now hold formatted HTML

    // Open modal on "View Details" button click
    $("#activityLogTable tbody").on("click", ".view-details-button", function () {
        const row = $(this).parents("tr");
        const rowData = activityLogTable.row(row).data();

        if (rowData && rowData.details) {
            try {
                // Pass action_type and raw details JSON string to the formatter
                const formattedHtml = formatLogDetails(
                    rowData.action_type,
                    rowData.details
                );
                jsonDetailsContent.html(formattedHtml); // Use .html() to insert generated HTML
            } catch (e) {
                jsonDetailsContent.text("Error formatting details: " + rowData.details);
                console.error("Error formatting JSON for log details:", e);
            }
            detailsModal.css("display", "flex"); // Show the modal
        }
    });

    // Close modal when 'x' is clicked
    closeDetailsButton.on("click", function () {
        detailsModal.css("display", "none");
    });

    // Close modal when clicking outside of it
    $(window).on("click", function (event) {
        if (event.target == detailsModal[0]) {
            detailsModal.css("display", "none");
        }
    });
}); // End of $(document).ready()

// --- New Function: Formats JSON details into human-readable HTML ---
function formatLogDetails(actionType, detailsJsonString) {
    let html = "";
    let details = {};

    try {
        if (detailsJsonString) {
            details = JSON.parse(detailsJsonString);
        }
    } catch (e) {
        // If JSON is malformed, just display it as raw text
        console.error("Failed to parse details JSON:", e, detailsJsonString);
        return `<p><strong>Invalid Details JSON:</strong></p><pre>${detailsJsonString}</pre>`;
    }

    // Handle cases where details might be null or empty object after parsing
    if (Object.keys(details).length === 0 && details.constructor === Object) {
        return "<p>No specific details recorded.</p>";
    }

    html += '<div class="formatted-details">'; // Wrapper for styling

    switch (actionType) {
        case "LOGIN":
            html += '<p class="detail-title"><strong>Login Event:</strong></p>';
            html += "<ul>";
            html += `<li><strong>User ID:</strong> ${details.user_id || "N/A"}</li>`;
            html += `<li><strong>Username:</strong> ${details.username_attempt || "N/A"
                }</li>`;
            if (details.ip)
                html += `<li><strong>IP Address:</strong> ${details.ip}</li>`;
            html += "</ul>";
            break;

        case "LOGIN_FAILED":
            html +=
                '<p class="detail-title failed"><strong>Failed Login Attempt:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Attempted Username:</strong> ${details.username_attempt || "N/A"
                }</li>`;
            if (details.ip)
                html += `<li><strong>IP Address:</strong> ${details.ip}</li>`;
            html += "</ul>";
            break;

        case "LOGOUT":
            html += '<p class="detail-title"><strong>Logout Event:</strong></p>';
            html += "<ul>";
            if (details.user_id)
                html += `<li><strong>User ID:</strong> ${details.user_id}</li>`;
            if (details.username)
                html += `<li><strong>Username:</strong> ${details.username}</li>`;
            html += "</ul>";
            break;

        case "PRODUCT_UPDATE":
            html +=
                '<p class="detail-title updated"><strong>Product Update:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Product Id:</strong> <span class="highlight">${details.product_id || "N/A"
                }</span></li>`;
            if (details.product_name) {
                html += `<li><strong>Product Name:</strong> ${details.product_name}</li>`;
            }
            html += `<li><strong>Editor User ID:</strong> ${details.editor_user_id || "N/A"
                }</li>`;
            // --- NEW LOGIC FOR DISPLAYING CHANGES ---
            if (details.changes && Object.keys(details.changes).length > 0) {
                html += `<li><strong>Changed Fields:</strong></li><ul>`;
                for (const fieldName in details.changes) {
                    if (details.changes.hasOwnProperty(fieldName)) {
                        const change = details.changes[fieldName];
                        // Display old and new values, handling null for clarity
                        const oldValue =
                            change.old === null ? "NULL" : JSON.stringify(change.old); // Use JSON.stringify for clarity if old is empty string/null
                        const newValue =
                            change.new === null ? "NULL" : JSON.stringify(change.new);

                        html += `<li><span class="field-label">${fieldName}:</span> <span class="old-value">${oldValue}</span> &rarr; <span class="new-value">${newValue}</span></li>`;
                    }
                }
                html += `</ul>`;
            } else {
                html += `<li>No specific field changes recorded (might be 'no change' log or details not captured).</li>`;
            }
            // --- END NEW LOGIC ---

            html += "</ul>";
            break;

        case "PRODUCT_UPDATE_FAILED":
            html +=
                '<p class="detail-title failed"><strong>Product Update Failed:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Product ID:</strong> ${details.product_id || "N/A"
                }</li>`;
            if (details.error_message)
                html += `<li><strong>Error:</strong> <span class="error-text">${details.error_message}</span></li>`;
            if (details.editor_user_id)
                html += `<li><strong>Editor User ID:</strong> ${details.editor_user_id}</li>`;
            // You might want to display attempted_fields here too if they are useful for debugging
            html += "</ul>";
            break;

        case "USER_ADD":
            html +=
                '<p class="detail-title added"><strong>New User Added:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Username:</strong> ${details.added_username || "N/A"
                }</li>`;
            html += `<li><strong>Email:</strong> ${details.added_email || "N/A"
                }</li>`;
            html += `<li><strong>Role:</strong> ${details.added_role || "N/A"}</li>`;
            html += `<li><strong>Added By:</strong> User ID ${details.admin_user_id || "N/A"
                }</li>`;
            html += "</ul>";
            break;

        case "USER_ADD_FAILED":
            html +=
                '<p class="detail-title failed"><strong>Failed User Addition:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Attempted Username:</strong> ${details.attempted_username || "N/A"
                }</li>`;
            if (details.error)
                html += `<li><strong>Error:</strong> <span class="error-text">${details.error}</span></li>`;
            if (details.admin_user_id)
                html += `<li><strong>Attempted By:</strong> User ID ${details.admin_user_id}</li>`;
            html += "</ul>";
            break;

        case "CRON_LOG_CLEANUP":
            html +=
                '<p class="detail-title system-event"><strong>Log Cleanup Event:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Type:</strong> ${details.type || "N/A"}</li>`;
            html += `<li><strong>Months Retained:</strong> ${details.months_retained || "N/A"
                }</li>`;
            html += `<li><strong>Status:</strong> Success</li>`;
            html += "</ul>";
            break;

        case "CRON_LOG_CLEANUP_FAILED":
            html +=
                '<p class="detail-title system-event failed"><strong>Log Cleanup Failed:</strong></p>';
            html += "<ul>";
            html += `<li><strong>Type:</strong> ${details.type || "N/A"}</li>`;
            if (details.error)
                html += `<li><strong>Error:</strong> <span class="error-text">${details.error}</span></li>`;
            html += `</ul>`;
            break;

        // Default case for any other action_type not explicitly handled
        default:
            html +=
                '<p class="detail-title default"><strong>Raw Details (Type: ' +
                actionType +
                "):</strong></p>";
            html += `<pre>${JSON.stringify(details, null, 2)}</pre>`; // Fallback to pretty-print raw JSON
            break;
    }
    html += "</div>"; // Close .formatted-details
    return html;
}
