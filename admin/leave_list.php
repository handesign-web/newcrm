<?php
// =========================================
// 1. CONFIGURATION & LOGIC
// =========================================
ob_start();
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Validasi Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; 

// Ambil Data User Lengkap
$stmt = $conn->prepare("SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$currUser = $stmt->get_result()->fetch_assoc();

$my_div_id = $currUser['division_id'];
$my_job    = $currUser['job_title']; 
$my_quota  = $currUser['leave_quota'];

// Tentukan Role Logika
$is_admin   = ($role == 'admin');
$is_hr      = ($is_admin || stripos($currUser['div_name'] ?? '', 'HR') !== false || stripos($currUser['div_name'] ?? '', 'Human') !== false);
$is_manager = ($my_job == 'Manager' || $my_job == 'General Manager');
$is_staff   = (!$is_manager && !$is_hr && !$is_admin);

// View Default
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'myleaves';

// Security Redirect Tabs
if (($active_tab == 'dashboard' || $active_tab == 'approval' || $active_tab == 'history') && !$is_manager && !$is_hr && !$is_admin) {
    $active_tab = 'myleaves';
}

$msg = "";

// =========================================
// 2. BACKEND ACTIONS
// =========================================

// A. UPDATE QUOTA (HR ONLY)
if (isset($_POST['update_quota']) && $is_hr) {
    $target_uid = intval($_POST['quota_user_id']);
    $new_quota  = intval($_POST['new_quota']);
    $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : NULL;
    $valid_until= !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;
    
    $stmt = $conn->prepare("UPDATE users SET leave_quota=?, quota_valid_from=?, quota_valid_until=? WHERE id=?");
    $stmt->bind_param("issi", $new_quota, $valid_from, $valid_until, $target_uid);
    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success alert-dismissible fade show'>Kuota berhasil diperbarui.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// B. SUBMIT CUTI
if (isset($_POST['submit_leave'])) {
    $start = $_POST['start_date'];
    $end   = $_POST['end_date'];
    $type  = $_POST['leave_type'];
    $reason= $conn->real_escape_string($_POST['reason']);
    
    $d1 = new DateTime($start); $d2 = new DateTime($end);
    $days = $d1->diff($d2)->days + 1; 
    
    if ($type == 'Annual' && $days > $my_quota) {
        $msg = "<div class='alert alert-danger'>Gagal: Sisa kuota tidak mencukupi ($my_quota hari).</div>";
    } else {
        // Staff -> pending_manager, Manager -> pending_hr
        $init_status = ($is_manager) ? 'pending_hr' : 'pending_manager';
        
        $sql = "INSERT INTO leaves (user_id, division_id, leave_type, start_date, end_date, total_days, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssiss", $user_id, $my_div_id, $type, $start, $end, $days, $reason, $init_status);
        
        if ($stmt->execute()) {
            echo "<script>alert('Pengajuan berhasil dikirim!'); window.location='leave_list.php?tab=myleaves';</script>";
        } else {
            $msg = "<div class='alert alert-danger'>Database Error: ".$conn->error."</div>";
        }
    }
}

// C. APPROVAL PROCESS
if (isset($_POST['process_approval'])) {
    $leave_id = intval($_POST['leave_id']);
    $action   = $_POST['action_type']; 
    $note     = $conn->real_escape_string($_POST['approval_note']);
    $now      = date('Y-m-d H:i:s');
    
    $lData = $conn->query("SELECT * FROM leaves WHERE id=$leave_id")->fetch_assoc();
    $requester_id = $lData['user_id'];
    $req_days     = $lData['total_days'];
    
    if ($action == 'approve_mgr') {
        $conn->query("UPDATE leaves SET status='pending_hr', manager_note='$note', manager_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='alert alert-success alert-dismissible fade show'>Disetujui Manager. Menunggu HR.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } 
    elseif ($action == 'reject_mgr') {
        $conn->query("UPDATE leaves SET status='rejected', manager_note='$note', manager_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='alert alert-warning alert-dismissible fade show'>Permintaan ditolak (Manager).<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    elseif ($action == 'approve_hr') {
        if ($lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota - $req_days WHERE id = $requester_id");
        }
        $conn->query("UPDATE leaves SET status='approved', hr_note='$note', hr_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='alert alert-success alert-dismissible fade show'>Disetujui HR (Final). Kuota terpotong.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    elseif ($action == 'reject_hr') {
        $conn->query("UPDATE leaves SET status='rejected', hr_note='$note', hr_approved_at='$now' WHERE id=$leave_id");
        $msg = "<div class='alert alert-warning alert-dismissible fade show'>Permintaan ditolak (HR).<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// D. CANCEL LEAVE
if (isset($_POST['cancel_leave_request'])) {
    $leave_id = intval($_POST['cancel_id']);
    $reason   = $conn->real_escape_string($_POST['cancel_reason']);
    
    $lData = $conn->query("SELECT * FROM leaves WHERE id=$leave_id AND user_id=$user_id")->fetch_assoc();
    
    if ($lData) {
        if ($lData['status'] == 'approved' && $lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota + {$lData['total_days']} WHERE id = $user_id");
        }
        
        $full_note = "CANCELLED by User: " . $reason;
        $conn->query("UPDATE leaves SET status='cancelled', edit_reason='$full_note' WHERE id=$leave_id");
        
        $msg = "<div class='alert alert-secondary alert-dismissible fade show'>Cuti berhasil dibatalkan.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        $currUser = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc(); 
        $my_quota = $currUser['leave_quota'];
    }
}

// E. REVISE LEAVE
if (isset($_POST['revise_leave_request'])) {
    $leave_id = intval($_POST['revise_id']);
    $new_start= $_POST['revise_start'];
    $new_end  = $_POST['revise_end'];
    $reason   = $conn->real_escape_string($_POST['revise_reason']);
    
    $lData = $conn->query("SELECT * FROM leaves WHERE id=$leave_id AND user_id=$user_id")->fetch_assoc();
    $rev_count = isset($lData['revision_count']) ? $lData['revision_count'] : 0;
    
    if ($rev_count >= 1) {
        $msg = "<div class='alert alert-danger'>Gagal: Revisi hanya diperbolehkan 1 kali.</div>";
    } else {
        $d1 = new DateTime($new_start); $d2 = new DateTime($new_end); 
        $new_days = $d1->diff($d2)->days + 1;
        
        if ($lData['status'] == 'approved' && $lData['leave_type'] == 'Annual') {
            $conn->query("UPDATE users SET leave_quota = leave_quota + {$lData['total_days']} WHERE id = $user_id");
        }
        
        $sqlRev = "UPDATE leaves SET start_date='$new_start', end_date='$new_end', total_days=$new_days, status='pending_hr', edit_reason='$reason', revision_count = revision_count + 1 WHERE id=$leave_id";
        
        if ($conn->query($sqlRev)) {
            $msg = "<div class='alert alert-info alert-dismissible fade show'>Revisi berhasil diajukan. Menunggu persetujuan HR.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Styling Tabs */
    .nav-tabs .nav-link { color: #6c757d; font-weight: 500; border: none; border-bottom: 2px solid transparent; }
    .nav-tabs .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: none; font-weight: bold; }
    .nav-tabs .nav-link:hover { border-bottom: 2px solid #ddd; }
    
    /* Status Badges */
    .status-badge { font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; font-weight: 600; text-transform: uppercase; }
    .st-approved { background-color: #d1e7dd; color: #0f5132; }
    .st-rejected { background-color: #f8d7da; color: #842029; }
    .st-pending_manager { background-color: #fff3cd; color: #664d03; }
    .st-pending_hr { background-color: #cff4fc; color: #055160; }
    .st-cancelled { background-color: #e2e3e5; color: #41464b; }
    
    /* Table & Cards */
    .table-custom thead th { background-color: #f8f9fa; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; }
    .table-custom tbody td { font-size: 0.9rem; vertical-align: middle; }
    .card-stat { transition: transform 0.2s; border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .card-stat:hover { transform: translateY(-3px); }

    /* [FIX] Icon Bulat Sempurna & Center */
    .icon-shape {
        width: 60px !important;
        height: 60px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 50% !important;
        flex-shrink: 0;
        padding: 0 !important; /* Hapus padding bawaan bootstrap jika ada */
        margin-right: 1rem;
    }
    .icon-shape i {
        line-height: 1 !important;
        font-size: 1.7rem; /* Ukuran icon */
    }
</style>

<div class="page-heading">
    <h3>Manajemen Cuti Karyawan</h3>
    <p class="text-muted">Portal pengajuan, persetujuan, dan pemantauan cuti.</p>
</div>

<?= $msg ?>

<ul class="nav nav-tabs mb-4">
    <?php if($is_manager || $is_hr || $is_admin): ?>
    <li class="nav-item">
        <a class="nav-link <?= ($active_tab == 'dashboard') ? 'active' : '' ?>" href="?tab=dashboard"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($active_tab == 'approval') ? 'active' : '' ?>" href="?tab=approval">
            <i class="bi bi-check-circle me-2"></i> Persetujuan 
            <?php 
                $countSQL = "SELECT COUNT(*) FROM leaves WHERE status = " . (($is_hr) ? "'pending_hr'" : "'pending_manager' AND division_id = $my_div_id");
                $cnt = $conn->query($countSQL)->fetch_row()[0];
                if($cnt > 0) echo "<span class='badge bg-danger ms-1'>$cnt</span>";
            ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($active_tab == 'history') ? 'active' : '' ?>" href="?tab=history">
            <i class="bi bi-clock-history me-2"></i> Riwayat Semua
        </a>
    </li>
    <?php endif; ?>
    
    <li class="nav-item">
        <a class="nav-link <?= ($active_tab == 'myleaves') ? 'active' : '' ?>" href="?tab=myleaves"><i class="bi bi-person-lines-fill me-2"></i> Riwayat Saya</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($active_tab == 'create') ? 'active' : '' ?>" href="?tab=create"><i class="bi bi-plus-lg me-2"></i> Ajukan Cuti</a>
    </li>
</ul>

<div class="tab-content">

    <?php if ($active_tab == 'dashboard' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="row">
        <?php
            $whereDash = "WHERE 1=1";
            if(!$is_hr && !$is_admin && $is_manager) {
                $whereDash .= " AND u.division_id = $my_div_id";
            }
            
            $totEmp = $conn->query("SELECT COUNT(*) FROM users u $whereDash AND role != 'admin'")->fetch_row()[0];
            $today = date('Y-m-d');
            $onLeave = $conn->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND status='approved' AND '$today' BETWEEN start_date AND end_date")->fetch_row()[0];
            $pend = $conn->query("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND (status='pending_manager' OR status='pending_hr')")->fetch_row()[0];
        ?>
        <div class="col-md-4">
            <div class="card card-stat bg-white mb-3">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light-primary icon-shape text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Total Karyawan</h6>
                        <h3 class="fw-bold mb-0"><?= $totEmp ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat bg-white mb-3">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light-success icon-shape text-success">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Sedang Cuti Hari Ini</h6>
                        <h3 class="fw-bold mb-0"><?= $onLeave ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat bg-white mb-3">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light-warning icon-shape text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Menunggu Approval</h6>
                        <h3 class="fw-bold mb-0"><?= $pend ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Monitoring Kuota Cuti Karyawan</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Nama Karyawan</th>
                                    <th>Jabatan</th>
                                    <th class="text-center">Sisa Kuota</th>
                                    <th class="text-center">Valid Sampai</th>
                                    <?php if($is_hr): ?><th class="text-center">Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sqlList = "SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id=d.id $whereDash AND role != 'admin' ORDER BY u.username ASC";
                                $resList = $conn->query($sqlList);
                                while($r = $resList->fetch_assoc()):
                                    $validDate = !empty($r['quota_valid_until']) ? date('d M Y', strtotime($r['quota_valid_until'])) : '-';
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= htmlspecialchars($r['username']) ?></td>
                                    <td class="text-muted small"><?= $r['job_title'] ?> (<?= $r['div_name'] ?>)</td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?= $r['leave_quota'] ?></span></td>
                                    <td class="text-center small"><?= $validDate ?></td>
                                    <?php if($is_hr): ?>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary py-0" onclick="openQuotaModal(<?= $r['id'] ?>, '<?= $r['username'] ?>', <?= $r['leave_quota'] ?>, '<?= $r['quota_valid_from'] ?>', '<?= $r['quota_valid_until'] ?>')">
                                            <i class="bi bi-pencil-square"></i> Adjust
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Statistik Pengajuan Bulanan</h6>
                </div>
                <div class="card-body">
                    <canvas id="leaveChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'history' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">Riwayat Pengajuan Cuti (Disetujui/Ditolak)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Pemohon</th>
                            <th>Detail Cuti</th>
                            <th>Tgl Cuti</th>
                            <th>Status</th>
                            <th>Catatan HR/Mgr</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $whereHist = "WHERE status IN ('approved', 'rejected', 'cancelled')";
                        if ($is_manager && !$is_hr && !$is_admin) {
                            $whereHist .= " AND l.division_id = $my_div_id";
                        }
                        
                        $sqlHist = "SELECT l.*, u.username, u.job_title, d.name as div_name 
                                    FROM leaves l 
                                    JOIN users u ON l.user_id = u.id 
                                    LEFT JOIN divisions d ON l.division_id = d.id 
                                    $whereHist 
                                    ORDER BY l.created_at DESC LIMIT 100";
                        $resHist = $conn->query($sqlHist);

                        if($resHist->num_rows > 0):
                        while($row = $resHist->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= htmlspecialchars($row['username']) ?></div>
                                <small class="text-muted"><?= $row['job_title'] ?> - <?= $row['div_name'] ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $row['leave_type'] ?></span>
                                <span class="ms-1 small"><?= $row['total_days'] ?> Hari</span>
                                <div class="small text-muted fst-italic mt-1">"<?= htmlspecialchars($row['reason']) ?>"</div>
                            </td>
                            <td><?= date('d M Y', strtotime($row['start_date'])) ?> s/d <?= date('d M Y', strtotime($row['end_date'])) ?></td>
                            <td><span class="status-badge st-<?= $row['status'] ?>"><?= strtoupper($row['status']) ?></span></td>
                            <td>
                                <?php 
                                    if($row['hr_note']) echo "<div class='small'>HR: {$row['hr_note']}</div>";
                                    elseif($row['manager_note']) echo "<div class='small'>Mgr: {$row['manager_note']}</div>";
                                    else echo "-";
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat cuti yang selesai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'approval' && ($is_manager || $is_hr || $is_admin)): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">Daftar Menunggu Persetujuan</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Pemohon</th>
                            <th>Detail Cuti</th>
                            <th>Alasan</th>
                            <th>Status Saat Ini</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $whereApp = "WHERE status != 'approved' AND status != 'rejected' AND status != 'cancelled'";
                        if ($is_hr || $is_admin) {
                            $whereApp .= " AND status = 'pending_hr'";
                        } elseif ($is_manager) {
                            $whereApp .= " AND status = 'pending_manager' AND l.division_id = $my_div_id AND l.user_id != $user_id";
                        }

                        $sqlApp = "SELECT l.*, u.username, u.job_title FROM leaves l JOIN users u ON l.user_id=u.id $whereApp ORDER BY l.created_at ASC";
                        $resApp = $conn->query($sqlApp);

                        if($resApp->num_rows > 0):
                        while($row = $resApp->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold"><?= htmlspecialchars($row['username']) ?></div>
                                <small class="text-muted"><?= $row['job_title'] ?></small>
                            </td>
                            <td>
                                <div class="fw-bold text-primary"><?= $row['leave_type'] ?> (<?= $row['total_days'] ?> Hari)</div>
                                <div class="small"><?= date('d M', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?></div>
                            </td>
                            <td><small class="fst-italic text-muted">"<?= htmlspecialchars($row['reason']) ?>"</small></td>
                            <td><span class="status-badge st-<?= $row['status'] ?>"><?= strtoupper($row['status']) ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-success" onclick="openApprovalModal(<?= $row['id'] ?>, 'approve')"><i class="bi bi-check-lg"></i> Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="openApprovalModal(<?= $row['id'] ?>, 'reject')"><i class="bi bi-x-lg"></i> Reject</button>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada permintaan cuti pending.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'myleaves'): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <h6 class="mb-0 fw-bold">Riwayat Pengajuan Cuti Saya</h6>
            <div class="text-end">
                <span class="badge bg-light text-primary border">Sisa Kuota: <?= $my_quota ?> Hari</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Tipe</th>
                            <th>Tanggal Cuti</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Catatan Approval</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $mySql = "SELECT * FROM leaves WHERE user_id = $user_id ORDER BY created_at DESC";
                        $myRes = $conn->query($mySql);
                        if($myRes->num_rows > 0):
                        while($row = $myRes->fetch_assoc()):
                            $rev_count = $row['revision_count'] ?? 0;
                        ?>
                        <tr>
                            <td class="ps-4"><?= $row['leave_type'] ?></td>
                            <td>
                                <div class="fw-bold"><?= date('d M', strtotime($row['start_date'])) ?> - <?= date('d M Y', strtotime($row['end_date'])) ?></div>
                                <small class="text-muted">Diajukan: <?= date('d M Y', strtotime($row['created_at'])) ?></small>
                            </td>
                            <td><?= $row['total_days'] ?> Hari</td>
                            <td>
                                <span class="status-badge st-<?= $row['status'] ?>">
                                    <?= str_replace('_', ' ', strtoupper($row['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($row['manager_note']) echo "<div class='small text-muted'>Mgr: {$row['manager_note']}</div>"; ?>
                                <?php if($row['hr_note']) echo "<div class='small text-muted'>HR: {$row['hr_note']}</div>"; ?>
                                <?php if($row['edit_reason']) echo "<div class='small text-danger fst-italic'>Note: {$row['edit_reason']}</div>"; ?>
                            </td>
                            <td class="text-center">
                                <?php if($row['status'] == 'pending_manager' || $row['status'] == 'pending_hr'): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="openCancelModal(<?= $row['id'] ?>)">Cancel</button>
                                <?php elseif($row['status'] == 'approved' && $rev_count < 1): ?>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="openCancelModal(<?= $row['id'] ?>)">Cancel</button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="openReviseModal(<?= $row['id'] ?>, '<?= $row['start_date'] ?>', '<?= $row['end_date'] ?>')">Revisi</button>
                                    </div>
                                <?php elseif($row['status'] == 'approved'): ?>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openCancelModal(<?= $row['id'] ?>)">Cancel</button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat cuti.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'create'): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-primary">Formulir Pengajuan Cuti</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Jenis Cuti</label>
                                <select name="leave_type" class="form-select">
                                    <option value="Annual">Annual Leave (Cuti Tahunan)</option>
                                    <option value="Sick">Sick Leave (Sakit)</option>
                                    <option value="Unpaid">Unpaid Leave</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Sisa Kuota</label>
                                <input type="text" class="form-control bg-light" value="<?= $my_quota ?> Hari" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Tanggal Selesai</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Alasan Cuti</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Jelaskan alasan pengajuan cuti..." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="submit_leave" class="btn btn-primary">Kirim Pengajuan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">Konfirmasi Persetujuan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="leave_id" id="app_leave_id">
                <input type="hidden" name="action_type" id="app_action_type">
                <label class="form-label small fw-bold text-muted">Catatan Approval (Opsional)</label>
                <textarea name="approval_note" class="form-control" rows="2" placeholder="Tambahkan catatan..."></textarea>
                <div class="alert alert-info mt-2 mb-0 small" id="app_alert" style="display:none;">
                    <i class="bi bi-info-circle me-1"></i> Tindakan ini akan meneruskan ke HR.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="process_approval" class="btn btn-primary btn-sm px-4">Proses</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 bg-light py-2">
                <h6 class="modal-title fw-bold text-danger">Batalkan Cuti</h6>
            </div>
            <div class="modal-body">
                <input type="hidden" name="cancel_id" id="cancel_leave_id">
                <p class="small text-muted mb-2">Apakah Anda yakin ingin membatalkan cuti ini? Kuota akan dikembalikan jika sudah disetujui.</p>
                <label class="form-label small fw-bold">Alasan Pembatalan (Wajib)</label>
                <textarea name="cancel_reason" class="form-control" rows="2" required></textarea>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" name="cancel_leave_request" class="btn btn-danger btn-sm">Ya, Batalkan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="reviseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 bg-light py-2">
                <h6 class="modal-title fw-bold text-warning">Revisi Tanggal Cuti</h6>
            </div>
            <div class="modal-body">
                <input type="hidden" name="revise_id" id="revise_leave_id">
                <div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle me-1"></i> Revisi hanya dapat dilakukan 1 kali.</div>
                
                <div class="row mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Mulai Baru</label>
                        <input type="date" name="revise_start" id="rev_start" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Selesai Baru</label>
                        <input type="date" name="revise_end" id="rev_end" class="form-control form-control-sm" required>
                    </div>
                </div>
                <label class="form-label small fw-bold">Alasan Revisi (Wajib)</label>
                <textarea name="revise_reason" class="form-control" rows="2" required></textarea>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="revise_leave_request" class="btn btn-warning btn-sm">Ajukan Revisi</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="quotaModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 bg-primary text-white py-2">
                <h6 class="modal-title fw-bold">Adjust Kuota</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="quota_user_id" id="q_uid">
                <div class="mb-2 text-center fw-bold" id="q_uname"></div>
                <div class="mb-2">
                    <label class="form-label small">Jumlah Kuota</label>
                    <input type="number" name="new_quota" id="q_val" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Valid From</label>
                    <input type="date" name="valid_from" id="q_from" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Valid Until</label>
                    <input type="date" name="valid_until" id="q_until" class="form-control">
                </div>
            </div>
            <div class="modal-footer border-0 py-2">
                <button type="submit" name="update_quota" class="btn btn-primary btn-sm w-100">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openApprovalModal(id, type) {
        document.getElementById('app_leave_id').value = id;
        
        let actType = '';
        let isHr = <?= $is_hr ? 'true' : 'false' ?>;
        
        if (type === 'approve') {
            actType = isHr ? 'approve_hr' : 'approve_mgr';
        } else {
            actType = isHr ? 'reject_hr' : 'reject_mgr';
        }
        
        document.getElementById('app_action_type').value = actType;
        
        if (actType === 'approve_mgr') {
            document.getElementById('app_alert').style.display = 'block';
        } else {
            document.getElementById('app_alert').style.display = 'none';
        }
        
        new bootstrap.Modal(document.getElementById('approvalModal')).show();
    }

    function openCancelModal(id) {
        document.getElementById('cancel_leave_id').value = id;
        new bootstrap.Modal(document.getElementById('cancelModal')).show();
    }

    function openReviseModal(id, start, end) {
        document.getElementById('revise_leave_id').value = id;
        document.getElementById('rev_start').value = start;
        document.getElementById('rev_end').value = end;
        new bootstrap.Modal(document.getElementById('reviseModal')).show();
    }

    function openQuotaModal(id, name, quota, from, until) {
        document.getElementById('q_uid').value = id;
        document.getElementById('q_uname').innerText = name;
        document.getElementById('q_val').value = quota;
        document.getElementById('q_from').value = from;
        document.getElementById('q_until').value = until;
        new bootstrap.Modal(document.getElementById('quotaModal')).show();
    }

    // Chart.js untuk Dashboard
    <?php if ($active_tab == 'dashboard' && ($is_manager || $is_hr || $is_admin)): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('leaveChart').getContext('2d');
        
        <?php
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $dataCounts = [];
            foreach(range(1,12) as $m) {
                $sqlC = "SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id=u.id $whereDash AND MONTH(start_date) = $m AND YEAR(start_date) = YEAR(CURRENT_DATE)";
                $dataCounts[] = $conn->query($sqlC)->fetch_row()[0];
            }
        ?>
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Pengajuan Cuti (Bulan Ini)',
                    data: <?= json_encode($dataCounts) ?>,
                    backgroundColor: '#435ebe',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    });
    <?php endif; ?>
</script>