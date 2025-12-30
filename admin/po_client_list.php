<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search    = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client  = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status  = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';

// Bangun Query WHERE Dasar (Hanya PO Received atau Invoiced)
$where = "q.status IN ('po_received', 'invoiced')";

// Filter Text (PO No, Quote No)
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (q.po_number_client LIKE '%$safe_search%' OR q.quotation_no LIKE '%$safe_search%')";
}

// Filter Client
if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client";
}

// Filter Status
if (!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND q.status = '$safe_status'";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT q.*, c.company_name, c.pic_name, u.username 
              FROM quotations q 
              JOIN clients c ON q.client_id = c.id 
              JOIN users u ON q.created_by_user_id = u.id 
              WHERE $where 
              ORDER BY q.created_at DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=PO_Client_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('PO Number', 'Quote Ref', 'Date', 'Client', 'PIC', 'Total Amount', 'Currency', 'Status', 'Sales Person', 'PO File'));
    
    while($row = $resEx->fetch_assoc()) {
        $qId = $row['id'];
        // Hitung Total Amount PO
        $total = $conn->query("SELECT SUM(qty * unit_price) as t FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc()['t'];
        
        fputcsv($output, array(
            $row['po_number_client'],
            $row['quotation_no'],
            $row['quotation_date'],
            $row['company_name'],
            $row['pic_name'],
            $total,
            $row['currency'],
            $row['status'],
            $row['username'],
            $row['po_file_client'] ? 'Uploaded' : 'Pending'
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "PO From Client";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// LOGIKA CANCEL PO
if (isset($_GET['cancel_id'])) {
    $q_id = intval($_GET['cancel_id']);
    $conn->query("UPDATE quotations SET status='cancel' WHERE id=$q_id");
    echo "<script>alert('PO dan Quotation berhasil dibatalkan!'); window.location='po_client_list.php';</script>";
}

// LOGIKA PROCESS TO INVOICE
if (isset($_GET['process_invoice_id'])) {
    $q_id = intval($_GET['process_invoice_id']);
    echo "<script>window.location='invoice_form.php?source_id=$q_id';</script>";
}

// LOGIKA UPLOAD PO DOC (BARU)
if (isset($_POST['upload_po_doc'])) {
    $q_id = intval($_POST['quotation_id']);
    $uploadDir = __DIR__ . '/../uploads/';
    
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['po_file']) && $_FILES['po_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['po_file']['name'], PATHINFO_EXTENSION));
        // Validasi tipe file
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $fileName = 'PO_' . time() . '_' . $q_id . '.' . $ext;
            if (move_uploaded_file($_FILES['po_file']['tmp_name'], $uploadDir . $fileName)) {
                $sqlUp = "UPDATE quotations SET po_file_client = '$fileName' WHERE id = $q_id";
                if ($conn->query($sqlUp)) {
                    echo "<script>alert('Dokumen PO berhasil diupload!'); window.location='po_client_list.php';</script>";
                } else {
                    echo "<script>alert('Gagal update database.');</script>";
                }
            } else {
                echo "<script>alert('Gagal memindahkan file ke server.');</script>";
            }
        } else {
            echo "<script>alert('Format file tidak didukung. Harap gunakan PDF, JPG, atau PNG.');</script>";
        }
    } else {
        echo "<script>alert('Silakan pilih file terlebih dahulu.');</script>";
    }
}

// QUERY DATA TAMPILAN
$sql = "SELECT q.*, c.company_name, u.username 
        FROM quotations q 
        JOIN clients c ON q.client_id = c.id 
        JOIN users u ON q.created_by_user_id = u.id 
        WHERE $where
        ORDER BY q.created_at DESC";
$res = $conn->query($sql);
?>

<style>
    .table-responsive { overflow: visible !important; }
</style>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Purchase Order (Client)</h3>
            <p class="text-subtitle text-muted">Daftar PO yang diterima dari client.</p>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#filterPanel">
            <h6 class="m-0 text-primary fw-bold"><i class="bi bi-funnel-fill me-2"></i> Filter Data & Export</h6>
            <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="card-body collapse show" id="filterPanel">
            <div class="row g-3">
                <div class="col-lg-9">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="No PO / No Quote..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select name="client_id" class="form-select">
                                <option value="">- Semua Client -</option>
                                <?php 
                                if($clients->num_rows > 0) {
                                    $clients->data_seek(0);
                                    while($c = $clients->fetch_assoc()): 
                                ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">- Semua Status -</option>
                                <option value="po_received" <?= $f_status=='po_received'?'selected':'' ?>>Pending Invoice</option>
                                <option value="invoiced" <?= $f_status=='invoiced'?'selected':'' ?>>Invoiced</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-3 border-start d-flex align-items-center justify-content-end">
                    <form method="POST" class="w-100">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($f_status) ?>">
                        
                        <button type="submit" name="export_excel" class="btn btn-success w-100 text-white">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export to Excel
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if(!empty($search) || !empty($f_client) || !empty($f_status)): ?>
                <div class="mt-3 text-center border-top pt-2">
                    <small class="text-muted">Filter aktif.</small> 
                    <a href="po_client_list.php" class="text-danger text-decoration-none fw-bold ms-2">Reset Filter</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-top border-success border-3">
        <div class="card-body">
            <div class="table-responsive" style="overflow: visible;">
                <table class="table table-hover align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>PO Number</th>
                            <th>Quote Ref</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>PO Doc</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold text-success"><?= htmlspecialchars($row['po_number_client']) ?></td>
                                <td class="text-muted font-monospace"><?= $row['quotation_no'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['company_name']) ?></div>
                                    <small class="text-muted"><i class="bi bi-person me-1"></i> <?= $row['username'] ?></small>
                                </td>
                                <td>
                                    <?php if($row['status'] == 'po_received'): ?>
                                        <span class="badge bg-warning text-dark">Pending Invoice</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Invoiced</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['po_file_client']): ?>
                                        <a href="../uploads/<?= $row['po_file_client'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lihat Bukti">
                                            <i class="bi bi-file-earmark-pdf"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-boundary="viewport">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            
                                            <li>
                                                <a class="dropdown-item" href="quotation_print.php?id=<?= $row['id'] ?>" target="_blank">
                                                    <i class="bi bi-eye me-2"></i> View Quote
                                                </a>
                                            </li>

                                            <li>
                                                <button class="dropdown-item" onclick="openUploadModal(<?= $row['id'] ?>, '<?= $row['po_number_client'] ?>')">
                                                    <i class="bi bi-cloud-upload me-2"></i> <?= $row['po_file_client'] ? 'Update PO Doc' : 'Upload PO Doc' ?>
                                                </button>
                                            </li>

                                            <?php if($row['status'] == 'po_received'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item fw-bold text-primary" href="?process_invoice_id=<?= $row['id'] ?>">
                                                        <i class="bi bi-receipt me-2"></i> Process to Invoice
                                                    </a>
                                                </li>
                                                
                                                <li>
                                                    <a class="dropdown-item text-danger" href="?cancel_id=<?= $row['id'] ?>" onclick="return confirm('PERINGATAN: Membatalkan PO ini juga akan membatalkan Quotation. Lanjutkan?')">
                                                        <i class="bi bi-x-circle me-2"></i> Cancel PO
                                                    </a>
                                                </li>
                                            <?php else: ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><span class="dropdown-item text-muted disabled"><i class="bi bi-check2-all me-2"></i> Already Invoiced</span></li>
                                            <?php endif; ?>
                                            
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Tidak ada data PO ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($res->num_rows > 20): ?>
            <div class="card-footer bg-white border-top text-center py-3">
                <small class="text-muted">Menampilkan hasil pencarian</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadPOModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title"><i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload PO Document</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="quotation_id" id="modal_q_id">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">PO Number</label>
                    <input type="text" id="modal_po_no" class="form-control form-control-sm bg-light" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">File (PDF / Image)</label>
                    <input type="file" name="po_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-text text-muted small">Maksimal 5MB.</div>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="upload_po_doc" class="btn btn-primary btn-sm">Upload File</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openUploadModal(id, poNo) {
        document.getElementById('modal_q_id').value = id;
        document.getElementById('modal_po_no').value = poNo;
        var myModal = new bootstrap.Modal(document.getElementById('uploadPOModal'));
        myModal.show();
    }
</script>