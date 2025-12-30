<?php
// ajax_track_delivery.php

// 1. Load Koneksi Database (Penting untuk Update Status)
require_once '../config/database.php';

if (!isset($_GET['resi']) || !isset($_GET['kurir'])) {
    echo "Invalid Request";
    exit;
}

// Sanitasi input untuk keamanan Database
$resi = $conn->real_escape_string($_GET['resi']);
$kurir = $_GET['kurir'];
$apiKey = '485762cb-0ade-41d3-afad-6da124ff90cb'; // API Key Anda

// 2. Panggil API KlikResi
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://klikresi.com/api/trackings/$resi/couriers/$kurir",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    "x-api-key: $apiKey"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "<div class='alert alert-danger'>cURL Error: $err</div>";
    exit;
}

// 3. Decode JSON
$result = json_decode($response, true);

// Cek apakah data ada
if (!isset($result['data'])) {
    echo "<div class='alert alert-warning text-center p-4'>
            <i class='bi bi-exclamation-circle fs-1'></i><br>
            <strong>Data tidak ditemukan.</strong><br>
            Pastikan nomor resi dan kurir benar.<br>
            <small class='text-muted'>Response: ".htmlspecialchars($response)."</small>
          </div>";
    exit;
}

$data = $result['data'];
$statusColor = ($data['status'] == 'Delivered') ? 'text-success' : 'text-primary';

// =================================================================================
// 4. LOGIC UPDATE DATABASE (FITUR BARU)
// =================================================================================
// Jika status paket sudah "Delivered", kita ambil tanggalnya dan update ke database
if ($data['status'] == 'Delivered') {
    $deliveredDate = null;
    
    // Cari tanggal "Delivered" yang tepat dari history
    if (isset($data['histories']) && is_array($data['histories'])) {
        foreach ($data['histories'] as $history) {
            // Case insensitive check
            if (stripos($history['status'], 'delivered') !== false) {
                // Format tanggal agar sesuai dengan DATETIME MySQL (Y-m-d H:i:s)
                $deliveredDate = date('Y-m-d H:i:s', strtotime($history['date']));
                break; // Ambil yang paling atas (terbaru) atau yang pertama ketemu
            }
        }
    }

    // Jika tanggal ditemukan, lakukan update query
    if ($deliveredDate) {
        $updateSql = "UPDATE deliveries SET 
                      status = 'Delivered', 
                      delivered_date = '$deliveredDate', 
                      last_tracking_update = NOW() 
                      WHERE tracking_number = '$resi'";
        
        // Eksekusi query (Silent update, user tidak perlu tahu proses ini di UI)
        $conn->query($updateSql);
    }
}
// =================================================================================
?>

<style>
    .track-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        padding: 20px;
    }
    .timeline {
        position: relative;
        padding-left: 30px;
        border-left: 2px solid #e9ecef;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }
    .timeline-dot {
        position: absolute;
        left: -36px;
        top: 0;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid #ced4da;
    }
    .timeline-item:first-child .timeline-dot {
        border-color: #435ebe; /* Primary Color */
        background: #435ebe;
    }
    .timeline-date {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 4px;
    }
    .timeline-status {
        font-weight: bold;
        color: #435ebe;
    }
    .route-arrow {
        font-size: 1.5rem;
        color: #ced4da;
        vertical-align: middle;
        margin: 0 15px;
    }
</style>

<div class="track-card">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
        <div>
            <div class="text-muted small text-uppercase fw-bold">Resi: <?= $resi ?> | Courier: <?= strtoupper($kurir) ?></div>
        </div>
    </div>
    
    <div class="row align-items-center">
        <div class="col-12 mb-3">
            <div class="text-muted small text-uppercase">Current Status</div>
            <h3 class="<?= $statusColor ?> fw-bold mb-0"><?= $data['status'] ?></h3>
        </div>
        
        <div class="col-md-5">
            <div class="text-muted small text-uppercase">Origin</div>
            <h6 class="fw-bold text-primary mb-1"><?= $data['origin']['contact_name'] ?? '-' ?></h6>
            <div class="small text-muted"><?= $data['origin']['address'] ?? '-' ?></div>
        </div>
        
        <div class="col-md-2 text-center d-none d-md-block">
            <i class="bi bi-arrow-right route-arrow"></i>
        </div>
        
        <div class="col-md-5 text-md-end">
            <div class="text-muted small text-uppercase">Destination</div>
            <h6 class="fw-bold text-primary mb-1"><?= $data['destination']['contact_name'] ?? '-' ?></h6>
            <div class="small text-muted"><?= $data['destination']['address'] ?? '-' ?></div>
        </div>
    </div>
</div>

<div class="track-card">
    <h6 class="text-muted fw-bold mb-4">Shipment History</h6>
    
    <div class="timeline">
        <?php if(isset($data['histories']) && is_array($data['histories'])): ?>
            <?php foreach($data['histories'] as $hist): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-date">
                        <?= date('Y-m-d H:i', strtotime($hist['date'])) ?>
                    </div>
                    <div class="timeline-status mb-1">
                        <?= $hist['status'] ?>
                    </div>
                    <div class="bg-light p-3 rounded border text-muted small">
                        <?= $hist['message'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">Tidak ada riwayat perjalanan.</p>
        <?php endif; ?>
    </div>
</div>