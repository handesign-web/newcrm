<?php
$page_title = "Upload Data Inject";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';
include 'includes/SimpleXLSX.php'; // Load Helper Excel

$logs = []; 

if (isset($_POST['upload_data'])) {
    if (isset($_FILES['data_file']) && $_FILES['data_file']['error'] == 0) {
        
        $fileTmp = $_FILES['data_file']['tmp_name'];
        $fileName = $_FILES['data_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $rows = [];

        // --- 1. BACA FILE ---
        if ($ext === 'xlsx') {
            $rows = SimpleXLSX::parse($fileTmp);
            if(!empty($rows)) array_shift($rows); // Hapus Header
        } elseif ($ext === 'csv') {
            $handle = fopen($fileTmp, "r");
            $firstLine = fgets($handle);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            rewind($handle);
            fgetcsv($handle, 1000, $delimiter); // Skip Header
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        } else {
            $logs[] = ['status' => 'error', 'msg' => 'Format file harus .xlsx atau .csv'];
        }

        // --- 2. PROSES DATA ---
        $success = 0;
        $failed = 0;
        $skipped = 0; // Counter untuk duplikat

        if (!empty($rows)) {
            foreach ($rows as $index => $data) {
                $lineNum = $index + 2; 
                
                $clientName = trim($data[0] ?? '');
                $batchName  = trim($data[1] ?? '');
                $rawMsisdn  = trim($data[2] ?? '');
                $pkgName    = trim($data[3] ?? '');

                // Sanitasi MSISDN
                $msisdn = preg_replace('/[^0-9]/', '', $rawMsisdn);
                if (substr($msisdn, 0, 1) === '0') {
                    $msisdn = '62' . substr($msisdn, 1);
                }

                if (empty($clientName) || empty($msisdn)) {
                    $logs[] = ['status' => 'warning', 'msg' => "Baris $lineNum: Client/MSISDN kosong."];
                    $failed++; continue;
                }

                // A. Cari Client ID
                $cQ = $conn->query("SELECT id FROM clients WHERE company_name LIKE '%" . $conn->real_escape_string($clientName) . "%' LIMIT 1");
                
                if ($cQ->num_rows > 0) {
                    $clientId = $cQ->fetch_assoc()['id'];
                    
                    // --- B. CEK DUPLIKASI (FITUR BARU) ---
                    // Cek apakah data sama persis sudah ada di staging
                    $checkDup = $conn->query("SELECT id FROM inject_staging WHERE client_id = $clientId AND batch_name = '$batchName' AND msisdn = '$msisdn'");
                    
                    if ($checkDup->num_rows > 0) {
                        // Jika sudah ada, skip
                        $skipped++;
                        // Opsional: Aktifkan baris bawah ini jika ingin log setiap skip (bisa bikin log penuh)
                        // $logs[] = ['status' => 'info', 'msg' => "Baris $lineNum: Skip duplikat ($msisdn)"];
                        continue; 
                    }

                    // C. Cari Denom ID
                    $denomId = '';
                    if(!empty($pkgName)) {
                        $dQ = $conn->query("SELECT denom_id FROM tsel_packages WHERE denom_name LIKE '%" . $conn->real_escape_string($pkgName) . "%' LIMIT 1");
                        if($dQ->num_rows > 0) {
                            $denomId = $dQ->fetch_assoc()['denom_id'];
                        }
                    }

                    // D. Insert Data Baru
                    $stmt = $conn->prepare("INSERT INTO inject_staging (client_id, batch_name, msisdn, package_name, denom_id, status) VALUES (?, ?, ?, ?, ?, 'READY')");
                    $stmt->bind_param("issss", $clientId, $batchName, $msisdn, $pkgName, $denomId);
                    
                    if ($stmt->execute()) {
                        $success++;
                    } else {
                        $logs[] = ['status' => 'error', 'msg' => "Baris $lineNum: Gagal Simpan DB - " . $stmt->error];
                        $failed++;
                    }
                } else {
                    $logs[] = ['status' => 'error', 'msg' => "Baris $lineNum: Client '$clientName' tidak ditemukan."];
                    $failed++;
                }
            }
            
            // Laporan Akhir
            if ($success > 0 || $skipped > 0) {
                $msg = "<strong>Upload Selesai!</strong><br>";
                $msg .= "Sukses Masuk: $success data.<br>";
                if ($skipped > 0) $msg .= "Dilewati (Duplikat): $skipped data.<br>";
                $msg .= "Gagal: $failed data.";
                
                $statusClass = ($failed == 0) ? 'success' : 'warning';
                $logs[] = ['status' => $statusClass, 'msg' => $msg];
            }
        } else {
            if(empty($logs)) $logs[] = ['status' => 'warning', 'msg' => 'File kosong atau tidak terbaca.'];
        }
    } else {
        $logs[] = ['status' => 'error', 'msg' => 'Gagal upload file.'];
    }
}
?>

<div class="page-heading">
    <h3>Upload Data Inject</h3>
    <p class="text-subtitle text-muted">Support file Excel (.xlsx) dan CSV. Data duplikat (Client + Batch + MSISDN sama) akan otomatis dilewati.</p>
</div>

<div class="page-content">
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">Form Upload</div>
                <div class="card-body">
                    
                    <div class="alert alert-light border">
                        <strong><i class="bi bi-table me-2"></i>Format Kolom Excel/CSV:</strong><br>
                        <small>
                        1. Nama Client (Sesuai Database)<br>
                        2. Nama Batch (Contoh: BATCH-001)<br>
                        3. MSISDN (Contoh: 62811...)<br>
                        4. Nama Paket (Contoh: 500MB)
                        </small>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih File</label>
                            <input type="file" name="data_file" class="form-control" accept=".csv, .xlsx" required>
                            <div class="form-text">Gunakan file <strong>.xlsx</strong> agar lebih akurat.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="upload_data" class="btn btn-primary">
                                <i class="bi bi-cloud-upload-fill me-2"></i> Upload & Validasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <?php if (!empty($logs)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Hasil Upload</strong></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach($logs as $log): ?>
                            <li class="list-group-item list-group-item-<?= $log['status'] == 'error' ? 'danger' : ($log['status'] == 'success' ? 'success' : ($log['status'] == 'info' ? 'info' : 'warning')) ?>">
                                <i class="bi bi-<?= $log['status'] == 'success' ? 'check-circle' : 'info-circle' ?> me-2"></i>
                                <?= $log['msg'] ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php 
                        // Cek apakah ada data sukses (variabel $success didefinisikan di logic atas)
                        // Namun karena $success scope-nya lokal di if, kita cek log terakhir atau redirect user manual
                        if (isset($success) && $success > 0): 
                    ?>
                        <div class="p-3 text-center border-top">
                            <a href="tsel_inject.php" class="btn btn-success w-100 fw-bold">
                                Lanjut ke Proses Inject <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>