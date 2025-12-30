<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";

// Filter Pencarian (No DO)
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND d.do_number LIKE '%$safe_search%'";
}

// Filter Client
if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT d.*, c.company_name 
              FROM delivery_orders d 
              JOIN payments p ON d.payment_id = p.id 
              JOIN invoices i ON p.invoice_id = i.id
              JOIN quotations q ON i.quotation_id = q.id
              JOIN clients c ON q.client_id = c.id
              WHERE $where
              ORDER BY d.created_at DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=DeliveryOrders_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('DO Number', 'Delivery Date', 'Client', 'Receiver Name', 'Receiver Phone', 'Status', 'Created At'));
    
    while($row = $resEx->fetch_assoc()) {
        fputcsv($output, array(
            $row['do_number'],
            $row['do_date'],
            $row['company_name'],
            $row['pic_name'],
            $row['pic_phone'],
            strtoupper($row['status']),
            $row['created_at']
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Delivery Orders";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Dropdown Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// QUERY DATA TAMPILAN
$sql = "SELECT d.*, c.company_name 
        FROM delivery_orders d 
        JOIN payments p ON d.payment_id = p.id 
        JOIN invoices i ON p.invoice_id = i.id
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        WHERE $where
        ORDER BY d.created_at DESC";
$res = $conn->query($sql);
?>

<style>
    .table-responsive { overflow: visible !important; }
</style>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Delivery Orders</h3>
            <p class="text-subtitle text-muted">Daftar surat jalan pengiriman barang ke client.</p>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-lg-9">
                    <form method="GET" class="row g-2">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Cari No DO..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <select name="client_id" class="form-select">
                                <option value="">- Semua Perusahaan -</option>
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
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-3 border-start d-flex align-items-center justify-content-end">
                    <form method="POST" class="w-100">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="client_id" value="<?= htmlspecialchars($f_client) ?>">
                        
                        <button type="submit" name="export_excel" class="btn btn-success w-100 text-white">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export to Excel
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if(!empty($search) || !empty($f_client)): ?>
                <div class="mt-3 text-center border-top pt-2">
                    <small class="text-muted">Filter aktif.</small> 
                    <a href="delivery_order_list.php" class="text-danger text-decoration-none fw-bold ms-2">Reset Filter</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive" style="overflow:visible;">
                <table class="table table-hover align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>DO Number</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Receiver (PIC)</th>
                            <th>Status</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-warning text-dark font-monospace"><?= $row['do_number'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                        <?= date('d M Y', strtotime($row['do_date'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['company_name']) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-light text-dark me-2">
                                            <span class="avatar-content small"><i class="bi bi-person"></i></span>
                                        </div>
                                        <span><?= htmlspecialchars($row['pic_name']) ?></span>
                                    </div>
                                    <?php if(!empty($row['pic_phone'])): ?>
                                        <small class="text-muted ms-5"><?= htmlspecialchars($row['pic_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $st = $row['status']; 
                                        $bg = ($st == 'sent') ? 'success' : 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $bg ?>"><?= strtoupper($st) ?></span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-boundary="viewport">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                            <li>
                                                <a class="dropdown-item" href="delivery_order_print.php?id=<?= $row['id'] ?>" target="_blank">
                                                    <i class="bi bi-printer me-2 text-danger"></i> Print DO
                                                </a>
                                            </li>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            
                                            <li>
                                                <a class="dropdown-item" href="delivery_order_form.php?edit_id=<?= $row['id'] ?>">
                                                    <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Data
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Tidak ada data Delivery Order ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($res->num_rows > 15): ?>
            <div class="card-footer bg-white border-top text-center py-3">
                <small class="text-muted">Menampilkan hasil pencarian</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>