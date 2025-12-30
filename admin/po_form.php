<?php
$page_title = "Create New Purchase Order";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Ambil data User yang sedang Login (Kontak, Email, Phone)
$user_id = $_SESSION['user_id'] ?? 0;
$user_info = $conn->query("SELECT email, phone FROM users WHERE id = $user_id")->fetch_assoc();

// Ambil list Vendors
$vendors = $conn->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC");

// --- FUNGSI PENOMORAN PO OTOMATIS ---
function generatePoNumber($conn) {
    $year_month = date("Ym");
    $prefix = "POLF" . $year_month; // Contoh: POLF202512

    // Ambil nomor urut tertinggi untuk bulan ini (4 digit)
    $sql = "SELECT MAX(SUBSTRING(po_number, 11)) as last_num FROM purchase_orders WHERE po_number LIKE '{$prefix}%'";
    $result = $conn->query($sql)->fetch_assoc();
    $last_num = (int)$result['last_num'];
    
    $new_num = $last_num + 1;
    // Format: POLFYYYYMMNNNN -> POLF2025120001
    return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
}

// Inisialisasi data PO
$po_data = [
    'id' => 0,
    'po_number' => generatePoNumber($conn),
    'vendor_id' => '',
    'po_date' => date('Y-m-d'),
    'total_amount' => 0.00,
    'status' => 'Draft',
];
$po_items = [];
$is_edit = false;

// Logic Edit Mode (jika ada ID di URL)
if (isset($_GET['id'])) {
    $po_id = intval($_GET['id']);
    $po_data_res = $conn->query("SELECT * FROM purchase_orders WHERE id=$po_id");
    if ($po_data_res->num_rows > 0) {
        $po_data = $po_data_res->fetch_assoc();
        $is_edit = true;
        
        // Ambil Item PO
        $po_items_res = $conn->query("SELECT * FROM po_items WHERE po_id=$po_id");
        while($item = $po_items_res->fetch_assoc()) {
            $po_items[] = $item;
        }
        $page_title = "Edit Purchase Order " . $po_data['po_number'];
    }
}

// --- LOGIC SAVE/SUBMIT PO ---
if (isset($_POST['save_po'])) {
    $po_id = intval($_POST['po_id']);
    $vendor_id = intval($_POST['vendor_id']);
    $po_date = $conn->real_escape_string($_POST['po_date']);
    $total_amount = floatval(str_replace(',', '', $_POST['final_total_amount']));
    $status = $conn->real_escape_string($_POST['status']);
    
    // Data Item dari JS
    $items_json = json_decode($_POST['po_items_data'], true);

    // 1. Simpan Header PO
    if ($po_id == 0) {
        $po_number = generatePoNumber($conn);
        $sql_header = "INSERT INTO purchase_orders (po_number, vendor_id, created_by_user_id, po_date, total_amount, status) 
                       VALUES ('$po_number', $vendor_id, $user_id, '$po_date', $total_amount, '$status')";
        $conn->query($sql_header);
        $po_id = $conn->insert_id;
        $msg = "Purchase Order $po_number Berhasil Dibuat!";
    } else {
        $po_number = $conn->real_escape_string($_POST['po_number']);
        $sql_header = "UPDATE purchase_orders SET vendor_id=$vendor_id, po_date='$po_date', total_amount=$total_amount, status='$status' WHERE id=$po_id";
        $conn->query($sql_header);
        // Hapus item lama jika edit
        $conn->query("DELETE FROM po_items WHERE po_id=$po_id");
        $msg = "Purchase Order $po_number Berhasil Diupdate!";
    }

    // 2. Simpan Item PO
    if (!empty($items_json) && $po_id > 0) {
        foreach ($items_json as $item) {
            $desc = $conn->real_escape_string($item['description']);
            $platform = $conn->real_escape_string($item['platform']);
            $sub = $conn->real_escape_string($item['sub']);
            $qty = floatval($item['qty']);
            $unit_price = floatval($item['unit_price']);
            $currency = $conn->real_escape_string($item['currency']);
            $total = floatval($item['total']);
            $charge_mode = $conn->real_escape_string($item['charge_mode']);

            $sql_item = "INSERT INTO po_items (po_id, description, platform, sub, qty, unit_price, currency, total, charge_mode) 
                         VALUES ($po_id, '$desc', '$platform', '$sub', $qty, $unit_price, '$currency', $total, '$charge_mode')";
            $conn->query($sql_item);
        }
    }

    echo "<script>alert('$msg'); window.location='po_list.php';</script>";
}
?>

<div class="page-heading">
    <h3><?= $page_title ?></h3>
    <p class="text-subtitle text-muted">Formulir pembuatan Purchase Order baru.</p>
</div>

<div class="page-content">
    <form method="POST">
        <input type="hidden" name="po_id" value="<?= $po_data['id'] ?>">
        <input type="hidden" name="po_number" value="<?= $po_data['po_number'] ?>">
        <input type="hidden" name="po_items_data" id="po_items_data">
        <input type="hidden" name="final_total_amount" id="final_total_amount">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title">PO Header & Vendor Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">PO Number</label>
                            <input type="text" class="form-control" value="<?= $po_data['po_number'] ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">PO Date</label>
                            <input type="date" name="po_date" class="form-control" value="<?= $po_data['po_date'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Draft" <?= $po_data['status'] == 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Submitted" <?= $po_data['status'] == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="Approved" <?= $po_data['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="Rejected" <?= $po_data['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Vendor</label>
                            <select name="vendor_id" id="vendor_id" class="form-select" required>
                                <option value="">-- Pilih Vendor --</option>
                                <?php while($v = $vendors->fetch_assoc()): ?>
                                    <option value="<?= $v['id'] ?>" <?= $po_data['vendor_id'] == $v['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['company_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3 alert alert-light">
                            <h6 class="fw-bold">Our Contact Person (User Login)</h6>
                            <p class="small mb-0">
                                Contact: <?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?><br>
                                Email: <?= htmlspecialchars($user_info['email'] ?? 'N/A') ?><br>
                                Phone: <?= htmlspecialchars($user_info['phone'] ?? 'N/A') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title text-white m-0">PO Items</h5>
                <button type="button" class="btn btn-sm btn-light" onclick="openItemModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Item
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="itemsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Description / Platform / Sub</th>
                                <th>Charge Mode</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end fw-bold">GRAND TOTAL:</td>
                                <td class="text-end fw-bold fs-5" id="grandTotalDisplay">IDR 0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p id="noItemMessage" class="text-center text-muted py-3" style="display:none;">
                    Klik "Add Item" untuk menambahkan item PO.
                </p>
            </div>
        </div>

        <div class="d-grid gap-2 mb-5">
            <button type="submit" name="save_po" class="btn btn-success btn-lg shadow-sm">
                <i class="bi bi-save me-2"></i> Save Purchase Order
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add/Edit PO Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="itemIndex">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label">Description <small class="text-danger">*</small></label>
                        <textarea id="item_description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Platform</label>
                        <input type="text" id="item_platform" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sub</label>
                        <input type="text" id="item_sub" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Qty <small class="text-danger">*</small></label>
                        <input type="number" id="item_qty" class="form-control" min="0" step="0.01" value="1" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Unit Price <small class="text-danger">*</small></label>
                        <div class="input-group">
                            <select id="item_currency" class="form-select input-group-text" style="max-width: fit-content;">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                            </select>
                            <input type="number" id="item_unit_price" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Total (Calculated)</label>
                        <input type="text" id="item_total_display" class="form-control fw-bold bg-light" readonly>
                        <input type="hidden" id="item_total">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Charge Mode</label>
                        <select id="item_charge_mode_select" class="form-select" onchange="toggleChargeModeInput(this.value)">
                            <option value="">-- Select --</option>
                            <option value="TBD">TBD</option>
                            <option value="Per Service">Per Service</option>
                            <option value="Per Project">Per Project</option>
                            <option value="Per Month">Per Month</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="item_charge_mode_manual" class="form-control mt-2" placeholder="Input Charge Mode Manual" style="display:none;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveItem()">Save Item</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    let poItems = <?= json_encode($po_items) ?>;
    const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
    const defaultCurrency = 'IDR';
    const currencyFormat = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: defaultCurrency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    // Initial check for items on edit mode
    document.addEventListener('DOMContentLoaded', function() {
        if (poItems.length > 0) {
            renderItems();
        } else {
            document.getElementById('noItemMessage').style.display = 'block';
        }
        updateFinalTotal();
    });

    // --- Kalkulasi & Tampilan Item ---

    function calculateTotal() {
        const qty = parseFloat(document.getElementById('item_qty').value) || 0;
        const price = parseFloat(document.getElementById('item_unit_price').value) || 0;
        const currency = document.getElementById('item_currency').value;
        const total = qty * price;
        
        document.getElementById('item_total').value = total.toFixed(2);
        
        // Update display with correct currency format
        let formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        document.getElementById('item_total_display').value = formatter.format(total);
    }
    
    document.getElementById('item_qty').addEventListener('input', calculateTotal);
    document.getElementById('item_unit_price').addEventListener('input', calculateTotal);
    document.getElementById('item_currency').addEventListener('change', calculateTotal);


    function toggleChargeModeInput(value) {
        const manualInput = document.getElementById('item_charge_mode_manual');
        if (value === 'Other') {
            manualInput.style.display = 'block';
            manualInput.value = '';
        } else {
            manualInput.style.display = 'none';
        }
    }

    function openItemModal(index = -1) {
        // Reset form
        document.getElementById('itemIndex').value = index;
        document.getElementById('item_description').value = '';
        document.getElementById('item_platform').value = '';
        document.getElementById('item_sub').value = '';
        document.getElementById('item_qty').value = 1;
        document.getElementById('item_unit_price').value = '';
        document.getElementById('item_currency').value = defaultCurrency;
        document.getElementById('item_charge_mode_select').value = '';
        document.getElementById('item_charge_mode_manual').style.display = 'none';
        
        calculateTotal();

        if (index > -1) {
            // Edit existing item
            const item = poItems[index];
            document.getElementById('item_description').value = item.description;
            document.getElementById('item_platform').value = item.platform;
            document.getElementById('item_sub').value = item.sub;
            document.getElementById('item_qty').value = item.qty;
            document.getElementById('item_unit_price').value = item.unit_price;
            document.getElementById('item_currency').value = item.currency;
            
            // Handle Charge Mode
            const chargeModeSelect = document.getElementById('item_charge_mode_select');
            const chargeModeManual = document.getElementById('item_charge_mode_manual');
            
            // Cek apakah item.charge_mode adalah salah satu opsi dropdown
            if (Array.from(chargeModeSelect.options).map(opt => opt.value).includes(item.charge_mode)) {
                chargeModeSelect.value = item.charge_mode;
                chargeModeManual.style.display = 'none';
            } else {
                // Jika tidak ada di dropdown, set ke 'Other' dan masukkan nilai manual
                chargeModeSelect.value = 'Other';
                chargeModeManual.value = item.charge_mode;
                chargeModeManual.style.display = 'block';
            }
            
            calculateTotal(); // Recalculate based on existing data
        }
        itemModal.show();
    }

    function saveItem() {
        const index = parseInt(document.getElementById('itemIndex').value);
        const description = document.getElementById('item_description').value.trim();
        
        if (!description || parseFloat(document.getElementById('item_qty').value) <= 0 || parseFloat(document.getElementById('item_unit_price').value) <= 0) {
            alert('Deskripsi, Qty, dan Unit Price harus diisi dengan nilai yang valid.');
            return;
        }

        let chargeMode = document.getElementById('item_charge_mode_select').value;
        if (chargeMode === 'Other') {
            chargeMode = document.getElementById('item_charge_mode_manual').value.trim();
        } else if (chargeMode === '') {
            chargeMode = 'N/A';
        }
        
        const newItem = {
            description: description,
            platform: document.getElementById('item_platform').value,
            sub: document.getElementById('item_sub').value,
            qty: parseFloat(document.getElementById('item_qty').value).toFixed(2),
            unit_price: parseFloat(document.getElementById('item_unit_price').value).toFixed(2),
            currency: document.getElementById('item_currency').value,
            total: parseFloat(document.getElementById('item_total').value).toFixed(2),
            charge_mode: chargeMode,
        };

        if (index > -1) {
            poItems[index] = newItem; // Edit
        } else {
            poItems.push(newItem); // Add new
        }
        
        renderItems();
        itemModal.hide();
    }

    function deleteItem(index) {
        if (confirm('Yakin ingin menghapus item ini?')) {
            poItems.splice(index, 1);
            renderItems();
        }
    }

    function renderItems() {
        const tbody = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
        tbody.innerHTML = '';
        
        if (poItems.length === 0) {
            document.getElementById('noItemMessage').style.display = 'block';
            document.getElementById('itemsTable').style.display = 'none';
        } else {
            document.getElementById('noItemMessage').style.display = 'none';
            document.getElementById('itemsTable').style.display = 'table';
            
            poItems.forEach((item, index) => {
                let formatter = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: item.currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>
                        <span class="fw-bold">${item.description}</span>
                        <div class="small text-muted mt-1">
                            Platform: ${item.platform || '-'} | Sub: ${item.sub || '-'}
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border">${item.charge_mode}</span></td>
                    <td class="text-end">${item.qty}</td>
                    <td class="text-end">${formatter.format(item.unit_price)}</td>
                    <td class="text-end fw-bold">${formatter.format(item.total)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openItemModal(${index})"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteItem(${index})"><i class="bi bi-trash"></i></button>
                    </td>
                `;
            });
        }
        updateFinalTotal();
        
        // Update hidden JSON input for PHP submission
        document.getElementById('po_items_data').value = JSON.stringify(poItems);
    }
    
    function updateFinalTotal() {
        let totalIDR = 0;
        let totalUSD = 0;

        poItems.forEach(item => {
            if (item.currency === 'IDR') {
                totalIDR += parseFloat(item.total);
            } else if (item.currency === 'USD') {
                totalUSD += parseFloat(item.total);
            }
        });

        // Display Total IDR (assuming system operates primarily in IDR)
        // If USD exists, show both.
        let displayHTML = '';
        if (totalIDR > 0) {
            displayHTML += currencyFormat.format(totalIDR);
        }
        if (totalUSD > 0) {
            if (displayHTML) displayHTML += ' + ';
            displayHTML += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(totalUSD);
        }
        
        if (!displayHTML) displayHTML = 'IDR 0.00';

        document.getElementById('grandTotalDisplay').innerHTML = displayHTML;
        
        // Set IDR total to be saved in the database (assuming IDR is the primary stored value)
        document.getElementById('final_total_amount').value = totalIDR.toFixed(2); 
    }
</script>