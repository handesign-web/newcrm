<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php'; 

// --- 2. INIT FILTER VARIABLES ---
$search    = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client  = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';
$f_status  = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$f_start   = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : '';
$f_end     = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : '';

// Bangun Query WHERE
$where = "1=1";
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND q.quotation_no LIKE '%$safe_search%'";
}
if(!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND q.client_id = $safe_client";
}
if(!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $where .= " AND q.status = '$safe_status'";
}
if(!empty($f_start) && !empty($f_end)) {
    $where .= " AND q.quotation_date BETWEEN '$f_start' AND '$f_end'";
}

// --- 3. LOGIKA EXPORT EXCEL (UPDATED) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT q.*, c.company_name, c.pic_name, u.username 
              FROM quotations q 
              JOIN clients c ON q.client_id = c.id 
              JOIN users u ON q.created_by_user_id = u.id 
              WHERE $where ORDER BY q.created_at DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Quotations_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header Kolom CSV
    fputcsv($output, array('Quotation No', 'Date', 'Client', 'Card Types (Int)', 'PIC', 'Items Count', 'Total Amount', 'Currency', 'Status', 'Created By'));
    
    while($row = $resEx->fetch_assoc()) {
        $qId = $row['id'];
        $calc = $conn->query("SELECT SUM(qty * unit_price) as t, COUNT(*) as c FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc();
        
        // Get Card Types
        $cardsArr = [];
        $resCard = $conn->query("SELECT DISTINCT card_type FROM quotation_items WHERE quotation_id = $qId");
        while($rc = $resCard->fetch_assoc()) if(!empty($rc['card_type'])) $cardsArr[] = $rc['card_type'];
        $cardString = implode(", ", $cardsArr);

        fputcsv($output, array(
            $row['quotation_no'],
            $row['quotation_date'],
            $row['company_name'],
            $cardString,
            $row['pic_name'],
            $calc['c'], 
            $calc['t'], 
            $row['currency'],
            $row['status'],
            $row['username']
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Quotations";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// --- LOGIKA ACTION LAIN ---
// Hapus
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $cek = $conn->query("SELECT status FROM quotations WHERE id=$del_id")->fetch_assoc();
    if(in_array($cek['status'], ['po_received', 'invoiced'])) {
        echo "<script>alert('Gagal: Quotation sudah diproses!'); window.location='quotation_list.php';</script>";
    } else {
        $conn->query("DELETE FROM quotation_items WHERE quotation_id = $del_id");
        if ($conn->query("DELETE FROM quotations WHERE id = $del_id")) {
            echo "<script>alert('Quotation berhasil dihapus!'); window.location='quotation_list.php';</script>";
        }
    }
}
// Update Status
if (isset($_GET['status_id']) && isset($_GET['st'])) {
    $st_id = intval($_GET['status_id']);
    $st_val = $conn->real_escape_string($_GET['st']);
    $conn->query("UPDATE quotations SET status = '$st_val' WHERE id = $st_id");
    echo "<script>window.location='quotation_list.php';</script>";
}
// Process to PO
if (isset($_POST['process_po'])) {
    $q_id = intval($_POST['quotation_id']);
    $po_no = $conn->real_escape_string($_POST['po_number_client']);
    $po_file = null;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (isset($_FILES['po_document']) && $_FILES['po_document']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['po_document']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'png', 'jpeg'])) {
            $fileName = 'PO_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['po_document']['tmp_name'], $uploadDir . $fileName)) {
                $po_file = $fileName;
            }
        }
    }

    if ($po_file) {
        $sql = "UPDATE quotations SET status='po_received', po_number_client='$po_no', po_file_client='$po_file' WHERE id=$q_id";
        if ($conn->query($sql)) {
            echo "<script>alert('Berhasil diproses ke PO Client!'); window.location='po_client_list.php';</script>";
        }
    } else {
        echo "<script>alert('Gagal upload dokumen PO.');</script>";
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
        <div class="col-12 col-md-6 order-md-1 order-last">
            <h3>Quotations</h3>
            <p class="text-subtitle text-muted">Daftar penawaran harga yang telah dibuat.</p>
        </div>
        <div class="col-12 col-md-6 order-md-2 order-first text-end">
            <a href="quotation_form.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-2"></i> Create New</a>
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
                <div class="col-lg-12">
                    <form method="GET" class="row g-2">
                        
                        <div class="col-md-3">
                            <label class="form-label small text-muted">No Quotation</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari Nomor..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Nama Perusahaan</label>
                            <select name="client_id" class="form-select">
                                <option value="">- Semua Client -</option>
                                <?php while($c = $clients->fetch_assoc()): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="">- Semua -</option>
                                <option value="draft" <?= $f_status=='draft'?'selected':'' ?>>Draft</option>
                                <option value="sent" <?= $f_status=='sent'?'selected':'' ?>>Sent</option>
                                <option value="po_received" <?= $f_status=='po_received'?'selected':'' ?>>PO Received</option>
                                <option value="invoiced" <?= $f_status=='invoiced'?'selected':'' ?>>Invoiced</option>
                                <option value="cancel" <?= $f_status=='cancel'?'selected':'' ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted">Rentang Tanggal</label>
                            <div class="input-group">
                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $f_start ?>">
                                <span class="input-group-text">-</span>
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $f_end ?>">
                            </div>
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="col-12 border-top pt-3 d-flex justify-content-between align-items-center">
                    <div>
                        <?php if(!empty($search) || !empty($f_client) || !empty($f_status)): ?>
                            <a href="quotation_list.php" class="text-danger text-decoration-none fw-bold small"><i class="bi bi-x-circle"></i> Reset Filter</a>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($f_status) ?>">
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($f_start) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($f_end) ?>">
                        
                        <button type="submit" name="export_excel" class="btn btn-success text-white btn-sm">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Filtered Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div>
                <table class="table table-hover table-striped align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>Quotation No</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Card Type (Int)</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <?php 
                                $qId = $row['id'];
                                $calc = $conn->query("SELECT SUM(qty * unit_price) as t, COUNT(*) as c FROM quotation_items WHERE quotation_id = $qId")->fetch_assoc();
                                $total = $calc['t'];
                                $countItem = $calc['c'];
                                
                                // Card Types
                                $cardList = [];
                                $resCard = $conn->query("SELECT DISTINCT card_type FROM quotation_items WHERE quotation_id = $qId");
                                while($rc = $resCard->fetch_assoc()) if(!empty($rc['card_type'])) $cardList[] = $rc['card_type'];

                                $st = $row['status'];
                                $bg = 'secondary';
                                if($st=='sent') $bg = 'info text-dark';
                                if($st=='po_received') $bg = 'warning text-dark';
                                if($st=='invoiced') $bg = 'success';
                                if($st=='cancel') $bg = 'danger';
                            ?>
                            <tr>
                                <td class="fw-bold text-primary font-monospace">
                                    <?= $row['quotation_no'] ?>
                                </td>
                                <td><?= date('d M Y', strtotime($row['quotation_date'])) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name']) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-person-circle me-1"></i> <?= $row['username'] ?></small>
                                </td>

                                <td>
                                    <?php if(!empty($cardList)): ?>
                                        <?php foreach($cardList as $ctype): ?>
                                            <span class="badge bg-white text-dark border me-1 mb-1"><?= htmlspecialchars($ctype) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <td><span class="badge bg-light text-dark border"><?= $countItem ?> Items</span></td>
                                <td class="fw-bold text-end">
                                    <small class="text-muted me-1"><?= $row['currency'] ?></small>
                                    <?= number_format($total, 0, ',', '.') ?>
                                </td>
                                <td><span class="badge bg-<?= $bg ?> bg-opacity-75"><?= strtoupper(str_replace('_', ' ', $st)) ?></span></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-boundary="viewport">Action</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                            <li><a class="dropdown-item" href="quotation_print.php?id=<?= $row['id'] ?>" target="_blank"><i class="bi bi-printer me-2"></i> Print PDF</a></li>
                                            
                                            <?php if(!in_array($st, ['po_received', 'invoiced', 'cancel'])): ?>
                                                <li><a class="dropdown-item text-primary" href="quotation_form.php?edit_id=<?= $row['id'] ?>"><i class="bi bi-pencil-square me-2"></i> Edit Quote</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if($st == 'draft'): ?>
                                                <li><a class="dropdown-item text-info" href="?status_id=<?= $row['id'] ?>&st=sent"><i class="bi bi-send me-2"></i> Mark as Sent</a></li>
                                                <?php endif; ?>
                                                <li><button class="dropdown-item text-success fw-bold" onclick="openPOModal(<?= $row['id'] ?>, '<?= $row['quotation_no'] ?>')"><i class="bi bi-file-earmark-check me-2"></i> Process to PO</button></li>
                                                <li><a class="dropdown-item text-danger" href="?status_id=<?= $row['id'] ?>&st=cancel" onclick="return confirm('Batalkan Quotation ini?')"><i class="bi bi-x-circle me-2"></i> Cancel Quote</a></li>
                                            <?php endif; ?>

                                            <?php if($st == 'draft' || $st == 'cancel'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('Hapus permanen?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">Tidak ada data quotation ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($res->num_rows > 20): ?>
            <div class="card-footer bg-white border-top text-center py-3"><small class="text-muted">Menampilkan hasil pencarian</small></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="poModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Process to PO Client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="quotation_id" id="modal_quotation_id">
                <div class="alert alert-light-success border-success"><small>Anda akan memproses Quotation <strong id="modal_q_no"></strong> menjadi PO.</small></div>
                <div class="mb-3"><label class="form-label fw-bold">Client PO Number</label><input type="text" name="po_number_client" class="form-control" required></div>
                <div class="mb-3"><label class="form-label fw-bold">Upload PO Document</label><input type="file" name="po_document" class="form-control" accept=".pdf,.jpg,.png,.jpeg" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" name="process_po" class="btn btn-success">Proses</button></div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openPOModal(id, no) {
        document.getElementById('modal_quotation_id').value = id;
        document.getElementById('modal_q_no').innerText = no;
        var myModal = new bootstrap.Modal(document.getElementById('poModal'));
        myModal.show();
    }
</script>