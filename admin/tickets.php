<?php
$page_title = "Manage Tickets";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- LOGIKA FILTER & PENCARIAN ---

$filter_id = isset($_GET['search_id']) ? $_GET['search_id'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_company = isset($_GET['filter_company']) ? $_GET['filter_company'] : '';

// 1. QUERY JOIN TABLE (Tickets + Users)
// Kita gunakan alias 't' untuk tickets dan 'u' untuk users
$sql = "SELECT t.*, u.username as assigned_name 
        FROM tickets t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE 1=1";

// 2. Filter Logic (Gunakan alias t.)
if (!empty($filter_id)) {
    $safe_id = $conn->real_escape_string($filter_id);
    $sql .= " AND t.ticket_code LIKE '%$safe_id%'";
}

if (!empty($filter_status)) {
    $safe_status = $conn->real_escape_string($filter_status);
    $sql .= " AND t.status = '$safe_status'";
}

if (!empty($filter_company)) {
    $safe_company = $conn->real_escape_string($filter_company);
    $sql .= " AND t.company LIKE '%$safe_company%'";
}

// Urutkan
$sql .= " ORDER BY t.created_at DESC";

$result = $conn->query($sql);
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Eksternal Ticket</h3>
                <p class="text-subtitle text-muted">Management pusat tiket masuk.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tickets</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#filterCard">
                <h6 class="mb-0 text-primary fw-bold"><i class="bi bi-funnel-fill"></i> Filter & Pencarian</h6>
                <i class="bi bi-chevron-down text-muted"></i>
            </div>
            <div class="card-body collapse show" id="filterCard">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase">Nomor Ticket</label>
                            <input type="text" name="search_id" class="form-control" placeholder="LFID-..." value="<?= htmlspecialchars($filter_id) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase">Nama Perusahaan</label>
                            <input type="text" name="filter_company" class="form-control" placeholder="PT..." value="<?= htmlspecialchars($filter_company) ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase">Status</label>
                            <select name="filter_status" class="form-select">
                                <option value="">-- Semua Status --</option>
                                <option value="open" <?= $filter_status == 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="progress" <?= $filter_status == 'progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="hold" <?= $filter_status == 'hold' ? 'selected' : '' ?>>Hold</option>
                                <option value="closed" <?= $filter_status == 'closed' ? 'selected' : '' ?>>Closed</option>
                                <option value="canceled" <?= $filter_status == 'canceled' ? 'selected' : '' ?>>Canceled</option>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i> Cari</button>
                            <a href="tickets.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle" id="table1">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="px-4 py-3">ID Ticket</th>
                                <th>Subject & Type</th>
                                <th>Client / Company</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-4 fw-bold text-primary font-monospace" style="font-size: 0.9rem;">
                                        <?= $row['ticket_code'] ?>
                                        <div class="text-muted small fw-normal mt-1">
                                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php 
                                            $typeColor = 'secondary';
                                            if($row['type'] == 'support') $typeColor = 'info';
                                            elseif($row['type'] == 'payment') $typeColor = 'warning';
                                            elseif($row['type'] == 'info') $typeColor = 'primary';
                                        ?>
                                        <span class="badge bg-<?= $typeColor ?> bg-opacity-25 text-<?= $typeColor ?> mb-1" style="font-size: 0.7rem;">
                                            <?= strtoupper($row['type']) ?>
                                        </span>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($row['subject']) ?>">
                                            <?= htmlspecialchars($row['subject']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-light text-dark me-2">
                                                <span class="avatar-content small"><?= strtoupper(substr($row['company'], 0, 1)) ?></span>
                                            </div>
                                            <span class="fw-semibold text-secondary small"><?= htmlspecialchars($row['company']) ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if($row['assigned_name']): ?>
                                            <span class="badge bg-white border text-dark">
                                                <i class="bi bi-person-fill text-primary"></i> <?= htmlspecialchars($row['assigned_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border-0">
                                                <i class="bi bi-dash-circle"></i> Unassigned
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php 
                                            $st = $row['status'];
                                            $badgeClass = 'secondary';
                                            $icon = 'circle';
                                            
                                            if($st == 'open') { $badgeClass = 'success'; $icon = 'envelope-open'; }
                                            elseif($st == 'progress') { $badgeClass = 'warning text-dark'; $icon = 'hourglass-split'; }
                                            elseif($st == 'hold') { $badgeClass = 'info text-dark'; $icon = 'pause-circle'; }
                                            elseif($st == 'closed') { $badgeClass = 'secondary'; $icon = 'check-circle'; }
                                            elseif($st == 'canceled') { $badgeClass = 'danger'; $icon = 'x-circle'; }
                                        ?>
                                        <span class="badge bg-<?= $badgeClass ?> d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-<?= $icon ?>"></i> <?= strtoupper($st) ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <a href="view_ticket.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary shadow-sm" title="View Detail">
                                            Manage <i class="bi bi-arrow-right-short"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <img src="../assets/compiled/svg/no-data.svg" alt="No Data" style="width: 100px; opacity: 0.5;" class="mb-3 d-block mx-auto">
                                        <h6 class="text-secondary">Tidak ada data ticket yang ditemukan.</h6>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if($result && $result->num_rows > 20): ?>
            <div class="card-footer bg-white border-top text-center">
                <small class="text-muted">Showing all records</small>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>