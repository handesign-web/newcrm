<?php
$page_title = "Delivery List";
include 'includes/header.php';
include 'includes/sidebar.php';
require_once '../config/database.php';

// --- 1. PREPARE FILTER DATA (Dropdown Options) ---
// Mengambil data unik untuk dropdown filter agar dinamis
$opt_projects = $conn->query("SELECT DISTINCT project_name FROM deliveries WHERE project_name IS NOT NULL AND project_name != '' ORDER BY project_name ASC");
$opt_couriers = $conn->query("SELECT DISTINCT courier_name FROM deliveries ORDER BY courier_name ASC");
$opt_receivers = $conn->query("SELECT DISTINCT receiver_name FROM deliveries ORDER BY receiver_name ASC");

// --- 2. HANDLE FILTER LOGIC ---
$search_track = isset($_GET['search_track']) ? $_GET['search_track'] : '';
$filter_project = isset($_GET['filter_project']) ? $_GET['filter_project'] : '';
$filter_courier = isset($_GET['filter_courier']) ? $_GET['filter_courier'] : '';
$filter_receiver = isset($_GET['filter_receiver']) ? $_GET['filter_receiver'] : '';

$where_clause = "WHERE 1=1";

if (!empty($search_track)) {
    $safe_track = $conn->real_escape_string($search_track);
    $where_clause .= " AND tracking_number LIKE '%$safe_track%'";
}

if (!empty($filter_project)) {
    $safe_proj = $conn->real_escape_string($filter_project);
    $where_clause .= " AND project_name = '$safe_proj'";
}

if (!empty($filter_courier)) {
    $safe_cour = $conn->real_escape_string($filter_courier);
    $where_clause .= " AND courier_name = '$safe_cour'";
}

if (!empty($filter_receiver)) {
    $safe_recv = $conn->real_escape_string($filter_receiver);
    $where_clause .= " AND receiver_name = '$safe_recv'";
}

// --- 3. MAIN QUERY ---
$sql = "SELECT * FROM deliveries $where_clause ORDER BY delivery_date DESC";
$result = $conn->query($sql);
?>

<style>
    /* Custom Style untuk Tampilan Lebih Rapi */
    .table-modern thead th {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background-color: #f8f9fa;
        color: #6c757d;
        border-bottom: 1px solid #dee2e6;
        padding: 12px 10px;
    }
    .table-modern tbody td {
        font-size: 0.9rem;
        padding: 10px;
        vertical-align: middle;
        color: #495057;
    }
    .filter-card {
        border: none;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }
    .text-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #adb5bd;
        margin-bottom: 4px;
        display: block;
        text-transform: uppercase;
    }
</style>

<div class="page-heading mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h3 class="mb-1">Delivery Management</h3>
            <p class="text-muted small mb-0">Monitor status pengiriman dan riwayat logistik.</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="delivery_form.php" class="btn btn-primary shadow-sm btn-sm px-3 py-2">
                <i class="bi bi-plus-lg me-2"></i> Input Delivery
            </a>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card filter-card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="delivery_list.php">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="text-label">Search Tracking</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search_track" class="form-control border-start-0" placeholder="Nomor Resi..." value="<?= htmlspecialchars($search_track) ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="text-label">Project</label>
                        <select name="filter_project" class="form-select form-select-sm">
                            <option value="">- All Projects -</option>
                            <?php 
                            if($opt_projects->num_rows > 0){
                                $opt_projects->data_seek(0); // Reset pointer
                                while($p = $opt_projects->fetch_assoc()): 
                            ?>
                                <option value="<?= $p['project_name'] ?>" <?= ($filter_project == $p['project_name']) ? 'selected' : '' ?>>
                                    <?= $p['project_name'] ?>
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="text-label">Courier</label>
                        <select name="filter_courier" class="form-select form-select-sm">
                            <option value="">- All Couriers -</option>
                            <?php 
                            if($opt_couriers->num_rows > 0){
                                $opt_couriers->data_seek(0);
                                while($c = $opt_couriers->fetch_assoc()): 
                            ?>
                                <option value="<?= $c['courier_name'] ?>" <?= ($filter_courier == $c['courier_name']) ? 'selected' : '' ?>>
                                    <?= strtoupper($c['courier_name']) ?>
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="text-label">Receiver</label>
                        <select name="filter_receiver" class="form-select form-select-sm">
                            <option value="">- All Receivers -</option>
                            <?php 
                            if($opt_receivers->num_rows > 0){
                                $opt_receivers->data_seek(0);
                                while($r = $opt_receivers->fetch_assoc()): 
                            ?>
                                <option value="<?= $r['receiver_name'] ?>" <?= ($filter_receiver == $r['receiver_name']) ? 'selected' : '' ?>>
                                    <?= $r['receiver_name'] ?>
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                Filter
                            </button>
                            <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier) || !empty($filter_receiver)): ?>
                                <a href="delivery_list.php" class="btn btn-outline-secondary btn-sm w-100">
                                    Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-modern mb-0 text-nowrap">
                    <thead>
                        <tr>
                            <th class="ps-4">Sent Date</th>
                            <th>Delivered</th>
                            <th>Project</th> 
                            <th>Tracking Info</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Item Name</th>
                            <th>Package</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <?= date('d M Y', strtotime($row['delivery_date'])) ?>
                                </td>
                                
                                <td>
                                    <?php if($row['delivered_date']): ?>
                                        <div class="d-flex align-items-center text-success">
                                            <i class="bi bi-check-circle-fill me-2"></i>
                                            <div>
                                                <div class="fw-bold" style="font-size:0.85rem;"><?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                                                <div class="small text-muted" style="font-size:0.75rem;"><?= date('H:i', strtotime($row['delivered_date'])) ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">In Progress</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if(!empty($row['project_name'])): ?>
                                        <span class="badge bg-info text-dark bg-opacity-10 border border-info">
                                            <i class="bi bi-kanban me-1"></i> <?= htmlspecialchars($row['project_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="#" onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="text-decoration-none fw-bold font-monospace text-primary">
                                            <?= htmlspecialchars($row['tracking_number']) ?>
                                        </a>
                                        <span class="badge bg-secondary text-uppercase mt-1" style="width: fit-content; font-size: 0.65rem;">
                                            <?= htmlspecialchars($row['courier_name']) ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($row['sender_name']) ?></div>
                                    <div class="text-muted small text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($row['sender_company']) ?>">
                                        <?= htmlspecialchars($row['sender_company']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                    <div class="text-muted small text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($row['receiver_company']) ?>">
                                        <?= htmlspecialchars($row['receiver_company']) ?>
                                    </div>
                                </td>
                                
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td>
                                    <?php if(!empty($row['data_package'])): ?>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['data_package']) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold"><?= $row['qty'] ?></td>

                                <td class="text-center">
                                    <div class="btn-group shadow-sm" role="group">
                                        <button class="btn btn-sm btn-outline-primary" title="Lacak Paket" onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" title="Lihat Detail" onclick="viewDetail(<?= $row['id'] ?>)">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    Data tidak ditemukan dengan filter saat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if($result->num_rows > 0): ?>
        <div class="card-footer bg-white border-top py-2">
            <small class="text-muted">Menampilkan hasil data pengiriman terbaru.</small>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2 text-primary"></i> Shipment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="location.reload();"></button> 
            </div>
            <div class="modal-body bg-light" id="trackingResult"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-white">
                <h5 class="modal-title fw-bold">Delivery Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailResult">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
// Logic Lacak Paket (Tidak berubah)
function trackResi(resi, kurir) {
    var myModal = new bootstrap.Modal(document.getElementById('trackingModal'));
    myModal.show();
    
    document.getElementById('trackingResult').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Connecting to Courier API...</p>
        </div>
    `;

    fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('trackingResult').innerHTML = data;
        })
        .catch(err => {
            document.getElementById('trackingResult').innerHTML = '<div class="alert alert-danger">Gagal memuat data tracking.</div>';
        });
}

// Logic View Detail (Tidak berubah)
function viewDetail(id) {
    var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
    myModal.show();

    const formData = new FormData();
    formData.append('id', id);

    fetch('ajax_get_delivery.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailResult').innerHTML = data;
        })
        .catch(err => {
            document.getElementById('detailResult').innerHTML = '<div class="alert alert-danger">Gagal memuat detail data.</div>';
        });
}
</script>

<?php include 'includes/footer.php'; ?>