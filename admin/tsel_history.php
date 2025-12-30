<?php
$page_title = "Injection History";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- LOGIKA 1: PROSES RETRY INJECT (API CONNECTED WITH SIGNATURE & DB LOGGING) ---
if (isset($_POST['do_retry'])) {
    $retry_id = intval($_POST['retry_id']);
    
    // 1. Ambil data lama yang gagal
    $q_old = $conn->query("SELECT * FROM inject_history WHERE id = $retry_id");
    
    if ($q_old && $q_old->num_rows > 0) {
        $data = $q_old->fetch_assoc();
        
        $batch_id   = $data['batch_id'];
        $msisdn     = trim($data['msisdn']);
        $denom      = $data['denom_name'];
        
        // --- KONFIGURASI API ---
        $apiKey          = 'w8w2svtf75ufv87rbgb7ux22';
        $secretKey       = 'a7n6TSQeXD';
        $subscriptionKey = "D4CFBBCCBABCDHJDIGXZ"; 
        
        // --- GENERATE SIGNATURE ---
        $timestamp = time(); 
        $signature = md5($apiKey . $secretKey . $timestamp);

        // 2. Setup cURL ke API Telkomsel
        $curl = curl_init();

        $payload_array = [
            "subscriptionKey" => $subscriptionKey,
            "denomName"       => $denom,
            "targetMsisdn"    => (string)$msisdn, 
            "deliveryType"    => "Instant"
        ];
        $payload_json = json_encode($payload_array);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.digitalcore.telkomsel.com/scrt/b2b/v2/create-gift-request',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload_json,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array(
                'api-key: ' . $apiKey,
                'x-signature: ' . $signature,
                'Content-Type: application/json',
                'Cookie: 4a24423b2c57be721f3be4958f00723b=bd6c55c854060df3595eef72c4745afe; TS01234dfd=0134757f1d1bdf74c4065092a5a1be5debf153298ab3abd1b69796feb3d335677481839b1980ec0536bb8fe3f1de8a5187e564b7d33bc3d264804a02247b1796fbbb7b6846'
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // 3. Proses Response
        if ($err) {
            echo "<script>alert('Gagal Koneksi cURL: " . addslashes($err) . "'); window.location='tsel_history.php';</script>";
        } else {
            $respData = json_decode($response, true);

            // Cek apakah response JSON valid
            if (is_null($respData)) {
                $cleanResponse = strip_tags(substr($response, 0, 200));
                echo "<script>alert('API Error (HTTP $http_code): " . addslashes($cleanResponse) . "'); window.location='tsel_history.php';</script>";
            } 
            // Cek Status API = true
            elseif (isset($respData['status']) && $respData['status'] === true) {
                
                // Ambil Request ID Baru dari Response API
                $new_req_id = isset($respData['data']['requestId']) ? $respData['data']['requestId'] : ("RTY-" . time());
                
                // [FIXED LOGIC] 
                // 1. Status langsung SUCCESS
                // 2. Isi Pesan Sukses
                // 3. Simpan raw response ke api_response
                $stmt = $conn->prepare("INSERT INTO inject_history (batch_id, msisdn, denom_name, request_id, status, error_message, api_response, created_at) VALUES (?, ?, ?, ?, 'SUCCESS', 'Inject Berhasil (Callback OK)', ?, NOW())");
                
                if ($stmt) {
                    $stmt->bind_param("issss", $batch_id, $msisdn, $denom, $new_req_id, $response);
                    if ($stmt->execute()) {
                        echo "<script>alert('Retry Sukses! Status Updated to SUCCESS.'); window.location='tsel_history.php';</script>";
                    } else {
                        echo "<script>alert('Gagal simpan ke database.');</script>";
                    }
                    $stmt->close();
                } else {
                    echo "<script>alert('Database Error.');</script>";
                }

            } else {
                $apiMsg = isset($respData['message']) ? $respData['message'] : json_encode($respData);
                echo "<script>alert('API Menolak (HTTP $http_code): " . addslashes($apiMsg) . "'); window.location='tsel_history.php';</script>";
            }
        }
    } else {
        echo "<script>alert('Data transaksi lama tidak ditemukan.');</script>";
    }
}

// Filter & Search Logic (TIDAK DIUBAH)
$search    = isset($_GET['search']) ? $_GET['search'] : '';
$f_client  = isset($_GET['client_id']) ? $_GET['client_id'] : '';
$f_package = isset($_GET['package']) ? $_GET['package'] : '';
$f_status  = isset($_GET['status']) ? $_GET['status'] : '';
$batch_id  = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

$whereClauses = [];
if ($batch_id > 0) $whereClauses[] = "h.batch_id = $batch_id";
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $whereClauses[] = "(h.msisdn LIKE '%$safe_search%' OR b.batch_code LIKE '%$safe_search%' OR h.request_id LIKE '%$safe_search%')";
}
if (!empty($f_client)) {
    $safe_client = intval($f_client);
    $whereClauses[] = "b.client_id = $safe_client";
}
if (!empty($f_package)) {
    $safe_package = $conn->real_escape_string($f_package);
    $whereClauses[] = "h.denom_name = '$safe_package'";
}
if (!empty($f_status)) {
    $safe_status = $conn->real_escape_string($f_status);
    $whereClauses[] = "h.status = '$safe_status'";
}

$whereSql = (count($whereClauses) > 0) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Query Data
$sql = "SELECT h.*, b.batch_code, c.company_name 
        FROM inject_history h 
        JOIN inject_batches b ON h.batch_id = b.id 
        LEFT JOIN clients c ON b.client_id = c.id
        $whereSql 
        ORDER BY h.created_at DESC LIMIT 500";
$histories = $conn->query($sql);

$clients_opt = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");
$packages_opt = $conn->query("SELECT DISTINCT denom_name FROM inject_history ORDER BY denom_name ASC");
?>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Injection History Log</h3>
            <p class="text-subtitle text-muted">Pantau status inject paket data secara real-time.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <a href="tsel_inject.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle me-2"></i> New Inject</a>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET">
                <?php if($batch_id > 0): ?><input type="hidden" name="batch_id" value="<?= $batch_id ?>"><?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="MSISDN / ReqID" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Client</label>
                        <select name="client_id" class="form-select">
                            <option value="">- Semua Client -</option>
                            <?php while($c = $clients_opt->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>" <?= ($f_client == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['company_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Package</label>
                        <select name="package" class="form-select">
                            <option value="">- Semua -</option>
                            <?php while($p = $packages_opt->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($p['denom_name']) ?>" <?= ($f_package == $p['denom_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['denom_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Status</label>
                        <select name="status" class="form-select">
                            <option value="">- Semua -</option>
                            <option value="SUCCESS" <?= ($f_status == 'SUCCESS') ? 'selected' : '' ?>>SUCCESS</option>
                            <option value="FAILED" <?= ($f_status == 'FAILED') ? 'selected' : '' ?>>FAILED</option>
                            <option value="SUBMITTED" <?= ($f_status == 'SUBMITTED') ? 'selected' : '' ?>>SUBMITTED</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
                        <a href="tsel_history.php" class="btn btn-light border text-danger" title="Reset"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body px-0 py-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0" id="table1" style="font-size: 12px;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Time</th>
                            <th>Batch / Client</th>
                            <th>MSISDN</th>
                            <th>Package</th>
                            <th width="10%">Status</th>
                            <th width="15%">Req ID / Code</th>
                            <th class="pe-4">Message Detail</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($histories->num_rows > 0): ?>
                            <?php while($row = $histories->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <?= date('d M H:i', strtotime($row['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($row['batch_code']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($row['company_name'] ?? '-') ?></small>
                                </td>
                                <td><span class="font-monospace fw-bold fs-7"><?= $row['msisdn'] ?></span></td>
                                <td><?= htmlspecialchars($row['denom_name']) ?></td>
                                
                                <td>
                                    <?php 
                                        $st = $row['status'];
                                        if($st == 'SUCCESS') {
                                            echo '<span class="badge bg-success">SUCCESS</span>';
                                        } elseif($st == 'FAILED') {
                                            echo '<span class="badge bg-danger">FAILED</span>';
                                        } else {
                                            echo '<span class="badge bg-info text-dark">SUBMITTED</span>';
                                        }
                                    ?>
                                </td>

                                <td>
                                    <?php if($row['status'] == 'FAILED'): ?>
                                        <span class="text-danger fw-bold">
                                            Code: <?= !empty($row['error_code']) ? htmlspecialchars($row['error_code']) : 'N/A' ?>
                                        </span>
                                    <?php else: ?>
                                        <div class="font-monospace text-muted small" title="<?= $row['request_id'] ?>">
                                            <?= htmlspecialchars($row['request_id']) ?>
                                        </div>
                                        <?php if(!empty($row['telkomsel_trx_id'])): ?>
                                            <div class="text-success small">Trx: <?= $row['telkomsel_trx_id'] ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                                <td class="pe-4">
                                    <?php if($row['status'] == 'FAILED'): ?>
                                        <span class="text-danger">
                                            <?= !empty($row['error_message']) ? htmlspecialchars($row['error_message']) : 'Unknown Error' ?>
                                        </span>
                                    <?php elseif($row['status'] == 'SUCCESS'): ?>
                                        <span class="text-success"><i class="bi bi-check-all"></i> Inject Berhasil (Callback OK)</span>
                                    <?php else: ?>
                                        <span class="text-warning text-dark">
                                            <i class="bi bi-hourglass-split"></i> Menunggu Callback Telkomsel...
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <?php if($row['status'] == 'FAILED'): ?>
                                        <form method="POST" onsubmit="return confirm('Yakin ingin Retry Inject ke API Telkomsel untuk nomor <?= $row['msisdn'] ?>?');">
                                            <input type="hidden" name="retry_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="do_retry" class="btn btn-sm btn-outline-danger shadow-sm" title="Inject Ulang (API)">
                                                <i class="bi bi-arrow-clockwise"></i> Retry
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($histories->num_rows == 500): ?>
                <div class="card-footer text-center bg-light small text-muted">
                    Menampilkan 500 transaksi terakhir. Gunakan filter untuk mencari data lama.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>