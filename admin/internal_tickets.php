<?php
$page_title = "Internal Tickets";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 0. AMBIL DATA USER LOGIN ---
$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];

// Ambil ID Divisi user saat ini
$uQuery = $conn->query("SELECT division_id FROM users WHERE id = $current_user_id");
$uData  = $uQuery->fetch_assoc();
$current_user_div = $uData['division_id'];

// --- 1. LOGIKA FILTER ---
$search_keyword  = isset($_GET['search']) ? $_GET['search'] : '';
$filter_division = isset($_GET['division']) ? $_GET['division'] : '';
$filter_status   = isset($_GET['status']) ? $_GET['status'] : '';

// --- 2. QUERY UTAMA ---
$sql = "SELECT t.*, u.username, d.name as target_div_name, d.code as target_div_code
        FROM internal_tickets t 
        JOIN users u ON t.user_id = u.id 
        JOIN divisions d ON t.target_division_id = d.id 
        WHERE 1=1";

// --- 3. FILTER PERMISSION ---
if ($current_role != 'admin') {
    if ($current_user_div) {
        $sql .= " AND (t.user_id = $current_user_id OR t.target_division_id = $current_user_div)";
    } else {
        $sql .= " AND t.user_id = $current_user_id";
    }
}

// --- 4. FILTER PENCARIAN ---
if (!empty($search_keyword)) {
    $safe_key = $conn->real_escape_string($search_keyword);
    $sql .= " AND (t.ticket_code LIKE '%$safe_key%' OR t.subject LIKE '%$safe_key%' OR u.username LIKE '%$safe_key%')";
}
if (!empty($filter_division)) {
    $safe_div = intval($filter_division);
    $sql .= " AND t.target_division_id = $safe_div";
}
if (!empty($filter_status)) {
    $safe_stat = $conn->real_escape_string($filter_status);
    $sql .= " AND t.status = '$safe_stat'";
}

$sql .= " ORDER BY t.created_at DESC";
$result = $conn->query($sql);

$div_list = $conn->query("SELECT * FROM divisions");
?>

<style>
    /* Perkecil font tabel secara global */
    .table-compact {
        font-size: 0.9rem;
    }
    .table-compact thead th {
        background-color: #f4f6f8; /* Abu-abu muda header */
        color: #637381;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 16px;
        border-bottom: 1px solid #dfe3e8;
    }
    .table-compact tbody td {
        padding: 10px 16px;
        vertical-align: middle;
        border-bottom: 1px dashed #eff2f5;
        color: #212b36;
    }
    /* Ticket Code Style */
    .ticket-code {
        font-family: 'Consolas', 'Monaco', monospace;
        font-weight: 700;
        color: #435ebe;
    }
    /* Avatar Initials */
    .avatar-initial {
        width: 32px;
        height: 32px;
        background-color: #dfe3e8;
        color: #637381;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 600;
        font-size: 0.8rem;
    }
    /* Status Badges - Pill Style */
    .badge-status {
        padding: 6px 12px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.75rem;
    }
    .badge-open { background-color: #e9fcd4; color: #54d62c; }
    .badge-progress { background-color: #fff7cd; color: #ffc107; }
    .badge-closed { background-color: #f4f6f8; color: #637381; }
    
    /* Input Form Compact */
    .form-control-sm, .form-select-sm, .btn-sm {
        font-size: 0.85rem;
        border-radius: 0.3rem;
    }
</style>

<div class="page-heading mb-4">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Internal Tickets</h3>
            <p class="text-muted small mb-0">Kelola tiket internal dan permintaan antar divisi.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <a href="internal_create.php" class="btn btn-primary btn-sm shadow-sm px-3">
                <i class="bi bi-plus-lg me-1"></i> Buat Ticket
            </a>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3 px-4">
            <form method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Search Keyword</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari ID, Subject, atau User..." value="<?= htmlspecialchars($search_keyword) ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Target Divisi</label>
                        <select name="division" class="form-select form-select-sm">
                            <option value="">- Semua Divisi -</option>
                            <?php while($d = $div_list->fetch_assoc()): ?>
                                <option value="<?= $d['id'] ?>" <?= ($filter_division == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Status Tiket</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">- Semua Status -</option>
                            <option value="open" <?= ($filter_status == 'open') ? 'selected' : '' ?>>OPEN</option>
                            <option value="progress" <?= ($filter_status == 'progress') ? 'selected' : '' ?>>PROGRESS</option>
                            <option value="closed" <?= ($filter_status == 'closed') ? 'selected' : '' ?>>CLOSED</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                            <a href="internal_tickets.php" class="btn btn-light btn-sm border"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-compact table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID Ticket</th>
                            <th>Subject</th>
                            <th>From (User)</th>
                            <th>To (Divisi)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="ticket-code"><?= $row['ticket_code'] ?></div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                    </div>
                                </td>

                                <td style="max-width: 300px;">
                                    <div class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars($row['subject']) ?>">
                                        <?= htmlspecialchars($row['subject']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-initial me-2">
                                            <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold small <?= ($row['username'] == $_SESSION['username']) ? 'text-primary' : 'text-dark' ?>">
                                                <?= htmlspecialchars($row['username']) ?>
                                            </div>
                                            <?php if($row['username'] == $_SESSION['username']): ?>
                                                <div class="text-muted" style="font-size: 0.7rem;">(Anda)</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-light text-secondary border fw-normal">
                                        <i class="bi bi-building me-1"></i> <?= htmlspecialchars($row['target_div_name']) ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <?php 
                                        $st = $row['status'];
                                        $badgeClass = 'badge-closed';
                                        if($st == 'open') $badgeClass = 'badge-open';
                                        elseif($st == 'progress') $badgeClass = 'badge-progress';
                                    ?>
                                    <span class="badge badge-status <?= $badgeClass ?>">
                                        <?= strtoupper($st) ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <a href="internal_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-3" style="font-size: 0.8rem;">
                                        Detail <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 opacity-25"></i>
                                    <p class="mt-2 small">Tidak ada data tiket ditemukan.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if($result->num_rows > 10): ?>
        <div class="card-footer bg-white border-top py-2 text-center text-muted small" style="font-size: 0.8rem;">
            End of list
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>