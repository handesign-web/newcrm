<?php
$page_title = "Generate Invoice";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$my_id = $_SESSION['user_id'];
$auto_inv = generateInvoiceNo($conn);
$is_manual = true; // Default Manual
$source_data = [];
$source_items = [];

// MODE 1: DARI PO / QUOTATION (OTOMATIS)
if (isset($_GET['source_id'])) {
    $is_manual = false;
    $q_id = intval($_GET['source_id']);
    
    // Ambil Data Header
    $sql = "SELECT q.*, c.company_name, c.address, c.pic_name 
            FROM quotations q 
            JOIN clients c ON q.client_id = c.id 
            WHERE q.id = $q_id";
    $source_data = $conn->query($sql)->fetch_assoc();
    
    if(!$source_data) die("Data Quotation tidak ditemukan.");

    // Ambil Item Quotation untuk ditampilkan sebagai DEFAULT VALUE (Editable)
    $resItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $q_id");
    while($itm = $resItems->fetch_assoc()) {
        $source_items[] = $itm;
    }
}

// MODE 2: MANUAL (PERLU LIST CLIENT)
$clients = $conn->query("SELECT * FROM clients ORDER BY company_name ASC");


// --- PROSES SIMPAN INVOICE ---
if (isset($_POST['save_invoice'])) {
    $inv_no = $conn->real_escape_string($_POST['invoice_no']);
    $inv_date = $_POST['invoice_date'];
    $due_date = $_POST['due_date'];
    $pymt_method = $conn->real_escape_string($_POST['payment_method_col']);
    
    // Ambil Data Form Item (Data hasil edit/validasi user)
    $items = $_POST['item_name'];
    $qtys  = $_POST['qty'];
    $prices= $_POST['unit_price'];
    $descs = $_POST['description'];
    $cards = isset($_POST['card_type']) ? $_POST['card_type'] : [];
    
    // Tentukan Quotation ID yang akan direferensikan oleh Invoice
    if ($is_manual) {
        // --- JIKA MANUAL: BUAT QUOTATION BAYANGAN DULU (LOGIKA LAMA) ---
        $client_id = intval($_POST['client_id']);
        $curr = $_POST['currency'];
        
        // [BARU] Ambil Input PO Reference Manual
        $po_ref = isset($_POST['po_ref']) ? $conn->real_escape_string($_POST['po_ref']) : '';
        
        $q_no_dummy = "Q-AUTO-" . time(); 
        
        // 1. Insert Quotation Dummy (Sekarang menyimpan PO Number Client juga)
        $sqlQ = "INSERT INTO quotations (quotation_no, client_id, created_by_user_id, quotation_date, currency, status, po_number_client) 
                 VALUES ('$q_no_dummy', $client_id, $my_id, '$inv_date', '$curr', 'invoiced', '$po_ref')";
        
        if($conn->query($sqlQ)) {
            $quot_id_ref = $conn->insert_id;
            
            // 2. Insert Items ke Quotation Items (untuk manual)
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i])) {
                    $it_name = $conn->real_escape_string($items[$i]);
                    $it_qty  = intval($qtys[$i]);
                    $it_prc  = floatval($prices[$i]);
                    $it_dsc  = $conn->real_escape_string($descs[$i]);
                    $it_card = isset($cards[$i]) ? $conn->real_escape_string($cards[$i]) : '';
                    
                    $conn->query("INSERT INTO quotation_items (quotation_id, item_name, qty, unit_price, description, card_type) 
                                  VALUES ($quot_id_ref, '$it_name', $it_qty, $it_prc, '$it_dsc', '$it_card')");
                }
            }
        } else {
            die("Error creating shadow quotation: " . $conn->error);
        }

    } else {
        // --- JIKA DARI PO / OTOMATIS: REFER KE QUOTATION ASLI ---
        $quot_id_ref = intval($_POST['quotation_id']);
    }

    // --- INSERT INVOICE ---
    // Invoice tetap merujuk ke Quotation Asli ID ($quot_id_ref)
    $sqlInv = "INSERT INTO invoices (invoice_no, quotation_id, invoice_date, due_date, status, payment_method, created_by_user_id) 
               VALUES ('$inv_no', $quot_id_ref, '$inv_date', '$due_date', 'draft', '$pymt_method', $my_id)";
    
    if ($conn->query($sqlInv)) {
        $invoice_id = $conn->insert_id;
        
        // --- INSERT ITEMS KE TABEL BARU invoice_items ---
        // Item Invoice disimpan terpisah dari Quotation items
        if (!$is_manual) {
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i])) {
                    $it_name = $conn->real_escape_string($items[$i]);
                    $it_qty  = intval($qtys[$i]);
                    $it_prc  = floatval($prices[$i]);
                    $it_dsc  = $conn->real_escape_string($descs[$i]);
                    $it_card = isset($cards[$i]) ? $conn->real_escape_string($cards[$i]) : '';
                    
                    // MEMASUKKAN KE TABEL BARU: invoice_items
                    $conn->query("INSERT INTO invoice_items (invoice_id, item_name, qty, unit_price, description, card_type) 
                                  VALUES ($invoice_id, '$it_name', $it_qty, $it_prc, '$it_dsc', '$it_card')");
                }
            }
        }
        
        // Update status quotation asli jika dari PO
        if (!$is_manual) {
            $conn->query("UPDATE quotations SET status='invoiced' WHERE id=$quot_id_ref");
        }
        
        echo "<script>alert('Invoice Created Successfully!'); window.location='invoice_list.php';</script>";
    } else {
        echo "<script>alert('Gagal membuat invoice: " . $conn->error . "');</script>";
    }
}
?>

<div class="page-heading">
    <h3><?= $is_manual ? 'Create Manual Invoice' : 'Generate Invoice from PO' ?></h3>
    <?php if(!$is_manual): ?>
    <div class="alert alert-light-primary border-primary">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Validasi Invoice:</strong> Anda dapat menghapus atau mengubah Quantity item di bawah sebelum menyimpan. Invoice akan mereferensikan Quotation Asli.
    </div>
    <?php endif; ?>
</div>

<div class="page-content">
    <form method="POST">
        <?php if(!$is_manual): ?>
            <input type="hidden" name="quotation_id" value="<?= $source_data['id'] ?>">
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light"><strong>Bill To</strong></div>
                    <div class="card-body pt-3">
                        
                        <?php if($is_manual): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Client</label>
                                <select name="client_id" id="client_select" class="form-select" required onchange="fillClientInfo()">
                                    <option value="">-- Choose Client --</option>
                                    <?php while($c = $clients->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" 
                                            data-addr="<?= htmlspecialchars($c['address']) ?>"
                                            data-pic="<?= htmlspecialchars($c['pic_name']) ?>">
                                            <?= htmlspecialchars($c['company_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">PO Reference (Manual)</label>
                                <input type="text" name="po_ref" class="form-control" placeholder="e.g. PO-001-CLIENT">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Address</label>
                                <textarea id="cl_addr" class="form-control bg-light" rows="3" readonly></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">PIC</label>
                                <input type="text" id="cl_pic" class="form-control bg-light" readonly>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label>Client</label>
                                <input type="text" class="form-control bg-light" value="<?= $source_data['company_name'] ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label>Address</label>
                                <textarea class="form-control bg-light" rows="3" readonly><?= $source_data['address'] ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label>PIC</label>
                                <input type="text" class="form-control bg-light" value="<?= $source_data['pic_name'] ?>" readonly>
                            </div>
                            <div class="alert alert-info py-2 small">
                                <strong>PO Ref:</strong> <?= $source_data['po_number_client'] ?>
                                <br>Quotation Ref: <strong><?= $source_data['quotation_no'] ?></strong>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white"><strong>Invoice Details</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-2">
                            <label class="fw-bold">Invoice No</label>
                            <input type="text" name="invoice_no" class="form-control fw-bold fs-5" value="<?= $auto_inv ?>" readonly>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Invoice Date</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="fw-bold">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>
                        </div>
                        
                        <?php if($is_manual): ?>
                            <div class="mb-3">
                                <label class="fw-bold">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="IDR">IDR (Rp)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="fw-bold">Currency</label>
                                <input type="text" class="form-control bg-light" value="<?= $source_data['currency'] ?>" readonly>
                            </div>
                        <?php endif; ?>

                        <div class="mt-2">
                            <label>Payment Method Label (Table)</label>
                            <input type="text" name="payment_method_col" class="form-control" value="Prepaid">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Items List</strong>
                <button type="button" class="btn btn-sm btn-primary" onclick="addRow()"><i class="bi bi-plus"></i> Add Item</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="itemTable">
                        <thead class="bg-light">
                            <tr>
                                <th width="30%">Item Name</th>
                                <th width="15%">Card Type (Int)</th>
                                <th width="10%">Qty</th>
                                <th width="20%">Unit Price</th>
                                <th>Desc</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!$is_manual): ?>
                                <?php foreach($source_items as $itm): ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" value="<?= htmlspecialchars($itm['item_name']) ?>" required></td>
                                    <td><input type="text" name="card_type[]" class="form-control" value="<?= htmlspecialchars($itm['card_type']) ?>"></td>
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="<?= $itm['qty'] ?>" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control text-end" value="<?= $itm['unit_price'] ?>" required></td>
                                    <td><input type="text" name="description[]" class="form-control" value="<?= htmlspecialchars($itm['description']) ?>"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                                <?php endforeach; ?>
                            
                            <?php else: ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control" required></td>
                                    <td><input type="text" name="card_type[]" class="form-control" placeholder="Optional"></td>
                                    <td><input type="number" name="qty[]" class="form-control text-center" value="1" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control text-end" required></td>
                                    <td><input type="text" name="description[]" class="form-control"></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <a href="invoice_list.php" class="btn btn-light border me-2">Cancel</a>
                <button type="submit" name="save_invoice" class="btn btn-success px-4"><i class="bi bi-check-circle"></i> Save Invoice</button>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function fillClientInfo() {
        var select = document.getElementById("client_select");
        if(select && select.selectedIndex > 0) {
            var opt = select.options[select.selectedIndex];
            document.getElementById("cl_addr").value = opt.getAttribute("data-addr");
            document.getElementById("cl_pic").value = opt.getAttribute("data-pic");
        } else if(select) {
            document.getElementById("cl_addr").value = "";
            document.getElementById("cl_pic").value = "";
        }
    }

    function addRow() {
        var table = document.getElementById("itemTable").getElementsByTagName('tbody')[0];
        // Clone baris pertama untuk struktur
        var newRow = table.rows[0].cloneNode(true);
        var inputs = newRow.getElementsByTagName("input");
        for(var i=0; i<inputs.length; i++) { 
            inputs[i].value = ""; 
            if(inputs[i].name == "qty[]") inputs[i].value="1"; 
        }
        table.appendChild(newRow);
    }

    function removeRow(btn) {
        var row = btn.parentNode.parentNode;
        var table = row.parentNode;
        if(table.rows.length > 1) {
            table.removeChild(row);
        } else {
            alert("Invoice minimal harus memiliki 1 item.");
        }
    }
</script>