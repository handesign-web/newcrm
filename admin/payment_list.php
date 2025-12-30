<?php
// --- 1. LOAD CONFIG DULUAN ---
include '../config/functions.php';

// --- 2. INIT FILTER VARIABLES ---
$search   = isset($_REQUEST['search']) ? $_REQUEST['search'] : '';
$f_client = isset($_REQUEST['client_id']) ? $_REQUEST['client_id'] : '';

// Bangun Query WHERE
$where = "1=1";
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND i.invoice_no LIKE '%$safe_search%'";
}
if(!empty($f_client)) {
    $safe_client = intval($f_client);
    $where .= " AND c.id = $safe_client";
}

// --- 3. LOGIKA EXPORT EXCEL (CSV) ---
if (isset($_POST['export_excel'])) {
    if (ob_get_length()) ob_end_clean();
    
    $sqlEx = "SELECT p.*, i.invoice_no, i.payment_method, c.company_name, u.username as admin_name
              FROM payments p 
              JOIN invoices i ON p.invoice_id = i.id 
              JOIN quotations q ON i.quotation_id = q.id 
              JOIN clients c ON q.client_id = c.id
              LEFT JOIN users u ON p.created_by = u.id
              WHERE $where
              ORDER BY p.payment_date DESC";
    $resEx = $conn->query($sqlEx);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Payments_Data_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, array('Payment Date', 'Invoice No', 'Client', 'Amount', 'Method', 'SIM Data Count', 'Proof Status', 'Processed By'));
    
    while($row = $resEx->fetch_assoc()) {
        // Hitung jumlah SIM
        $pid = $row['id'];
        $countSim = $conn->query("SELECT COUNT(*) as t FROM payment_sim_data WHERE payment_id=$pid")->fetch_assoc()['t'];
        
        fputcsv($output, array(
            $row['payment_date'],
            $row['invoice_no'],
            $row['company_name'],
            $row['amount'],
            $row['payment_method'] ? $row['payment_method'] : 'Transfer',
            $countSim . " Data",
            $row['proof_file'] ? 'Uploaded' : 'Pending',
            $row['admin_name'] ?? 'System'
        ));
    }
    fclose($output);
    exit();
}

// --- 4. LOAD TAMPILAN HTML ---
$page_title = "Payment List";
include 'includes/header.php';
include 'includes/sidebar.php';

// Ambil List Client untuk Filter
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// QUERY DATA TAMPILAN
$sql = "SELECT p.*, i.invoice_no, i.payment_method, c.company_name, u.username as admin_name
        FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        JOIN quotations q ON i.quotation_id = q.id 
        JOIN clients c ON q.client_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE $where
        ORDER BY p.payment_date DESC";
$res = $conn->query($sql);
?>

<style>
    .table-responsive { overflow: visible !important; }
</style>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Payment List</h3>
            <p class="text-subtitle text-muted">Riwayat pembayaran yang telah diverifikasi.</p>
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
                                <input type="text" name="search" class="form-control" placeholder="Cari No Invoice..." value="<?= htmlspecialchars($search) ?>">
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
                    <a href="payment_list.php" class="text-danger text-decoration-none fw-bold ms-2">Reset Filter</a>
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
                            <th>Payment Date</th>
                            <th>Invoice Ref</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>SIM Data</th>
                            <th>Proof</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar3 text-muted me-2"></i>
                                        <?= date('d M Y', strtotime($row['payment_date'])) ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">By: <?= $row['admin_name'] ?? 'System' ?></small>
                                </td>
                                
                                <td>
                                    <span class="fw-bold text-primary font-monospace"><?= $row['invoice_no'] ?></span>
                                </td>
                                
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name']) ?></div>
                                </td>
                                
                                <td>
                                    <span class="fw-bold text-success">Rp <?= number_format($row['amount'], 0, ',', '.') ?></span>
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= $row['payment_method'] ? $row['payment_method'] : 'Transfer' ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php 
                                        // Hitung jumlah data SIM
                                        $pid = $row['id'];
                                        $countSim = $conn->query("SELECT COUNT(*) as t FROM payment_sim_data WHERE payment_id=$pid")->fetch_assoc()['t'];
                                        if($countSim > 0) {
                                            echo "<span class='badge bg-info bg-opacity-25 text-primary'><i class='bi bi-sim'></i> $countSim Data</span>";
                                        } else {
                                            echo "<span class='badge bg-secondary bg-opacity-25 text-dark'>Empty</span>";
                                        }
                                    ?>
                                </td>

                                <td>
                                    <a href="../uploads/<?= $row['proof_file'] ?>" target="_blank" class="btn btn-sm btn-light border text-secondary" title="Lihat Bukti">
                                        <i class="bi bi-image"></i>
                                    </a>
                                </td>
                                
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-boundary="viewport">
                                            Action
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                            <li>
                                                <a class="dropdown-item text-primary" href="payment_view.php?id=<?= $row['id'] ?>">
                                                    <i class="bi bi-eye me-2"></i> View & Upload SIM
                                                </a>
                                            </li>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            
                                            <li>
                                                <a class="dropdown-item text-warning fw-bold" href="delivery_order_form.php?payment_id=<?= $row['id'] ?>">
                                                    <i class="bi bi-truck me-2"></i> Create DO
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Tidak ada data pembayaran ditemukan.
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