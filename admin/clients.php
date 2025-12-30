<?php
$page_title = "Client List";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 1. LOGIKA IMPORT CSV (EXCEL) ---
if (isset($_POST['import_clients'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        // Skip header
        fgetcsv($handle, 1000, ","); 
        
        $success = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Format CSV: Company, Address, PIC Name, PIC Phone, Subscription (Daily/Monthly/Yearly), Status (Trial/Subscribe/...)
            $comp = $conn->real_escape_string($data[0] ?? '');
            $addr = $conn->real_escape_string($data[1] ?? '');
            $pic  = $conn->real_escape_string($data[2] ?? '');
            $phone= $conn->real_escape_string($data[3] ?? '');
            $sub  = $conn->real_escape_string($data[4] ?? 'Monthly');
            $stat = $conn->real_escape_string($data[5] ?? 'Trial');
            
            if(!empty($comp)) {
                $sql = "INSERT INTO clients (company_name, address, pic_name, pic_phone, subscription_type, status) 
                        VALUES ('$comp', '$addr', '$pic', '$phone', '$sub', '$stat')";
                if($conn->query($sql)) $success++;
            }
        }
        fclose($handle);
        echo "<script>alert('Berhasil import $success data client!'); window.location='clients.php';</script>";
    } else {
        echo "<script>alert('Gagal upload file.');</script>";
    }
}

// --- 2. AMBIL DATA SALES PERSON (Untuk Dropdown) ---
$sales_people = [];
$sqlSales = "SELECT u.id, u.username FROM users u 
             JOIN divisions d ON u.division_id = d.id 
             WHERE d.name = 'Business Development' OR d.code = 'BD'";
$resSales = $conn->query($sqlSales);
while($row = $resSales->fetch_assoc()) { $sales_people[] = $row; }

// --- 3. LOGIKA SIMPAN (ADD / EDIT - Fitur Lama) ---
if (isset($_POST['save_client'])) {
    $is_edit = !empty($_POST['client_id']);
    $id = $is_edit ? intval($_POST['client_id']) : 0;

    $comp = $conn->real_escape_string($_POST['company_name']);
    $addr = $conn->real_escape_string($_POST['address']);
    $pic  = $conn->real_escape_string($_POST['pic_name']);
    $phone= $conn->real_escape_string($_POST['pic_phone']);
    $sub_type = $conn->real_escape_string($_POST['subscription_type']);
    $status   = $conn->real_escape_string($_POST['status']);
    $sales_id = !empty($_POST['sales_person_id']) ? intval($_POST['sales_person_id']) : "NULL";

    // Upload Logic (Sama seperti sebelumnya)
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $nda_sql = "";
    if (isset($_FILES['nda_file']) && $_FILES['nda_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['nda_file']['name'], PATHINFO_EXTENSION));
        $newName = 'NDA_' . time() . '_' . rand(100,999) . '.' . $ext;
        if (move_uploaded_file($_FILES['nda_file']['tmp_name'], $uploadDir . $newName)) {
            $nda_sql = $is_edit ? ", nda_file='$newName'" : $newName;
        }
    } else { $nda_sql = $is_edit ? "" : "NULL"; }

    $contract_sql = "";
    if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION));
        $newName = 'CONT_' . time() . '_' . rand(100,999) . '.' . $ext;
        if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $uploadDir . $newName)) {
            $contract_sql = $is_edit ? ", contract_file='$newName'" : $newName;
        }
    } else { $contract_sql = $is_edit ? "" : "NULL"; }

    if ($is_edit) {
        $sql = "UPDATE clients SET 
                company_name='$comp', address='$addr', pic_name='$pic', pic_phone='$phone',
                subscription_type='$sub_type', status='$status', sales_person_id=$sales_id
                $nda_sql $contract_sql
                WHERE id=$id";
    } else {
        $nda_val = ($nda_sql == "NULL") ? "NULL" : "'$nda_sql'";
        $cont_val = ($contract_sql == "NULL") ? "NULL" : "'$contract_sql'";
        $sql = "INSERT INTO clients (company_name, address, pic_name, pic_phone, subscription_type, status, sales_person_id, nda_file, contract_file) 
                VALUES ('$comp', '$addr', '$pic', '$phone', '$sub_type', '$status', $sales_id, $nda_val, $cont_val)";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('Data Client Berhasil Disimpan!'); window.location='clients.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// --- 4. FILTER DATA ---
$search = isset($_GET['search']) ? $_GET['search'] : '';
$f_sub  = isset($_GET['subscription']) ? $_GET['subscription'] : '';
$f_stat = isset($_GET['status']) ? $_GET['status'] : '';
$f_sales= isset($_GET['sales']) ? $_GET['sales'] : '';

$where = "1=1";
if(!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (company_name LIKE '%$safe_search%' OR pic_name LIKE '%$safe_search%')";
}
if(!empty($f_sub)) {
    $safe_sub = $conn->real_escape_string($f_sub);
    $where .= " AND subscription_type = '$safe_sub'";
}
if(!empty($f_stat)) {
    $safe_stat = $conn->real_escape_string($f_stat);
    $where .= " AND status = '$safe_stat'";
}
if(!empty($f_sales)) {
    $safe_sales = intval($f_sales);
    $where .= " AND sales_person_id = $safe_sales";
}

// --- 5. QUERY DATA UTAMA ---
$sqlClients = "SELECT c.*, u.username as sales_name 
               FROM clients c 
               LEFT JOIN users u ON c.sales_person_id = u.id 
               WHERE $where
               ORDER BY c.created_at DESC";
$clients = $conn->query($sqlClients);
?>

<style>
    .table-responsive { overflow: visible !important; }
</style>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Client List</h3>
            <p class="text-subtitle text-muted">Database pelanggan dan dokumen legalitas.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <button class="btn btn-success shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i> Import Excel
            </button>
            <button class="btn btn-primary shadow-sm" onclick="openModal()">
                <i class="bi bi-person-plus-fill me-2"></i> Add New Client
            </button>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET">
                <div class="row g-2">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari Perusahaan / PIC..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="subscription" class="form-select">
                            <option value="">- Subscription -</option>
                            <option value="Daily" <?= $f_sub=='Daily'?'selected':'' ?>>Daily</option>
                            <option value="Monthly" <?= $f_sub=='Monthly'?'selected':'' ?>>Monthly</option>
                            <option value="Yearly" <?= $f_sub=='Yearly'?'selected':'' ?>>Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">- Status -</option>
                            <option value="Trial" <?= $f_stat=='Trial'?'selected':'' ?>>Trial</option>
                            <option value="Subscribe" <?= $f_stat=='Subscribe'?'selected':'' ?>>Subscribe</option>
                            <option value="Unsubscribe" <?= $f_stat=='Unsubscribe'?'selected':'' ?>>Unsubscribe</option>
                            <option value="Cancel" <?= $f_stat=='Cancel'?'selected':'' ?>>Cancel</option>
                            <option value="Hold" <?= $f_stat=='Hold'?'selected':'' ?>>Hold</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="sales" class="form-select">
                            <option value="">- Sales Person -</option>
                            <?php foreach($sales_people as $sp): ?>
                                <option value="<?= $sp['id'] ?>" <?= $f_sales==$sp['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($sp['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
                <?php if(!empty($search) || !empty($f_sub) || !empty($f_stat) || !empty($f_sales)): ?>
                    <div class="mt-2 text-center">
                        <a href="clients.php" class="text-danger small text-decoration-none">Reset Filter</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive" style="overflow:visible;">
                <table class="table table-hover align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>Company Info</th>
                            <th>Subscription</th>
                            <th>Status</th>
                            <th>Sales Person</th>
                            <th class="text-center">Docs</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($clients->num_rows > 0): ?>
                            <?php while($row = $clients->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['company_name']) ?></span>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($row['pic_name']) ?> 
                                        <span class="mx-1">|</span> 
                                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($row['pic_phone']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $row['subscription_type'] ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $st = $row['status'];
                                        $bg = 'secondary';
                                        if($st=='Subscribe') $bg='success';
                                        if($st=='Trial') $bg='info text-dark';
                                        if($st=='Hold') $bg='warning text-dark';
                                        if($st=='Cancel' || $st=='Unsubscribe') $bg='danger';
                                    ?>
                                    <span class="badge bg-<?= $bg ?>"><?= strtoupper($st) ?></span>
                                </td>
                                <td>
                                    <?= $row['sales_name'] ? $row['sales_name'] : '<span class="text-muted fst-italic">-</span>' ?>
                                </td>
                                
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-3">
                                        <?php if($row['nda_file']): ?>
                                            <a href="../uploads/<?= $row['nda_file'] ?>" target="_blank" title="View NDA" class="text-success fs-4">
                                                <i class="bi bi-file-earmark-lock-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-secondary opacity-25 fs-4" title="NDA Missing"><i class="bi bi-file-earmark-lock"></i></span>
                                        <?php endif; ?>

                                        <?php if($row['contract_file']): ?>
                                            <a href="../uploads/<?= $row['contract_file'] ?>" target="_blank" title="View Contract" class="text-success fs-4">
                                                <i class="bi bi-file-earmark-check-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-secondary opacity-25 fs-4" title="Contract Missing"><i class="bi bi-file-earmark-check"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editClient(<?= json_encode($row) ?>)'>
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Tidak ada data client ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="client_id" id="client_id">
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Company Name</label>
                        <input type="text" name="company_name" id="company_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">PIC Name</label>
                        <input type="text" name="pic_name" id="pic_name" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">PIC Phone</label>
                        <input type="text" name="pic_phone" id="pic_phone" class="form-control">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="col-12"><hr></div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Subscription Type</label>
                        <select name="subscription_type" id="subscription_type" class="form-select">
                            <option value="Daily">Daily</option>
                            <option value="Monthly" selected>Monthly</option>
                            <option value="Yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Client Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="Trial">Trial</option>
                            <option value="Subscribe">Subscribe</option>
                            <option value="Unsubscribe">Unsubscribe</option>
                            <option value="Cancel">Cancel</option>
                            <option value="Hold">Hold</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Sales Person (BD)</label>
                        <select name="sales_person_id" id="sales_person_id" class="form-select">
                            <option value="">-- Select Sales --</option>
                            <?php foreach($sales_people as $sp): ?>
                                <option value="<?= $sp['id'] ?>"><?= $sp['username'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12"><hr></div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Upload NDA</label>
                        <input type="file" name="nda_file" class="form-control">
                        <small class="text-muted" id="nda_status"></small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Upload Contract</label>
                        <input type="file" name="contract_file" class="form-control">
                        <small class="text-muted" id="contract_status"></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="save_client" class="btn btn-primary">Save Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Import Clients from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Pilih File CSV</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                </div>
                <div class="alert alert-light small">
                    <strong>Format CSV:</strong><br>
                    Company Name, Address, PIC Name, PIC Phone, Subscription (Monthly/Yearly), Status (Trial/Subscribe)
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="import_clients" class="btn btn-success">Import Data</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    var myModal = new bootstrap.Modal(document.getElementById('clientModal'));

    function openModal() {
        document.getElementById('client_id').value = '';
        document.getElementById('company_name').value = '';
        document.getElementById('pic_name').value = '';
        document.getElementById('pic_phone').value = '';
        document.getElementById('address').value = '';
        document.getElementById('subscription_type').value = 'Monthly';
        document.getElementById('status').value = 'Trial';
        document.getElementById('sales_person_id').value = '';
        document.getElementById('nda_status').innerHTML = '';
        document.getElementById('contract_status').innerHTML = '';
        document.getElementById('modalTitle').innerText = "Add New Client";
        myModal.show();
    }

    function editClient(data) {
        document.getElementById('client_id').value = data.id;
        document.getElementById('company_name').value = data.company_name;
        document.getElementById('pic_name').value = data.pic_name;
        document.getElementById('pic_phone').value = data.pic_phone;
        document.getElementById('address').value = data.address;
        document.getElementById('subscription_type').value = data.subscription_type;
        document.getElementById('status').value = data.status;
        document.getElementById('sales_person_id').value = data.sales_person_id;

        if(data.nda_file) document.getElementById('nda_status').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Existing File';
        else document.getElementById('nda_status').innerHTML = '<i class="bi bi-x-circle text-secondary"></i> No file';

        if(data.contract_file) document.getElementById('contract_status').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Existing File';
        else document.getElementById('contract_status').innerHTML = '<i class="bi bi-x-circle text-secondary"></i> No file';

        document.getElementById('modalTitle').innerText = "Edit Client Data";
        myModal.show();
    }
</script>