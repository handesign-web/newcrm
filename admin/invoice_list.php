<?php
// --- 1. LOAD CONFIG DULUAN (Agar Export Excel berjalan sebelum HTML) ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search       = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client     = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status     = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$f_tax        = isset($_REQUEST['tax_status']) ? $_REQUEST['tax_status'] : '';
$f_start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : '';
$f_end_date   = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : '';

// Bangun Query WHERE
$where = "1=1";

// 1. Filter Search (No Invoice)
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (i.invoice_no LIKE '%$safe_search%')";
}

// 2. Filter Client
if(!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client"; 
}

// 3. Filter Status Invoice
if(!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND i.status = '$safe_status'";
}

// 4. Filter Status Faktur Pajak
if($f_tax == 'uploaded') {
    $where .= " AND i.tax_invoice_file IS NOT NULL AND i.tax_invoice_file != ''";
} elseif($f_tax == 'pending') {
    $where .= " AND (i.tax_invoice_file IS NULL OR i.tax_invoice_file = '')";
}

// 5. Filter Tanggal
if(!empty($f_start_date)) {
    $safe_start = $conn->real_escape_string($f_start_date);
    $where .= " AND i.invoice_date >= '$safe_start'";
}
if(!empty($f_end_date)) {
    $safe_end = $conn->real_escape_string($f_end_date);
    $where .= " AND i.invoice_date <= '$safe_end'";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean(); // Bersihkan buffer HTML
    
    // [UPDATE] Query dimodifikasi untuk mengambil NOTES dari tabel scratchpad
    $sqlEx = "SELECT 
                i.invoice_date,
                i.invoice_no,
                i.status,
                i.tax_invoice_file,
                c.company_name,
                q.quotation_no,
                q.po_number_client,
                q.currency,
                u.username as sales_name,
                isp.general_notes, 
                COALESCE(
                    (SELECT SUM(qty * unit_price) FROM invoice_items WHERE invoice_id = i.id),
                    (SELECT SUM(qty * unit_price) FROM quotation_items WHERE quotation_id = i.quotation_id)
                ) as sub_total,
                COALESCE(
                    (SELECT GROUP_CONCAT(item_name SEPARATOR ', ') FROM invoice_items WHERE invoice_id = i.id),
                    (SELECT GROUP_CONCAT(item_name SEPARATOR ', ') FROM quotation_items WHERE quotation_id = i.quotation_id)
                ) as item_desc,
                (
                    SELECT GROUP_CONCAT(do.do_number SEPARATOR ', ') 
                    FROM delivery_orders do 
                    JOIN payments pay ON do.payment_id = pay.id 
                    WHERE pay.invoice_id = i.id
                ) as do_numbers
              FROM invoices i 
              JOIN quotations q ON i.quotation_id = q.id 
              JOIN clients c ON q.client_id = c.id 
              LEFT JOIN users u ON c.sales_person_id = u.id 
              LEFT JOIN invoice_scratchpads isp ON i.invoice_no = isp.invoice_no 
              WHERE $where 
              ORDER BY i.created_at DESC";
              
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Invoices_Rekap_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array('Date', 'Client', 'Invoice No', 'PO Client', 'Ref Quote', 'Description', 'Currency', 'Sub Total', 'VAT (11%)', 'Grand Total', 'Status', 'Sales Person', 'Delivery Order No', 'Status Faktur Pajak', 'Notes'));
    
    while($row = $resEx->fetch_assoc()) {
        $subTotal = floatval($row['sub_total'] ?? 0);
        $vat = $subTotal * 0.11;
        $grandTotal = $subTotal + $vat;
        
        $doNum = !empty($row['do_numbers']) ? $row['do_numbers'] : '-';
        $poClient = !empty($row['po_number_client']) ? $row['po_number_client'] : '-';
        $salesPerson = !empty($row['sales_name']) ? $row['sales_name'] : '-';
        $taxStatus = (!empty($row['tax_invoice_file'])) ? 'Uploaded' : 'Pending';
        
        $cleanNotes = !empty($row['general_notes']) ? str_replace(array("\r", "\n"), " ", $row['general_notes']) : '-';

        fputcsv($output, array(
            $row['invoice_date'],
            $row['company_name'],
            $row['invoice_no'],
            $poClient,
            $row['quotation_no'],
            $row['item_desc'],
            $row['currency'],
            $subTotal,
            $vat,
            $grandTotal,
            strtoupper($row['status']),
            $salesPerson,
            $doNum,
            $taxStatus,
            $cleanNotes 
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Invoices";
include 'includes/header.php';
include 'includes/sidebar.php';

$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// --- LOGIKA ACTION: PAYMENT ---
if (isset($_POST['confirm_payment'])) {
    $inv_id = intval($_POST['invoice_id']);
    $pay_date = $_POST['payment_date'];
    $amount_input = floatval(str_replace(['.', ','], '', $_POST['amount']));
    $grand_total_system = floatval($_POST['grand_total_system']);
    $user_id = $_SESSION['user_id'];

    if (abs($amount_input - $grand_total_system) > 1) {
        echo "<script>alert('GAGAL: Nominal pembayaran tidak sesuai dengan Total Tagihan!'); window.location='invoice_list.php';</script>";
        exit;
    }

    $proof_file = null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'PAY_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDir . $fileName)) {
                $proof_file = $fileName;
            }
        }
    }

    if ($proof_file) {
        $sqlPay = "INSERT INTO payments (invoice_id, payment_date, amount, proof_file, created_by) 
                   VALUES ($inv_id, '$pay_date', $amount_input, '$proof_file', $user_id)";
        if ($conn->query($sqlPay)) {
            $conn->query("UPDATE invoices SET status='paid' WHERE id=$inv_id");
            echo "<script>alert('Pembayaran berhasil disimpan!'); window.location='payment_list.php';</script>";
        }
    } else {
        echo "<script>alert('Gagal upload bukti pembayaran.');</script>";
    }
}

// --- LOGIKA ACTION: UPLOAD FAKTUR PAJAK ---
if (isset($_POST['upload_tax_invoice'])) {
    $inv_id = intval($_POST['tax_invoice_id']);
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['tax_file']) && $_FILES['tax_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['tax_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'TAX_' . time() . '_' . $inv_id . '.' . $ext;
            if (move_uploaded_file($_FILES['tax_file']['tmp_name'], $uploadDir . $fileName)) {
                $sqlTax = "UPDATE invoices SET tax_invoice_file = '$fileName' WHERE id = $inv_id";
                if ($conn->query($sqlTax)) {
                    echo "<script>alert('Faktur Pajak berhasil diupload!'); window.location='invoice_list.php';</script>";
                } else {
                    echo "<script>alert('Gagal update database.');</script>";
                }
            } else {
                echo "<script>alert('Gagal memindahkan file.');</script>";
            }
        } else {
            echo "<script>alert('Format file tidak didukung. Gunakan PDF/JPG/PNG.');</script>";
        }
    } else {
        echo "<script>alert('Pilih file terlebih dahulu.');</script>";
    }
}

// --- LOGIKA ACTION: UPDATE STATUS & CANCEL ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $act = $_GET['action'];
    
    if ($act == 'sent') {
        $conn->query("UPDATE invoices SET status='sent' WHERE id=$id");
    }
    if ($act == 'cancel') {
        $inv = $conn->query("SELECT quotation_id FROM invoices WHERE id=$id")->fetch_assoc();
        $q_id = $inv['quotation_id'];
        $conn->query("UPDATE invoices SET status='cancel' WHERE id=$id");
        $conn->query("UPDATE quotations SET status='cancel' WHERE id=$q_id");
        echo "<script>alert('Invoice dan Quotation terkait telah dibatalkan.');</script>";
    }
    echo "<script>window.location='invoice_list.php';</script>";
}

// --- 5. QUERY DATA TAMPILAN UTAMA ---
$sql = "SELECT i.*, c.company_name, q.quotation_no, q.currency, 
        isp.general_notes, 
        COALESCE(
            (SELECT SUM(qty * unit_price) FROM invoice_items WHERE invoice_id = i.id),
            (SELECT SUM(qty * unit_price) FROM quotation_items WHERE quotation_id = i.quotation_id)
        ) as sub_total
        FROM invoices i 
        JOIN quotations q ON i.quotation_id=q.id 
        JOIN clients c ON q.client_id=c.id 
        LEFT JOIN invoice_scratchpads isp ON i.invoice_no = isp.invoice_no
        WHERE $where
        ORDER BY i.created_at DESC";
$res = $conn->query($sql);
?>

<style>
    .table-responsive { overflow: visible !important; }
    
    .table-compact { font-size: 0.85rem; }
    .table-compact thead th { 
        font-size: 0.8rem; 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
        background-color: #f8f9fa;
        color: #6c757d;
        padding: 10px 12px;
    }
    .table-compact tbody td { 
        padding: 8px 12px; 
        vertical-align: middle; 
    }
    
    .badge-status { font-size: 0.7rem; padding: 5px 8px; }
    
    .btn-note-icon {
        border: none;
        background: transparent;
        padding: 4px;
        color: #adb5bd; 
        transition: all 0.2s;
    }
    .btn-note-icon:hover { color: #0d6efd; transform: scale(1.1); }
    .btn-note-icon.has-note { color: #ffc107; }
</style>

<div class="page-heading mb-3">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Invoice List</h3>
            <p class="text-subtitle text-muted small mb-0">Daftar tagihan dan status pembayaran.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <a href="invoice_form.php" class="btn btn-success btn-sm shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Create Manual Invoice
            </a>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3 px-4">
            <form method="GET">
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="No Invoice..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="client_id" class="form-select form-select-sm">
                            <option value="">- Semua Perusahaan -</option>
                            <?php if($clients->num_rows > 0) {
                                $clients->data_seek(0);
                                while($c = $clients->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['company_name']) ?>
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">- Semua Status -</option>
                            <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                            <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                            <option value="paid" <?= $f_status=='paid'?'selected':'' ?>>Paid</option>
                            <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-2">
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($f_start_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($f_end_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="tax_status" class="form-select form-select-sm">
                            <option value="">- Filter Faktur Pajak -</option>
                            <option value="uploaded" <?= $f_tax=='uploaded'?'selected':'' ?>>Uploaded</option>
                            <option value="pending" <?= $f_tax=='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filter</button>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="submit" formaction="invoice_list.php" formmethod="POST" name="export_excel" class="btn btn-success btn-sm w-100 text-white">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive" style="overflow:visible;">
                <table class="table table-hover table-compact mb-0 align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Invoice No</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th class="text-end">Sub Total</th>
                            <th class="text-end">VAT (11%)</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-center">Note</th> <th class="text-center">Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <?php
                                $subTotal = floatval($row['sub_total'] ?? 0);
                                $vat = $subTotal * 0.11;
                                $grandTotal = $subTotal + $vat;
                                $st = $row['status'];
                                $bg = ($st=='paid')?'success':(($st=='cancel')?'danger':(($st=='sent')?'info':'secondary'));
                                $hasTax = !empty($row['tax_invoice_file']);
                                
                                $hasNote = !empty($row['general_notes']);
                                $noteClass = $hasNote ? 'has-note' : '';
                                $noteTooltip = $hasNote ? 'Lihat Catatan' : 'Buat Catatan';
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark font-monospace">
                                    <?= $row['invoice_no'] ?>
                                    <div class="text-muted small fw-normal" style="font-size: 0.7rem;">Ref: <?= $row['quotation_no'] ?></div>
                                    <?php if($hasTax): ?>
                                        <span class="badge bg-light text-primary border mt-1" style="font-size: 0.6rem;">
                                            <i class="bi bi-check-circle-fill"></i> Tax Invoice
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['invoice_date'])) ?></td>
                                <td><div class="fw-bold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($row['company_name']) ?></div></td>
                                
                                <td class="text-end text-muted"><?= number_format($subTotal, 0, ',', '.') ?></td>
                                <td class="text-end text-muted"><?= number_format($vat, 0, ',', '.') ?></td>
                                <td class="text-end fw-bold text-primary">
                                    <small class="text-muted me-1"><?= $row['currency'] ?></small><?= number_format($grandTotal, 0, ',', '.') ?>
                                </td>

                                <td class="text-center">
                                    <button class="btn-note-icon <?= $noteClass ?>" 
                                            onclick="openNoteModal('<?= $row['invoice_no'] ?>')" 
                                            title="<?= $noteTooltip ?>"
                                            id="btn-note-<?= $row['invoice_no'] ?>">
                                        <i class="bi bi-sticky-fill fs-5"></i>
                                    </button>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-<?= $bg ?> badge-status rounded-pill"><?= strtoupper($st) ?></span>
                                </td>
                                
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0" style="font-size:0.8rem;" type="button" data-bs-toggle="dropdown">Act</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 small">
                                            <li><a class="dropdown-item text-primary" href="invoice_print.php?id=<?= $row['id'] ?>" target="_blank"><i class="bi bi-printer me-2"></i> Print PDF</a></li>
                                            <li><button class="dropdown-item" onclick="openTaxModal(<?= $row['id'] ?>, '<?= $row['invoice_no'] ?>')"><i class="bi bi-file-earmark-arrow-up me-2"></i> <?= $hasTax ? 'Update Tax' : 'Upload Tax' ?></button></li>
                                            
                                            <?php if($hasTax): ?>
                                            <li><a href="../uploads/<?= $row['tax_invoice_file'] ?>" target="_blank" class="dropdown-item"><i class="bi bi-eye me-2"></i> View Tax</a></li>
                                            <?php endif; ?>

                                            <?php if($st != 'paid' && $st != 'cancel'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <?php if($st == 'draft'): ?>
                                                <li><a class="dropdown-item text-warning" href="invoice_edit.php?id=<?= $row['id'] ?>"><i class="bi bi-pencil me-2"></i> Edit Invoice</a></li>
                                                <li><a class="dropdown-item text-info" href="?action=sent&id=<?= $row['id'] ?>"><i class="bi bi-send me-2"></i> Mark Sent</a></li>
                                                <?php endif; ?>
                                                
                                                <li><button class="dropdown-item text-success" onclick="openPayModal(<?= $row['id'] ?>, '<?= $row['invoice_no'] ?>', <?= $grandTotal ?>)"><i class="bi bi-check-circle me-2"></i> Mark Paid</button></li>
                                                <li><a class="dropdown-item text-danger" href="?action=cancel&id=<?= $row['id'] ?>" onclick="return confirm('Batalkan Invoice?')"><i class="bi bi-x-circle me-2"></i> Cancel</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center py-5 text-muted small">Tidak ada data invoice.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($res->num_rows > 20): ?>
                <div class="card-footer bg-white border-top text-center py-2 text-muted small">Menampilkan hasil pencarian</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i> Catatan Invoice</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="noteInvoiceNo">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Nomor Invoice</label>
                    <input type="text" id="noteTitle" class="form-control form-control-sm bg-light fw-bold" readonly>
                </div>
                <div>
                    <label class="form-label small fw-bold text-muted">Isi Catatan</label>
                    <textarea id="generalNotes" class="form-control" rows="8" placeholder="Tulis catatan internal di sini..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <span id="saveStatus" class="me-auto small text-success fw-bold" style="display:none;"><i class="bi bi-check-circle"></i> Tersimpan!</span>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="saveNote()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content" onsubmit="return validatePayment()">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title"><i class="bi bi-wallet2 me-2"></i> Konfirmasi Pembayaran</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="invoice_id" id="modal_inv_id">
                <input type="hidden" name="grand_total_system" id="modal_grand_total">
                <div class="alert alert-light-success border-success text-center py-2 mb-3">
                    <strong id="modal_inv_no" class="d-block"></strong>
                    <small>Tagihan: <strong class="text-success">Rp <span id="display_total"></span></strong></small>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Tanggal Bayar</label>
                    <input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Nominal (Harus Sesuai)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="amount" id="input_amount" class="form-control" required>
                    </div>
                    <div id="err_msg" class="text-danger small mt-1 fw-bold" style="display:none;">Nominal tidak sesuai!</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Bukti Transfer</label>
                    <input type="file" name="proof_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" name="confirm_payment" class="btn btn-success btn-sm w-100">Proses Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="taxModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title"><i class="bi bi-cloud-upload me-2"></i> Upload Faktur Pajak</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="tax_invoice_id" id="tax_invoice_id">
                <div class="mb-2">
                    <label class="form-label small fw-bold">No. Invoice</label>
                    <input type="text" id="tax_inv_no" class="form-control form-control-sm bg-light" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">File (PDF/JPG/PNG)</label>
                    <input type="file" name="tax_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" name="upload_tax_invoice" class="btn btn-warning btn-sm w-100">Upload</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    let systemTotal = 0;

    // --- LOGIC CATATAN ---
    function openNoteModal(invoiceNo) {
        document.getElementById('noteInvoiceNo').value = invoiceNo;
        document.getElementById('noteTitle').value = invoiceNo;
        document.getElementById('generalNotes').value = "Loading...";
        document.getElementById('saveStatus').style.display = 'none';

        // Load Data via AJAX
        const formData = new FormData();
        formData.append('action', 'load');
        formData.append('invoice_no', invoiceNo);

        fetch('ajax_scratchpad.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success' && res.data) {
                document.getElementById('generalNotes').value = res.data.general_notes || '';
            } else {
                document.getElementById('generalNotes').value = '';
            }
            new bootstrap.Modal(document.getElementById('noteModal')).show();
        });
    }

    function saveNote() {
        const inv = document.getElementById('noteInvoiceNo').value;
        const notes = document.getElementById('generalNotes').value;
        const btn = document.getElementById('btn-note-' + inv);

        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('invoice_no', inv);
        formData.append('notes', notes);
        
        formData.append('calc_data', '[]'); 

        fetch('ajax_scratchpad.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                const s = document.getElementById('saveStatus');
                s.style.display = 'inline-block';
                setTimeout(() => { 
                    s.style.display = 'none'; 
                    bootstrap.Modal.getInstance(document.getElementById('noteModal')).hide();
                }, 1000);

                if(notes.trim() !== "") {
                    btn.classList.add('has-note');
                } else {
                    btn.classList.remove('has-note');
                }
            } else {
                alert('Gagal simpan: ' + res.message);
            }
        });
    }

    // --- LOGIC LAIN ---
    function openPayModal(id, no, total) {
        systemTotal = parseFloat(total);
        document.getElementById('modal_inv_id').value = id;
        document.getElementById('modal_inv_no').innerText = no;
        document.getElementById('modal_grand_total').value = total;
        document.getElementById('display_total').innerText = new Intl.NumberFormat('id-ID').format(total);
        document.getElementById('input_amount').value = ""; 
        document.getElementById('err_msg').style.display = 'none';
        new bootstrap.Modal(document.getElementById('payModal')).show();
    }

    function openTaxModal(id, no) {
        document.getElementById('tax_invoice_id').value = id;
        document.getElementById('tax_inv_no').value = no;
        new bootstrap.Modal(document.getElementById('taxModal')).show();
    }

    function validatePayment() {
        let inputVal = parseFloat(document.getElementById('input_amount').value);
        if (isNaN(inputVal) || Math.abs(inputVal - systemTotal) > 1) {
            document.getElementById('err_msg').style.display = 'block';
            return false;
        }
        return true;
    }
</script>