<?php
$page_title = "Quotation Form";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$my_id = $_SESSION['user_id'];
$me = $conn->query("SELECT * FROM users WHERE id=$my_id")->fetch_assoc();
$clients = $conn->query("SELECT * FROM clients ORDER BY company_name ASC");
$def_remarks = $conn->query("SELECT setting_value FROM settings WHERE setting_key='default_quotation_remarks'")->fetch_object()->setting_value;

$is_edit = false;
$edit_data = null;
$edit_items = [];
$auto_no = "";

if (isset($_GET['edit_id'])) {
    $is_edit = true;
    $edit_id = intval($_GET['edit_id']);
    
    $edit_data = $conn->query("SELECT * FROM quotations WHERE id = $edit_id")->fetch_assoc();
    if (!$edit_data) {
        echo "<script>alert('Data tidak ditemukan'); window.location='quotation_list.php';</script>";
        exit;
    }
    
    $resItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $edit_id");
    while($itm = $resItems->fetch_assoc()) { $edit_items[] = $itm; }
    
    $auto_no = $edit_data['quotation_no'];
    $page_title = "Edit Quotation " . $auto_no;
} else {
    $auto_no = generateQuotationNo($conn);
}

if (isset($_POST['save_quotation'])) {
    $client_id = intval($_POST['client_id']);
    $q_no      = $conn->real_escape_string($_POST['quotation_no']);
    $q_date    = $_POST['quotation_date'];
    $curr      = $_POST['currency'];
    $remarks   = $conn->real_escape_string($_POST['remarks']);
    
    if ($is_edit) {
        $q_id = $edit_id;
        $conn->query("UPDATE quotations SET client_id=$client_id, quotation_date='$q_date', currency='$curr', remarks='$remarks' WHERE id=$q_id");
        $conn->query("DELETE FROM quotation_items WHERE quotation_id=$q_id");
    } else {
        $conn->query("INSERT INTO quotations (quotation_no, client_id, created_by_user_id, quotation_date, currency, remarks) VALUES ('$q_no', $client_id, $my_id, '$q_date', '$curr', '$remarks')");
        $q_id = $conn->insert_id;
    }
    
    $items = $_POST['item_name'];
    $card_types = $_POST['card_type']; 
    $qtys  = $_POST['qty'];
    $prices= $_POST['unit_price'];
    $descs = $_POST['description'];
    $modes = $_POST['charge_mode'];
    
    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i])) {
            $it_name = $conn->real_escape_string($items[$i]);
            $it_card = $conn->real_escape_string($card_types[$i]); 
            $it_qty  = intval($qtys[$i]);
            $it_prc  = floatval($prices[$i]);
            $it_dsc  = $conn->real_escape_string($descs[$i]);
            $it_mod  = $conn->real_escape_string($modes[$i]);
            
            $conn->query("INSERT INTO quotation_items (quotation_id, item_name, card_type, qty, unit_price, description, charge_mode) 
                          VALUES ($q_id, '$it_name', '$it_card', $it_qty, $it_prc, '$it_dsc', '$it_mod')");
        }
    }
    
    echo "<script>alert('Quotation Saved!'); window.location='quotation_list.php';</script>";
}
?>

<div class="page-heading">
    <div class="row">
        <div class="col-12 col-md-6">
            <h3><?= $is_edit ? 'Edit Quotation' : 'Create New Quotation' ?></h3>
            <p class="text-subtitle text-muted">Form pembuatan penawaran harga resmi.</p>
        </div>
    </div>
</div>

<div class="page-content">
    <form method="POST">
        
        <datalist id="cardOptions">
            <option value="Physical SIM">
            <option value="eSIM">
            <option value="IoT SIM">
            <option value="Others">
        </datalist>

        <div class="row">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="m-0 text-primary"><i class="bi bi-person-lines-fill me-2"></i> Client Information</h6>
                    </div>
                    <div class="card-body pt-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Client</label>
                            <select name="client_id" id="client_select" class="form-select" required onchange="fillClientInfo()">
                                <option value="">-- Choose Client --</option>
                                <?php 
                                $clients->data_seek(0); 
                                while($c = $clients->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c['id'] ?>" 
                                        data-addr="<?= htmlspecialchars($c['address']) ?>"
                                        data-pic="<?= htmlspecialchars($c['pic_name']) ?>"
                                        data-phone="<?= htmlspecialchars($c['pic_phone']) ?>"
                                        <?= ($is_edit && $edit_data['client_id'] == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Address</label>
                            <textarea id="cl_addr" class="form-control bg-light" readonly rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label text-muted small">Attn (PIC)</label>
                                <input type="text" id="cl_pic" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label text-muted small">PIC Phone</label>
                                <input type="text" id="cl_phone" class="form-control bg-light" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="m-0 text-warning text-dark"><i class="bi bi-file-earmark-text me-2"></i> Quotation Details</h6>
                    </div>
                    <div class="card-body pt-4">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Quotation Date</label>
                                <input type="date" name="quotation_date" class="form-control" value="<?= $is_edit ? $edit_data['quotation_date'] : date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Quotation No</label>
                                <input type="text" name="quotation_no" class="form-control fw-bold text-primary" value="<?= $auto_no ?>" readonly>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="IDR" <?= ($is_edit && $edit_data['currency'] == 'IDR') ? 'selected' : '' ?>>IDR (Rp)</option>
                                    <option value="USD" <?= ($is_edit && $edit_data['currency'] == 'USD') ? 'selected' : '' ?>>USD ($)</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Sales Contact</label>
                                <input type="text" class="form-control bg-light" value="<?= $me['username'] ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h6 class="m-0"><i class="bi bi-cart-check me-2"></i> Items List</h6>
                <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="addRow()">
                    <i class="bi bi-plus-circle me-2"></i> Add Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle" id="itemTable">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th width="20%">Item Name</th>
                                <th width="15%">Card Type <span class="text-danger">*Internal</span></th>
                                <th width="8%">Qty</th>
                                <th width="15%">Unit Price</th>
                                <th width="20%">Description</th>
                                <th width="15%">Charge Mode</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($is_edit && !empty($edit_items)): ?>
                                <?php foreach($edit_items as $itm): ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($itm['item_name']) ?>" required></td>
                                    
                                    <td>
                                        <input type="text" name="card_type[]" class="form-control" list="cardOptions" 
                                               value="<?= htmlspecialchars($itm['card_type']) ?>" 
                                               placeholder="Pilih / Ketik...">
                                    </td>
                                    
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="<?= $itm['qty'] ?>" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control text-end" value="<?= $itm['unit_price'] ?>" required></td>
                                    <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($itm['description']) ?>"></td>
                                    <td>
                                        <select name="charge_mode[]" class="form-select">
                                            <option value="Monthly" <?= $itm['charge_mode']=='Monthly'?'selected':'' ?>>Monthly</option>
                                            <option value="One Time" <?= $itm['charge_mode']=='One Time'?'selected':'' ?>>One Time</option>
                                            <option value="Annually" <?= $itm['charge_mode']=='Annually'?'selected':'' ?>>Annually</option>
                                            <option value="Daily" <?= $itm['charge_mode']=='Daily'?'selected':'' ?>>Daily</option>
                                        </select>
                                    </td>
                                    <td class="text-center"><button type="button" class="btn btn-light text-danger btn-sm border" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" placeholder="Nama Produk" required></td>
                                    
                                    <td>
                                        <input type="text" name="card_type[]" class="form-control" list="cardOptions" placeholder="Pilih / Ketik...">
                                    </td>
                                    
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="1" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control text-end" placeholder="0" required></td>
                                    <td><input type="text" name="description[]" class="form-control" placeholder="Keterangan"></td>
                                    <td>
                                        <select name="charge_mode[]" class="form-select">
                                            <option value="Monthly">Monthly</option>
                                            <option value="One Time">One Time</option>
                                            <option value="Annually">Annually</option>
                                            <option value="Daily">Daily</option>
                                        </select>
                                    </td>
                                    <td class="text-center"><button type="button" class="btn btn-light text-danger btn-sm border" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4 mb-5 shadow-sm">
            <div class="card-body">
                <label class="form-label fw-bold text-muted">Remarks / Notes</label>
                <textarea name="remarks" class="form-control bg-light" rows="4"><?= $is_edit ? htmlspecialchars($edit_data['remarks']) : $def_remarks ?></textarea>
                
                <div class="mt-4 text-end border-top pt-3">
                    <a href="quotation_list.php" class="btn btn-light border me-2">Cancel</a>
                    <button type="submit" name="save_quotation" class="btn btn-primary px-5 shadow-sm">
                        <i class="bi bi-save me-2"></i> <?= $is_edit ? 'Update Quotation' : 'Save Quotation' ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // 1. AUTO FILL CLIENT
    window.addEventListener('load', function() { fillClientInfo(); });

    function fillClientInfo() {
        var select = document.getElementById("client_select");
        if (select.selectedIndex === -1) return;
        var option = select.options[select.selectedIndex];
        
        if (option.value !== "") {
            document.getElementById("cl_addr").value = option.getAttribute("data-addr");
            document.getElementById("cl_pic").value = option.getAttribute("data-pic");
            document.getElementById("cl_phone").value = option.getAttribute("data-phone");
        } else {
            document.getElementById("cl_addr").value = "";
            document.getElementById("cl_pic").value = "";
            document.getElementById("cl_phone").value = "";
        }
    }

    // 2. ADD ROW
    function addRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        // Clone baris pertama
        var newRow = table.rows[0].cloneNode(true);
        
        // Reset Values pada baris baru
        var inputs = newRow.getElementsByTagName("input");
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") inputs[i].value="1"; 
        }
        
        // Reset Select Dropdown
        var selects = newRow.getElementsByTagName("select");
        for(var j=0; j<selects.length; j++) { selects[j].selectedIndex = 0; }
        
        table.appendChild(newRow);
    }
    
    // 3. REMOVE ROW
    function removeRow(btn) {
        var row = btn.parentNode.parentNode;
        var table = row.parentNode;
        if(table.rows.length > 1) {
            table.removeChild(row);
        } else {
            alert("Minimal harus ada 1 item.");
        }
    }
</script>