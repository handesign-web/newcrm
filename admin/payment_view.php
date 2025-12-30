<?php
$page_title = "Payment Details";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

if (!isset($_GET['id'])) { echo "<script>window.location='payment_list.php';</script>"; exit; }
$id = intval($_GET['id']);

// --- FUNGSI DETEKSI DELIMITER (FIX CSV BERANTAKAN) ---
function detectDelimiter($file) {
    $handle = fopen($file, "r");
    $firstLine = fgets($handle);
    fclose($handle);
    
    $delimiters = [",", ";", "\t", "|"];
    $bestDelimiter = ",";
    $maxCount = 0;

    foreach ($delimiters as $delimiter) {
        $count = substr_count($firstLine, $delimiter);
        if ($count > $maxCount) {
            $maxCount = $count;
            $bestDelimiter = $delimiter;
        }
    }
    return $bestDelimiter;
}

// --- PROSES UPLOAD CSV (SMART IMPORT) ---
if (isset($_POST['upload_sim'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $delimiter = detectDelimiter($file); // Deteksi otomatis

        $handle = fopen($file, "r");
        
        // Skip header row
        fgetcsv($handle, 1000, $delimiter); 
        
        $successCount = 0;
        $conn->begin_transaction(); // Pakai transaksi biar aman

        try {
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                // Bersihkan data dari karakter aneh (Excel BOM, kutip, dll)
                $msisdn = isset($data[0]) ? trim(str_replace("'", "", $data[0])) : '';
                $iccid  = isset($data[1]) ? trim(str_replace("'", "", $data[1])) : '';
                $imsi   = isset($data[2]) ? trim($data[2]) : '';
                $sn     = isset($data[3]) ? trim($data[3]) : '';
                $pkg    = isset($data[4]) ? trim($data[4]) : '';
                $type   = isset($data[5]) ? trim($data[5]) : '';
                
                if(!empty($msisdn)) {
                    $msisdn = $conn->real_escape_string($msisdn);
                    $iccid = $conn->real_escape_string($iccid);
                    $imsi = $conn->real_escape_string($imsi);
                    $sn = $conn->real_escape_string($sn);
                    $pkg = $conn->real_escape_string($pkg);
                    $type = $conn->real_escape_string($type);

                    $sql = "INSERT INTO payment_sim_data (payment_id, msisdn, iccid, imsi, sn, data_package, sim_type) 
                            VALUES ($id, '$msisdn', '$iccid', '$imsi', '$sn', '$pkg', '$type')";
                    $conn->query($sql);
                    $successCount++;
                }
            }
            $conn->commit();
            fclose($handle);
            echo "<script>alert('Berhasil mengimport $successCount data SIM!'); window.location='payment_view.php?id=$id';</script>";
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Terjadi kesalahan saat import: " . $e->getMessage() . "');</script>";
        }
    } else {
        echo "<script>alert('Gagal upload file. Pastikan format CSV.');</script>";
    }
}

// 3. Ambil Data Header Payment
$sql = "SELECT p.*, i.invoice_no, c.company_name, c.pic_name, u.username as created_by_name
        FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = $id";
$pay = $conn->query($sql)->fetch_assoc();

if(!$pay) { echo "<script>alert('Data tidak ditemukan'); window.location='payment_list.php';</script>"; exit; }

// 4. Ambil Data SIM
$sims = $conn->query("SELECT * FROM payment_sim_data WHERE payment_id = $id ORDER BY id ASC");
$totalSims = $sims->num_rows;

$lastUpdateObj = $conn->query("SELECT uploaded_at FROM payment_sim_data WHERE payment_id = $id ORDER BY uploaded_at DESC LIMIT 1")->fetch_object();
$lastUpdate = $lastUpdateObj ? date('d M Y, H:i', strtotime($lastUpdateObj->uploaded_at)) : '-';
?>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Payment Details</h3>
            <p class="text-subtitle text-muted">Detail pembayaran dan inventaris data SIM.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <a href="payment_list.php" class="btn btn-light border shadow-sm"><i class="bi bi-arrow-left me-2"></i> Kembali</a>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="row">
        <div class="col-12 col-lg-4">
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-bold text-primary"><i class="bi bi-receipt-cutoff me-2"></i> Informasi Pembayaran</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-muted">Status</span>
                        <span class="badge bg-success bg-opacity-25 text-success px-3 py-2">PAID / LUNAS</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Invoice Number</small>
                        <span class="fw-bold fs-5 text-dark"><?= $pay['invoice_no'] ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Client</small>
                        <span class="fw-bold"><?= htmlspecialchars($pay['company_name']) ?></span>
                        <div class="small text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($pay['pic_name']) ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block mb-1">Tanggal Bayar</small>
                            <span class="fw-bold"><i class="bi bi-calendar3 me-1"></i> <?= date('d/m/Y', strtotime($pay['payment_date'])) ?></span>
                        </div>
                        <div class="col-6">
                             <small class="text-muted d-block mb-1">Diproses Oleh</small>
                             <span class="fw-bold"><i class="bi bi-person-check me-1"></i> <?= htmlspecialchars($pay['created_by_name']) ?></span>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded mt-2">
                        <small class="text-muted d-block mb-1">Total Pembayaran</small>
                        <h4 class="text-primary mb-0">Rp <?= number_format($pay['amount'], 0,',','.') ?></h4>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-bold text-secondary"><i class="bi bi-image me-2"></i> Bukti Transfer</h6>
                </div>
                <div class="card-body text-center">
                    <?php if(!empty($pay['proof_file']) && file_exists('../uploads/' . $pay['proof_file'])): ?>
                        <?php 
                            $ext = strtolower(pathinfo($pay['proof_file'], PATHINFO_EXTENSION));
                            if($ext == 'pdf'):
                        ?>
                            <div class="py-4">
                                <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 3rem;"></i>
                                <p class="mt-2 text-muted"><?= $pay['proof_file'] ?></p>
                                <a href="../uploads/<?= $pay['proof_file'] ?>" target="_blank" class="btn btn-outline-primary w-100 mt-2">Lihat Dokumen PDF</a>
                            </div>
                        <?php else: ?>
                            <div class="position-relative group-hover">
                                <img src="../uploads/<?= $pay['proof_file'] ?>" class="img-fluid rounded border shadow-sm" style="max-height: 250px; object-fit: cover;">
                                <div class="mt-3">
                                    <a href="../uploads/<?= $pay['proof_file'] ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-zoom-in"></i> Lihat Full Size</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="py-4 text-muted">Tidak ada bukti upload.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-12 col-lg-8">
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 text-white"><i class="bi bi-cloud-upload me-2"></i> Import Data SIM</h6>
                    <span class="badge bg-white text-primary">Excel CSV</span>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-2"><br>
                            <label class="form-label fw-bold">Pilih File CSV</label>
                            <div class="input-group">
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                <button type="submit" name="upload_sim" class="btn btn-primary px-4">
                                    <i class="bi bi-upload me-2"></i> Import Data
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text text-muted small m-0">
                                <i class="bi bi-info-circle"></i> Pastikan urutan kolom Excel: <strong>MSISDN, ICCID, IMSI, SN, Package, Type</strong><br>
                                <span class="text-danger">*Save As: CSV (Comma Delimited) (*.csv)</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-bold text-dark"><i class="bi bi-sim me-2"></i> Data Paket & SIM</h6>
                    <div class="text-end">
                        <span class="badge bg-light text-dark border me-2">Total: <strong><?= $totalSims ?></strong></span>
                        <span class="badge bg-light text-secondary border">Updated: <?= $lastUpdate ?></span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle" id="table1">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th class="px-4">No</th>
                                    <th>MSISDN</th>
                                    <th>ICCID</th>
                                    <th>Data Package</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($totalSims > 0): ?>
                                    <?php $no=1; while($row = $sims->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-4"><?= $no++ ?></td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($row['msisdn']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($row['iccid']) ?>
                                            <div class="small text-muted mt-1">IMSI: <?= htmlspecialchars($row['imsi']) ?> | SN: <?= htmlspecialchars($row['sn']) ?></div>
                                        </td>
                                        <td><span class="badge bg-info bg-opacity-25 text-dark"><?= htmlspecialchars($row['data_package']) ?></span></td>
                                        <td><?= htmlspecialchars($row['sim_type']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <img src="../assets/compiled/svg/no-data.svg" alt="No Data" style="width: 60px; opacity: 0.5;" class="mb-3 d-block mx-auto">
                                            <p class="text-muted mb-0">Belum ada data SIM yang diupload.</p>
                                            <small class="text-muted">Silakan upload file CSV pada form di atas.</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if($totalSims > 10): ?>
                <div class="card-footer bg-white border-top text-center py-3">
                    <small class="text-muted">Showing all <?= $totalSims ?> records</small>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>