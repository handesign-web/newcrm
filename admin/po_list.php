<?php
$page_title = "Purchase Orders";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- LOGIKA FILTER DAN PENCARIAN ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$vendor_filter = $_GET['vendor_id'] ?? 'all';

// Ambil daftar Vendor untuk dropdown filter
$vendors_res = $conn->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC");

// Bangun klausa WHERE
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(po.po_number LIKE '%" . $conn->real_escape_string($search) . "%' OR v.company_name LIKE '%" . $conn->real_escape_string($search) . "%')";
}

if ($status_filter !== 'all') {
    $where_clauses[] = "po.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($vendor_filter !== 'all' && is_numeric($vendor_filter)) {
    $where_clauses[] = "po.vendor_id = " . intval($vendor_filter);
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Logic untuk mengambil data PO
$sql = "SELECT po.*, v.company_name as vendor_name, u.username as created_by 
        FROM purchase_orders po
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.created_by_user_id = u.id
        " . $where_sql . "
        ORDER BY po.created_at DESC";
$pos = $conn->query($sql);
?>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Purchase Orders</h3>
            <p class="text-subtitle text-muted">Daftar semua Purchase Order yang dibuat.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <a href="po_form.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-file-earmark-plus me-2"></i> Create New PO
            </a>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="po_list.php" class="row g-3 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label for="search" class="form-label">Search PO/Vendor</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="PO Number / Vendor Name...">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="Draft" <?= $status_filter == 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Submitted" <?= $status_filter == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3 col-lg-3">
                    <label for="vendor_id" class="form-label">Vendor</label>
                    <select class="form-select" id="vendor_id" name="vendor_id">
                        <option value="all" <?= $vendor_filter == 'all' ? 'selected' : '' ?>>Semua Vendor</option>
                        <?php while($vendor = $vendors_res->fetch_assoc()): ?>
                            <option value="<?= $vendor['id'] ?>" <?= $vendor_filter == $vendor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vendor['company_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-4">
                    <button type="submit" class="btn btn-info w-100"><i class="bi bi-funnel me-2"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>PO Number</th>
                            <th>Vendor</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $pos->fetch_assoc()): ?>
                        <tr>
                            <td><span class="fw-bold text-primary"><?= htmlspecialchars($row['po_number']) ?></span></td>
                            <td><?= htmlspecialchars($row['vendor_name'] ?? 'N/A') ?></td>
                            <td><?= date('d M Y', strtotime($row['po_date'])) ?></td>
                            <td>Rp <?= number_format($row['total_amount'], 2, ',', '.') ?></td>
                            <td>
                                <?php 
                                    $status = $row['status'];
                                    $bg = 'secondary';
                                    if($status == 'Submitted') $bg = 'info';
                                    if($status == 'Approved') $bg = 'success';
                                    if($status == 'Rejected') $bg = 'danger';
                                    if($status == 'Draft') $bg = 'secondary';
                                ?>
                                <span class="badge bg-<?= $bg ?>"><?= $status ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['created_by'] ?? 'N/A') ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Action
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="po_form.php?id=<?= $row['id'] ?>">
                                                <i class="bi bi-eye"></i> View/Edit PO
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="po_print.php?id=<?= $row['id'] ?>" target="_blank">
                                                <i class="bi bi-printer"></i> Print PDF
                                            </a>
                                        </li>
                                        </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>