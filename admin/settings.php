<?php
// Set Judul Halaman
$page_title = "System Settings";

// 1. Load Header & Sidebar
include 'includes/header.php';
include 'includes/sidebar.php';

// 2. Load Functions & Config
$funcPath = __DIR__ . '/../config/functions.php';
if (file_exists($funcPath)) {
    include $funcPath;
} else {
    die("Error: File config/functions.php tidak ditemukan.");
}

// 3. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>";
    exit;
}

$msg = "";

// LOGIC: Handle Save Settings (All in One)
if (isset($_POST['save_settings'])) {
    
    // 1. Simpan Text Settings (SMTP, Discord, Quotation, Invoice)
    $configs = [
        'smtp_host'                => $_POST['smtp_host'],
        'smtp_user'                => $_POST['smtp_user'],
        'smtp_pass'                => $_POST['smtp_pass'],
        'smtp_port'                => $_POST['smtp_port'],
        'smtp_secure'              => $_POST['smtp_secure'],
        'discord_webhook'          => $_POST['discord_webhook'],
        'discord_webhook_internal' => $_POST['discord_webhook_internal'],
        'company_address_full'     => $_POST['company_address_full'],
        'default_quotation_remarks'=> $_POST['default_quotation_remarks'],
        'invoice_payment_info'     => $_POST['invoice_payment_info'],
        'invoice_note_default'     => $_POST['invoice_note_default']
    ];

    foreach ($configs as $key => $val) {
        $val = $conn->real_escape_string($val);
        // Gunakan ON DUPLICATE KEY UPDATE logic atau UPDATE langsung jika key sudah di-insert
        // Agar aman, kita cek dulu atau gunakan INSERT IGNORE saat setup awal
        $conn->query("UPDATE settings SET setting_value = '$val' WHERE setting_key = '$key'");
    }

    // 2. Simpan Upload Files (Logo & Watermark)
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    function uploadAsset($fileKey, $dbKey, $conn, $dir) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $newName = $dbKey . '.' . $ext; 
                if(move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dir . $newName)) {
                    $conn->query("UPDATE settings SET setting_value = '$newName' WHERE setting_key = '$dbKey'");
                }
            }
        }
    }

    uploadAsset('company_logo', 'company_logo', $conn, $uploadDir);
    uploadAsset('company_watermark', 'company_watermark', $conn, $uploadDir);

    $msg = "<div class='alert alert-success alert-dismissible fade show'><i class='bi bi-check-circle'></i> Konfigurasi berhasil disimpan! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// LOGIC: Handle Test Email
if (isset($_POST['test_email'])) {
    $test_to = $_POST['test_to'];
    if (function_exists('sendEmailNotification')) {
        $result = sendEmailNotification($test_to, "Test Email Helpdesk", "<h1>Koneksi Berhasil!</h1><p>Email ini dikirim menggunakan PHPMailer melalui sistem Helpdesk.</p>");
        if ($result === true) {
            $msg = "<div class='alert alert-success alert-dismissible fade show'><i class='bi bi-check-circle'></i> Email berhasil dikirim ke <strong>$test_to</strong>!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $msg = "<div class='alert alert-danger alert-dismissible fade show'><i class='bi bi-exclamation-triangle'></i> Gagal Mengirim Email. Detail: <strong>$result</strong><button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// LOGIC: Handle Test Discord
if (isset($_POST['test_discord_customer'])) {
    if (function_exists('sendToDiscord')) {
        $res = sendToDiscord("Test Customer Webhook", "Ini adalah tes notifikasi untuk Customer Ticket.", [["name" => "Status", "value" => "Active", "inline" => true]]);
        if(isset($res['id']) || isset($res['channel_id'])) $msg = "<div class='alert alert-info alert-dismissible fade show'><i class='bi bi-discord'></i> Tes Customer Webhook Berhasil! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        else $msg = "<div class='alert alert-warning alert-dismissible fade show'><i class='bi bi-exclamation-circle'></i> Gagal mengirim ke Customer Webhook. Cek URL. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}
if (isset($_POST['test_discord_internal'])) {
    if (function_exists('sendInternalDiscord')) {
        $res = sendInternalDiscord("Test Internal Webhook", "Ini adalah tes notifikasi untuk Internal Ticket.", [["name" => "Status", "value" => "Active", "inline" => true]]);
        if($res) $msg = "<div class='alert alert-info alert-dismissible fade show'><i class='bi bi-discord'></i> Tes Internal Webhook Berhasil! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        else $msg = "<div class='alert alert-warning alert-dismissible fade show'><i class='bi bi-exclamation-circle'></i> Gagal mengirim ke Internal Webhook. Cek URL. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// Ambil Data Existing
$settings = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) { $settings[$row['setting_key']] = $row['setting_value']; }
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Settings System</h3>
                <p class="text-subtitle text-muted">Konfigurasi Email, Notifikasi, dan Aset Dokumen.</p>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <?= $msg ?>
    
    <div class="row">
        <div class="col-12 col-md-8">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="settingTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General & SMTP</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="quotation-tab" data-bs-toggle="tab" data-bs-target="#quotation" type="button" role="tab">Quotation Assets</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab">Invoice Settings</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body pt-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="tab-content" id="settingTabContent">
                            
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <h6 class="text-muted text-uppercase mb-3">Email Settings (SMTP)</h6>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <div class="input-group"><span class="input-group-text"><i class="bi bi-hdd-network"></i></span><input type="text" name="smtp_host" class="form-control" value="<?= $settings['smtp_host'] ?? '' ?>" placeholder="smtp.gmail.com"></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Port</label>
                                        <input type="number" name="smtp_port" class="form-control" value="<?= $settings['smtp_port'] ?? '587' ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP User</label>
                                        <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="text" name="smtp_user" class="form-control" value="<?= $settings['smtp_user'] ?? '' ?>"></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Security</label>
                                        <select name="smtp_secure" class="form-select">
                                            <option value="tls" <?= ($settings['smtp_secure']=='tls')?'selected':'' ?>>TLS (Port 587)</option>
                                            <option value="ssl" <?= ($settings['smtp_secure']=='ssl')?'selected':'' ?>>SSL (Port 465)</option>
                                            <option value="">None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">SMTP Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                                            <input type="password" name="smtp_pass" id="smtp_pass" class="form-control" value="<?= $settings['smtp_pass'] ?? '' ?>">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()"><i class="bi bi-eye" id="toggleIcon"></i></button>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="text-muted text-uppercase mb-3 mt-3">Discord Webhooks</h6>
                                <div class="mb-3">
                                    <label class="form-label text-primary fw-bold">Customer Ticket Webhook</label>
                                    <input type="text" name="discord_webhook" class="form-control" value="<?= $settings['discord_webhook'] ?? '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-warning fw-bold">Internal Ticket Webhook</label>
                                    <input type="text" name="discord_webhook_internal" class="form-control" value="<?= $settings['discord_webhook_internal'] ?? '' ?>">
                                </div>
                            </div>

                            <div class="tab-pane fade" id="quotation" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-bold">Company Logo (Header)</label>
                                        <input type="file" name="company_logo" class="form-control mb-2">
                                        <?php if(!empty($settings['company_logo'])): ?>
                                            <div class="p-2 border bg-light rounded text-center">
                                                <img src="../uploads/<?= $settings['company_logo'] ?>" style="max-height: 50px;">
                                                <small class="d-block text-muted mt-1">Current Logo</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-bold">Watermark (Background)</label>
                                        <input type="file" name="company_watermark" class="form-control mb-2">
                                        <?php if(!empty($settings['company_watermark'])): ?>
                                            <div class="p-2 border bg-light rounded text-center">
                                                <img src="../uploads/<?= $settings['company_watermark'] ?>" style="max-height: 50px; opacity: 0.5;">
                                                <small class="d-block text-muted mt-1">Current Watermark</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold">Company Address (Full)</label>
                                        <textarea name="company_address_full" class="form-control" rows="4"><?= $settings['company_address_full'] ?? '' ?></textarea>
                                        <div class="form-text">Alamat ini akan muncul di header surat penawaran dan invoice.</div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold">Default Quotation Remarks</label>
                                        <textarea name="default_quotation_remarks" class="form-control" rows="3"><?= $settings['default_quotation_remarks'] ?? '' ?></textarea>
                                        <div class="form-text">Catatan default untuk Quotation.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="invoice" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold">Invoice Payment Info (Bank Details)</label>
                                        <textarea name="invoice_payment_info" class="form-control" rows="6" placeholder="Masukkan Nama Bank, No Rek, Swift Code, dll..."><?= $settings['invoice_payment_info'] ?? '' ?></textarea>
                                        <div class="form-text">Informasi ini akan muncul di pojok kiri bawah Invoice PDF.</div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold">Invoice Default Note</label>
                                        <textarea name="invoice_note_default" class="form-control" rows="2" placeholder="Contoh: Please note payer is responsible for bank charges..."><?= $settings['invoice_note_default'] ?? '' ?></textarea>
                                        <div class="form-text">Catatan kaki (footer) untuk Invoice.</div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="mt-4 text-end border-top pt-3">
                            <button type="submit" name="save_settings" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i> Simpan Semua Konfigurasi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light"><h6 class="card-title mb-0">Test Email</h6></div>
                <div class="card-body"><br>
                    <form method="POST">
                        <div class="input-group">
                            <input type="email" name="test_to" class="form-control" placeholder="Email tujuan..." required>
                            <button type="submit" name="test_email" class="btn btn-secondary"><i class="bi bi-send"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light"><h6 class="card-title mb-0">Test Webhooks</h6></div>
                <div class="card-body d-grid gap-2"><br>
                    <form method="POST" class="d-grid gap-2">
                        <button type="submit" name="test_discord_customer" class="btn btn-primary text-white btn-sm">
                            <i class="bi bi-people-fill me-2"></i> Test Customer Webhook
                        </button>
                        <button type="submit" name="test_discord_internal" class="btn btn-warning text-dark btn-sm">
                            <i class="bi bi-building-fill me-2"></i> Test Internal Webhook
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function togglePassword() {
        var input = document.getElementById("smtp_pass");
        var icon = document.getElementById("toggleIcon");
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("bi-eye");
            icon.classList.add("bi-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("bi-eye-slash");
            icon.classList.add("bi-eye");
        }
    }
</script>

<?php include 'includes/footer.php'; ?>