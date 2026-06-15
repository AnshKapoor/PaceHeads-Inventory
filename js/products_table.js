$(document).ready(function() {
    // Define all product column names as they appear in your DB query results
    // This order MUST match the columns array in DataTable init
    const productColumnNames = [
        "id", "sku", "article", "category", "ean", "condition",
        "subscription_1_monthly", "subscription_3_monthly", "subscription_6_monthly", "subscription_12_monthly",
        "subscription_1_upfront", "subscription_3_upfront", "subscription_6_upfront", "subscription_12_upfront",
        "buyout_1_monthly", "buyout_3_monthly", "buyout_6_monthly", "buyout_12_monthly",
        "buyout_1_upfront", "buyout_3_upfront", "buyout_6_upfront", "buyout_12_upfront",
        "msrp_gross", "msrp_net", "outlet_warranty_gross", "outlet_warranty_net",
        "outlet_no_warranty_gross", "outlet_no_warranty_net", "pp_gross", "pp_net",
        "profit_eur", "profit_percent",
        "created_by_username", "created_at", "updated_at", "updated_by_username" // Names for display from JOINs
    ];

    // Define the columns array for DataTables
    const dataTableColumns = productColumnNames.map(name => ({ "data": name }));

    // Always add the "Actions" column definition
    dataTableColumns.push({
        "data": null, // This column doesn't map directly to data
        "orderable": false, // Cannot be sorted
        "searchable": false, // Cannot be searched
        "render": function (data, type, row) {
          if (canEdit) { // 'canEdit' is passed from PHP (true/false based on user role)
                return '<div class="product-actions">' +
                    '<button class="edit-button action-button" data-id="' + row.id + '">Edit</button>' +
                    '<button class="delete-button action-button" data-id="' + row.id + '">Delete</button>' +
                    '</div>';
            } else {
                return ''; // Return an empty string if user cannot edit
            }
            
        }
    });

    const productsTable = $('#productsTable').DataTable({
        "processing": true,
        "serverSide": true,
        "scrollX": true, // Enable horizontal scrolling for many columns
        "scrollY": "50vh",
        "scrollCollapse": true,
        "deferRender": true,
        "pageLength": 25, // Sets the default number of rows to display initially
        "lengthMenu": [ // Provides options for the "Show entries" dropdown
            [10, 25, 50, 100, 200, -1], // Values
            [10, 25, 50, 100, 200, "All"] // Display text
        ],
        "ajax": {
            "url": "api/update_product.php", // Endpoint for fetching and updating
            "type": "POST", // Using POST as previously discussed to avoid 414 error
            "data": function (d) {
                return d; // DataTables sends parameters as form data for POST by default
            }
        },
        "columns": dataTableColumns, // Use the dynamically created columns array
        "order": [[0, 'asc']], // Default sort by ID
        "fixedColumns": {
            leftColumns: 1 // Fix the first column (ID) on the left
            // You can add rightColumns if you want to fix columns on the right
            // rightColumns: 1 // Example: fix the last column (Actions) on the right
        }
    });

    // --- Modal Logic ---
    const modal = $('#editProductModal');
    const closeButton = $('.close-button');
    const editForm = $('#editProductForm');
    const deleteConfirmModal = $('#deleteConfirmModal');
    const deleteConfirmMessage = $('#deleteConfirmMessage');
    const cancelProductDelete = $('#cancelProductDelete');
    const confirmProductDelete = $('#confirmProductDelete');
    let currentRowData = null; // Store data of the row being edited
    let pendingDelete = null;

    function closeDeleteConfirmation() {
        deleteConfirmModal.css('display', 'none');

        if (pendingDelete) {
            pendingDelete.button.trigger('focus');
            pendingDelete = null;
        }
    }

    function deleteProduct(product, deleteButton) {
        deleteButton.prop('disabled', true);

        $.ajax({
            url: BASE_URL_JS + "dashboard/products/api/delete_product.php",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: product.id }),
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    productsTable.ajax.reload(null, false);
                } else {
                    alert('Error deleting product: ' + response.message);
                    deleteButton.prop('disabled', false);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                const message = response && response.message
                    ? response.message
                    : 'The product could not be deleted.';
                alert('Error deleting product: ' + message);
                deleteButton.prop('disabled', false);
            }
        });
    }

    // Open modal on Edit button click (only if canEdit is true)
    if (canEdit) {
    const modal = $('#editProductModal');
    const closeButton = $('.close-button');
    const editForm = $('#editProductForm');
    let currentRowData = null;

    $('#productsTable tbody').on('click', '.edit-button', function() {
        // --- ADD THIS LINE ---
        console.log("Edit button click detected!");
        // --- End ADD ---

        const row = $(this).parents('tr');
        currentRowData = productsTable.row(row).data();

        if (currentRowData) {
           // --- THIS IS THE MODAL POPULATION LOGIC ---
                // Populate modal form fields with current row data.
                // Iterate over form elements by their name attribute.
                editForm.find('input, textarea, select').each(function() {
                    const input = $(this);
                    const name = input.attr('name'); // Get the name attribute of the input field

                    // Debugging each input
                    // console.log("Processing input name:", name, " | Value in rowData:", currentRowData[name]);

                    // Check if input has a name and corresponding data exists in the row
                    if (name && currentRowData.hasOwnProperty(name)) {
                        if (input.attr('type') === 'checkbox') {
                            input.prop('checked', currentRowData[name] == 1);
                        } else if (input.is('select')) {
                            const currentValue = currentRowData[name] ?? '';

                            if (currentValue && !input.find('option').filter(function() {
                                return $(this).val() === String(currentValue);
                            }).length) {
                                input.append($('<option>', {
                                    value: currentValue,
                                    text: currentValue
                                }));
                            }

                            input.val(currentValue);
                        } else if (input.attr('readonly')) { // For read-only display fields (like created_by_username)
                            // For columns like created_by (ID), updated_by (ID) which have usernames for display
                            if (name === 'created_by') {
                                input.val(currentRowData['created_by_username'] || currentRowData[name]);
                            } else if (name === 'updated_by') {
                                input.val(currentRowData['updated_by_username'] || currentRowData[name]);
                            } else {
                                input.val(currentRowData[name]);
                            }
                        } else {
                            // Default case for text, number, textarea, etc.
                            input.val(currentRowData[name]);
                        }
                    } else if (name) {
                        // This warns if an input has a name but no corresponding data in rowData
                        console.warn("No data property found for input name:", name, "in rowData.");
                    }
                });
                // --- END MODAL POPULATION LOGIC ---
            modal.css('display', 'flex'); // Show the modal
        }
    });

    $('#productsTable tbody').on('click', '.delete-button', function() {
        const deleteButton = $(this);
        const row = deleteButton.parents('tr');
        const product = productsTable.row(row).data();

        if (!product) {
            alert('Error: Product data could not be found.');
            return;
        }

        const productLabel = product.article || product.sku || ('Product #' + product.id);
        pendingDelete = { product: product, button: deleteButton };
        deleteConfirmMessage.text('"' + productLabel + '" will be permanently removed. This action cannot be undone.');
        deleteConfirmModal.css('display', 'flex');
        cancelProductDelete.trigger('focus');
    });

    cancelProductDelete.on('click', closeDeleteConfirmation);

    confirmProductDelete.on('click', function() {
        if (!pendingDelete) {
            return;
        }

        const productToDelete = pendingDelete.product;
        const deleteButton = pendingDelete.button;
        pendingDelete = null;
        deleteConfirmModal.css('display', 'none');
        deleteProduct(productToDelete, deleteButton);
    });

    deleteConfirmModal.on('click', function(event) {
        if (event.target === this) {
            closeDeleteConfirmation();
        }
    });

    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && deleteConfirmModal.is(':visible')) {
            closeDeleteConfirmation();
        }
    });
}
    // Close modal when 'x' is clicked
    closeButton.on('click', function() {
        modal.css('display', 'none');
    });

    // Close modal when clicking outside of it
    $(window).on('click', function(event) {
        if (event.target == modal[0]) {
            modal.css('display', 'none');
        }
    });

    // Handle form submission inside modal
    editForm.on('submit', function(e) {
        e.preventDefault();
        const formData = {};
        $(this).find('input, textarea, select').each(function() {
            const input = $(this);
            const name = input.attr('name');
            if (name && !input.attr('readonly') && name !== 'updateNotes') { // Only include editable fields
                if (input.attr('type') === 'checkbox') {
                    formData[name] = input.prop('checked') ? 1 : 0;
                } else {
                    formData[name] = input.val();
                }
            }
        });

        const productId = formData.id; // Get product ID from form
        const updateNotes = $('#updateNotes').val(); // Capture notes separately

        if (!productId) {
            alert('Error: Product ID not found for update.');
            return;
        }

        // Send updated data to server via AJAX (this uses POST with JSON body)
        $.ajax({
            url: BASE_URL_JS + "dashboard/products/api/update_product.php",
            type: 'POST',
            contentType: 'application/json', // Send as JSON
            data: JSON.stringify({
                id: productId,
                data: formData, // Send all form data as an object
                notes: updateNotes
            }),
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    modal.css('display', 'none'); // Hide modal
                    productsTable.ajax.reload(null, false); // Reload DataTables data without resetting pagination
                } else {
                    alert('Error updating product: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX Error: ' + xhr.responseText);
                console.error("AJAX Error: ", error, xhr.responseText);
            }
        });
    });
});
