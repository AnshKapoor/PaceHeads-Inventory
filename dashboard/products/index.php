<?php
include '../partials/header.php';

// $can_edit = has_edit_access() || has_special_edit_access();

$page_title = "Product Management";
?>

<div class="dashboard-section product-management-section">
    <h2>Product Inventory</h2>
    <!-- <?php if ($can_edit): ?>
        <p>Use the 'Edit' button to modify product details. All changes are saved automatically upon submission of the modal form.</p>
    <?php else: ?>
        <p>You have read-only access to product information.</p>
    <?php endif; ?> -->

    <table id="productsTable" class="display compact" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>SKU</th>
                <th>Article</th>
                <th>Category</th>
                <th>EAN</th>
                <th>Condition</th>
                <th>Sub 1M</th>
                <th>Sub 3M</th>
                <th>Sub 6M</th>
                <th>Sub 12M</th>
                <th>Sub 1U</th>
                <th>Sub 3U</th>
                <th>Sub 6U</th>
                <th>Sub 12U</th>
                <th>Buy 1M</th>
                <th>Buy 3M</th>
                <th>Buy 6M</th>
                <th>Buy 12M</th>
                <th>Buy 1U</th>
                <th>Buy 3U</th>
                <th>Buy 6U</th>
                <th>Buy 12U</th>
                <th>MSRP Gross</th>
                <th>MSRP Net</th>
                <th>Outlet W. Gross</th>
                <th>Outlet W. Net</th>
                <th>Outlet No W. Gross</th>
                <th>Outlet No W. Net</th>
                <th>PP Gross</th>
                <th>PP Net</th>
                <th>Profit EUR</th>
                <th>Profit Percent</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Updated By</th>
                <th>Actions</th> </tr>
        </thead>
        <tbody>
            </tbody>
    </table>
</div>

<!-- <div id="editProductModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Edit Product</h2>
        <form id="editProductForm">
            <input type="hidden" id="modal_id" name="id">

            <div class="form-group"><label for="modal_sku">SKU:</label><input type="text" id="modal_sku" name="sku"></div>
            <div class="form-group"><label for="modal_article">Article:</label><input type="text" id="modal_article" name="article"></div>
            <div class="form-group"><label for="modal_category">Category:</label><input type="text" id="modal_category" name="category"></div>
            <div class="form-group"><label for="modal_ean">EAN:</label><input type="text" id="modal_ean" name="ean"></div>
            <div class="form-group"><label for="modal_condition">Condition:</label><input type="text" id="modal_condition" name="condition"></div>

            <div class="form-group"><label for="modal_subscription_1_monthly">Sub 1M:</label><input type="number" step="0.01" id="modal_subscription_1_monthly" name="subscription_1_monthly"></div>
            <div class="form-group"><label for="modal_subscription_3_monthly">Sub 3M:</label><input type="number" step="0.01" id="modal_subscription_3_monthly" name="subscription_3_monthly"></div>
            <div class="form-group"><label for="modal_subscription_6_monthly">Sub 6M:</label><input type="number" step="0.01" id="modal_subscription_6_monthly" name="subscription_6_monthly"></div>
            <div class="form-group"><label for="modal_subscription_12_monthly">Sub 12M:</label><input type="number" step="0.01" id="modal_subscription_12_monthly" name="subscription_12_monthly"></div>
            <div class="form-group"><label for="modal_subscription_1_upfront">Sub 1U:</label><input type="number" step="0.01" id="modal_subscription_1_upfront" name="subscription_1_upfront"></div>
            <div class="form-group"><label for="modal_subscription_3_upfront">Sub 3U:</label><input type="number" step="0.01" id="modal_subscription_3_upfront" name="subscription_3_upfront"></div>
            <div class="form-group"><label for="modal_subscription_6_upfront">Sub 6U:</label><input type="number" step="0.01" id="modal_subscription_6_upfront" name="subscription_6_upfront"></div>
            <div class="form-group"><label for="modal_subscription_12_upfront">Sub 12U:</label><input type="number" step="0.01" id="modal_subscription_12_upfront" name="subscription_12_upfront"></div>

            <div class="form-group"><label for="modal_buyout_1_monthly">Buy 1M:</label><input type="number" step="0.01" id="modal_buyout_1_monthly" name="buyout_1_monthly"></div>
            <div class="form-group"><label for="modal_buyout_3_monthly">Buy 3M:</label><input type="number" step="0.01" id="modal_buyout_3_monthly" name="buyout_3_monthly"></div>
            <div class="form-group"><label for="modal_buyout_6_monthly">Buy 6M:</label><input type="number" step="0.01" id="modal_buyout_6_monthly" name="buyout_6_monthly"></div>
            <div class="form-group"><label for="modal_buyout_12_monthly">Buy 12M:</label><input type="number" step="0.01" id="modal_buyout_12_monthly" name="buyout_12_monthly"></div>
            <div class="form-group"><label for="modal_buyout_1_upfront">Buy 1U:</label><input type="number" step="0.01" id="modal_buyout_1_upfront" name="buyout_1_upfront"></div>
            <div class="form-group"><label for="modal_buyout_3_upfront">Buy 3U:</label><input type="number" step="0.01" id="modal_buyout_3_upfront" name="buyout_3_upfront"></div>
            <div class="form-group"><label for="modal_buyout_6_upfront">Buy 6U:</label><input type="number" step="0.01" id="modal_buyout_6_upfront" name="buyout_6_upfront"></div>
            <div class="form-group"><label for="modal_buyout_12_upfront">Buy 12U:</label><input type="number" step="0.01" id="modal_buyout_12_upfront" name="buyout_12_upfront"></div>

            <div class="form-group"><label for="modal_msrp_gross">MSRP Gross:</label><input type="number" step="0.01" id="modal_msrp_gross" name="msrp_gross"></div>
            <div class="form-group"><label for="modal_msrp_net">MSRP Net:</label><input type="number" step="0.01" id="modal_msrp_net" name="msrp_net"></div>
            <div class="form-group"><label for="modal_outlet_warranty_gross">Outlet W. Gross:</label><input type="number" step="0.01" id="modal_outlet_warranty_gross" name="outlet_warranty_gross"></div>
            <div class="form-group"><label for="modal_outlet_warranty_net">Outlet W. Net:</label><input type="number" step="0.01" id="modal_outlet_warranty_net" name="outlet_warranty_net"></div>
            <div class="form-group"><label for="modal_outlet_no_warranty_gross">Outlet No W. Gross:</label><input type="number" step="0.01" id="modal_outlet_no_warranty_gross" name="outlet_no_warranty_gross"></div>
            <div class="form-group"><label for="modal_outlet_no_warranty_net">Outlet No W. Net:</label><input type="number" step="0.01" id="modal_outlet_no_warranty_net" name="outlet_no_warranty_net"></div>
            <div class="form-group"><label for="modal_pp_gross">PP Gross:</label><input type="number" step="0.0001" id="modal_pp_gross" name="pp_gross"></div>
            <div class="form-group"><label for="modal_pp_net">PP Net:</label><input type="number" step="0.0001" id="modal_pp_net" name="pp_net"></div>
            <div class="form-group"><label for="modal_profit_eur">Profit EUR:</label><input type="number" step="0.0001" id="modal_profit_eur" name="profit_eur"></div>
            <div class="form-group"><label for="modal_profit_percent">Profit Percent:</label><input type="number" step="0.0001" id="modal_profit_percent" name="profit_percent"></div>

            <div class="form-group"><label for="modal_created_by">Created By:</label><input type="text" id="modal_created_by" name="created_by" readonly></div>
            <div class="form-group"><label for="modal_created_at">Created At:</label><input type="text" id="modal_created_at" name="created_at" readonly></div>
            <div class="form-group"><label for="modal_updated_at">Updated At:</label><input type="text" id="modal_updated_at" name="updated_at" readonly></div>
            <div class="form-group"><label for="modal_updated_by">Updated By:</label><input type="text" id="modal_updated_by" name="updated_by_username" readonly></div> <button type="submit" class="action-button">Save Changes</button>
        </form>
    </div>
</div> -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css">

<script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js"></script>


<!-- <script>
    const canEdit = <?php echo json_encode($can_edit); ?>;
</script> -->
<script src="../../js/products_table.js"></script>

<!-- <?php
// Include the dashboard footer

?> -->