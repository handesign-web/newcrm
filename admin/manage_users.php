<?php
// =========================================
// 1. INITIALIZATION & SECURITY
// =========================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php'; 

// --- PERMISSION CHECK ---
$can_access = false;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $can_access = true;
} else {
    $my_div = isset($_SESSION['division_id']) ? intval($_SESSION['division_id']) : 0;
    
    // Refresh session jika perlu
    if ($my_div === 0 && isset($_SESSION['user_id'])) {
        $uID = intval($_SESSION['user_id']);
        $qDiv = $conn->query("SELECT division_id FROM users WHERE id = $uID");
        if ($qDiv && $qDiv->num_rows > 0) {
            $my_div = intval($qDiv->fetch_assoc()['division_id']);
            $_SESSION['division_id'] = $my_div;
        }
    }
    
    $page_url = basename($_SERVER['PHP_SELF']); 
    
    // [FIXED] Menggunakan dp.menu_id (bukan dp.id) agar tidak error
    $sqlPerm = "SELECT dp.menu_id FROM division_permissions dp 
                JOIN menus m ON dp.menu_id = m.id 
                WHERE dp.division_id = $my_div AND m.url LIKE '%$page_url'";
    $chk = $conn->query($sqlPerm);
    if ($chk && $chk->num_rows > 0) $can_access = true;
}

if (!$can_access) {
    echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin.'); window.location='dashboard.php';</script>";
    exit;
}

$page_title = "Manage Users";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- HELPER FUNCTIONS ---
function generateRandomPassword($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*'), 0, $length);
}

// Fungsi Pintar: Cek Upload File Dulu, Kalau Kosong Cek Base64 Canvas
function processSignature($fileInputName, $base64InputName) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // 1. Cek Apakah Ada File Upload (.PNG)
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            return ['status' => false, 'msg' => 'Format file harus .PNG'];
        }
        $fileName = 'sign_upload_' . time() . '_' . rand(100,999) . '.png';
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $uploadDir . $fileName)) {
            return ['status' => true, 'file' => $fileName];
        }
    }

    // 2. Jika Tidak Ada File, Cek Canvas Base64
    if (!empty($_POST[$base64InputName])) {
        $base64_string = $_POST[$base64InputName];
        // Cek apakah string valid base64 image
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_string, $type)) {
            $data = base64_decode(substr($base64_string, strpos($base64_string, ',') + 1));
            if ($data === false) return ['status' => false, 'msg' => 'Gagal decode gambar'];
            
            $fileName = 'sign_digital_' . time() . '_' . rand(100,999) . '.png';
            if(file_put_contents($uploadDir . $fileName, $data)) {
                return ['status' => true, 'file' => $fileName];
            }
        }
    }

    return ['status' => false, 'msg' => 'No Data'];
}

$msg = "";

// =========================================
// 2. BACKEND LOGIC (CRUD)
// =========================================

// --- ADD USER ---
if (isset($_POST['add_user'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $role = $conn->real_escape_string($_POST['role']);
    $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : "NULL";
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $leave_quota = intval($_POST['leave_quota']);
    
    // Proses Signature (Upload OR Canvas)
    $signVal = "NULL";
    $procSign = processSignature('signature_file', 'signature_data');
    
    if ($procSign['status']) {
        $signVal = "'" . $procSign['file'] . "'";
    } elseif (isset($_FILES['signature_file']['name']) && !empty($_FILES['signature_file']['name']) && $procSign['msg'] == 'Format file harus .PNG') {
        $msg = "<div class='alert alert-danger'>Gagal: Signature harus format PNG!</div>";
    }
    
    // Lanjut jika tidak ada error blocking
    if (empty($msg)) {
        $cek = $conn->query("SELECT id FROM users WHERE email = '$email' OR username = '$username'");
        if($cek->num_rows > 0) {
            $msg = "<div class='alert alert-danger'>Username atau Email sudah ada!</div>";
        } else {
            $pass_raw = generateRandomPassword(10);
            $pass_hash = password_hash($pass_raw, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, phone, password, role, division_id, job_title, leave_quota, signature, must_change_password) 
                    VALUES ('$username', '$email', '$phone', '$pass_hash', '$role', $division_id, '$job_title', $leave_quota, $signVal, 1)";
            
            if ($conn->query($sql)) {
                if (function_exists('sendEmailNotification')) {
                    sendEmailNotification($email, "Akun Baru", "Login: $email / $pass_raw");
                }
                $msg = "<div class='alert alert-success'>User berhasil ditambahkan!</div>";
            } else { $msg = "<div class='alert alert-danger'>Error DB: " . $conn->error . "</div>"; }
        }
    }
}

// --- EDIT USER ---
if (isset($_POST['edit_user'])) {
    $id = intval($_POST['edit_id']);
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $role = $conn->real_escape_string($_POST['role']);
    $division_id = !empty($_POST['division_id']) ? intval($_POST['division_id']) : "NULL";
    $job_title = $conn->real_escape_string($_POST['job_title']);
    $leave_quota = intval($_POST['leave_quota']);
    
    $sql = "UPDATE users SET username='$username', email='$email', phone='$phone', role='$role', division_id=$division_id, job_title='$job_title', leave_quota=$leave_quota WHERE id=$id";
    
    if($conn->query($sql)) {
        // Proses Update Signature (Upload OR Canvas)
        $procSign = processSignature('edit_signature_file', 'edit_signature_data');
        
        if ($procSign['status']) {
            $newFile = $procSign['file'];
            $conn->query("UPDATE users SET signature='$newFile' WHERE id=$id");
        } elseif (isset($_FILES['edit_signature_file']['name']) && !empty($_FILES['edit_signature_file']['name']) && $procSign['msg'] == 'Format file harus .PNG') {
             $msg = "<div class='alert alert-warning'>Data tersimpan, TAPI Signature Gagal: Harus format PNG!</div>";
        }
        
        if(empty($msg)) $msg = "<div class='alert alert-success'>User diperbarui.</div>";
    }
}

// --- RESET PASSWORD ---
if (isset($_POST['reset_password'])) {
    $id = intval($_POST['reset_id']);
    $uData = $conn->query("SELECT email, username FROM users WHERE id=$id")->fetch_assoc();
    if ($uData) {
        $new_pass = generateRandomPassword(10);
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        if ($conn->query("UPDATE users SET password='$new_hash', must_change_password=1 WHERE id=$id")) {
            if (function_exists('sendEmailNotification')) sendEmailNotification($uData['email'], "Reset Password", "New Pass: $new_pass");
            $msg = "<div class='alert alert-warning'>Password direset.</div>";
        }
    }
}

// --- DELETE USER ---
if (isset($_POST['delete_user'])) {
    $id = intval($_POST['delete_id']);
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id=$id");
        $msg = "<div class='alert alert-success'>User dihapus.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Tidak bisa hapus diri sendiri.</div>";
    }
}

// --- FETCH DATA ---
$users = $conn->query("SELECT u.*, d.name as div_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id ORDER BY u.id DESC");
$divisions = []; 
$dRes = $conn->query("SELECT * FROM divisions");
while($d = $dRes->fetch_assoc()) $divisions[] = $d;
?>

<style>
    /* Table Styling */
    .table thead th {
        background-color: #f2f4f6;
        color: #555;
        font-weight: 600;
        font-size: 0.9rem;
        border-bottom: none;
    }
    .table tbody td {
        vertical-align: middle;
        padding: 12px 15px;
        color: #444;
    }
    /* Badges */
    .badge-admin { background-color: #dc3545; color: white; } 
    .badge-standard { background-color: #198754; color: white; } 
    .badge-job { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; font-weight: 500; } 
    .badge-quota { background-color: #3f51b5; color: white; font-weight: 600; }
    
    /* Action Buttons */
    .btn-icon-action {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        background: white;
        transition: all 0.2s;
    }
    .btn-edit-action { border: 1px solid #0d6efd; color: #0d6efd; }
    .btn-edit-action:hover { background: #0d6efd; color: white; }
    
    .btn-key-action { border: 1px solid #ffc107; color: #ffc107; }
    .btn-key-action:hover { background: #ffc107; color: white; }
    
    .btn-del-action { border: 1px solid #dc3545; color: #dc3545; }
    .btn-del-action:hover { background: #dc3545; color: white; }
    
    /* Signature Area */
    .sig-container {
        position: relative;
        height: 160px;
        border: 2px dashed #ced4da;
        background-color: #f8f9fa;
        border-radius: 6px;
        overflow: hidden;
    }
    .sig-placeholder {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #adb5bd;
        pointer-events: none;
        font-size: 0.9rem;
    }
    canvas {
        width: 100%;
        height: 100%;
        display: block;
    }
</style>

<div class="page-heading mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h3 class="mb-1">Manage Users</h3>
            <p class="text-muted mb-0">Kelola data pengguna, peran, dan akses sistem.</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-2"></i> Tambah User
            </button>
        </div>
    </div>
</div>

<div class="page-content">
    <?= $msg ?>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="table1" style="white-space: nowrap;">
                    <thead>
                        <tr>
                            <th class="ps-4">Full Name</th>
                            <th>Role & Div</th>
                            <th>Job Title</th>
                            <th class="text-center">Quota</th>
                            <th>Status Pass</th>
                            <th>Sign</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($users && $users->num_rows > 0): while($row = $users->fetch_assoc()): ?>
                        <tr class="border-bottom-light">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['username']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($row['email']) ?></div>
                            </td>
                            
                            <td>
                                <?php $badgeClass = ($row['role'] == 'admin') ? 'badge-admin' : 'badge-standard'; ?>
                                <span class="badge <?= $badgeClass ?> mb-1"><?= ucfirst($row['role']) ?></span>
                                <div class="text-muted small"><?= $row['div_name'] ?? '-' ?></div>
                            </td>
                            
                            <td>
                                <span class="badge badge-job rounded-pill px-3"><?= $row['job_title'] ?></span>
                            </td>
                            
                            <td class="text-center">
                                <span class="badge badge-quota rounded-2"><?= $row['leave_quota'] ?> Hari</span>
                            </td>
                            
                            <td>
                                <?php if($row['must_change_password'] == 1): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle-fill"></i> Wajib Ganti</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Aman</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if($row['signature']): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">No</span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <button class="btn btn-icon-action btn-edit-action btn-edit"
                                        data-id="<?= $row['id'] ?>"
                                        data-username="<?= htmlspecialchars($row['username']) ?>"
                                        data-email="<?= htmlspecialchars($row['email']) ?>"
                                        data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                        data-role="<?= $row['role'] ?>"
                                        data-division="<?= $row['division_id'] ?>"
                                        data-jobtitle="<?= $row['job_title'] ?>"
                                        data-quota="<?= $row['leave_quota'] ?>"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                        title="Edit User">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    
                                    <button class="btn btn-icon-action btn-key-action"
                                        data-bs-toggle="modal" data-bs-target="#resetModal<?= $row['id'] ?>" 
                                        title="Reset Password">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    
                                    <button class="btn btn-icon-action btn-del-action"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['id'] ?>" 
                                        title="Hapus User">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <div class="modal fade" id="resetModal<?= $row['id'] ?>"><div class="modal-dialog modal-sm"><form method="POST" class="modal-content"><div class="modal-header bg-warning text-dark"><h6 class="modal-title">Reset Password</h6></div><div class="modal-body text-start">Reset password <b><?= $row['username'] ?></b>?</div><div class="modal-footer"><button type="submit" name="reset_password" class="btn btn-warning btn-sm w-100">Reset</button><input type="hidden" name="reset_id" value="<?= $row['id'] ?>"></div></form></div></div>
                                <div class="modal fade" id="deleteModal<?= $row['id'] ?>"><div class="modal-dialog modal-sm"><form method="POST" class="modal-content"><div class="modal-header bg-danger text-white"><h6 class="modal-title">Hapus User</h6></div><div class="modal-body text-start">Hapus <b><?= $row['username'] ?></b>?</div><div class="modal-footer"><button type="submit" name="delete_user" class="btn btn-danger btn-sm w-100">Hapus</button><input type="hidden" name="delete_id" value="<?= $row['id'] ?>"></div></form></div></div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data user.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold">Tambah User Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Name</label><input type="text" name="username" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Phone</label><input type="text" name="phone" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Role</label><select name="role" class="form-select" required><option value="standard">Standard</option><option value="admin">Admin</option></select></div>
                    <div class="col-md-12"><label class="form-label small text-muted fw-bold">Division</label><select name="division_id" class="form-select" required><option value="">-- Pilih --</option><?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= $div['name'] ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label small text-primary fw-bold">Job Title</label><select name="job_title" class="form-select" required><option value="Staff">Staff</option><option value="Manager">Manager</option><option value="General Manager">GM</option></select></div>
                    <div class="col-md-6"><label class="form-label small text-primary fw-bold">Leave Quota</label><input type="number" name="leave_quota" class="form-control" value="12" required></div>
                    
                    <div class="col-12 mt-4">
                        <label class="form-label small text-muted fw-bold">Digital Signature</label>
                        <div class="sig-container mb-2">
                            <canvas id="add-sig-canvas"></canvas>
                            <span class="sig-placeholder" id="add-sig-placeholder">Tanda tangan di sini</span>
                        </div>
                        <input type="hidden" name="signature_data" id="add-sig-data">
                        
                        <div class="d-flex align-items-center mb-2">
                            <span class="text-muted small mx-2">- ATAU -</span>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>
                        <label class="form-label small text-muted">Upload Gambar (.PNG)</label>
                        <input type="file" name="signature_file" class="form-control form-control-sm" accept="image/png">
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-3 w-100" onclick="clearAddSign()"><i class="bi bi-eraser me-1"></i> Reset Canvas</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_user" class="btn btn-primary" onclick="saveAddSign()">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" enctype="multipart/form-data">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold">Edit Data User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Name</label><input type="text" name="username" id="edit_username" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label small text-muted fw-bold">Role</label><select name="role" id="edit_role" class="form-select" required><option value="standard">Standard</option><option value="admin">Admin</option></select></div>
                    <div class="col-md-12"><label class="form-label small text-muted fw-bold">Division</label><select name="division_id" id="edit_division" class="form-select" required><option value="">-- Pilih --</option><?php foreach($divisions as $div): ?><option value="<?= $div['id'] ?>"><?= $div['name'] ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label small text-success fw-bold">Job Title</label><select name="job_title" id="edit_job_title" class="form-select" required><option value="Staff">Staff</option><option value="Manager">Manager</option><option value="General Manager">GM</option></select></div>
                    <div class="col-md-6"><label class="form-label small text-success fw-bold">Leave Quota</label><input type="number" name="leave_quota" id="edit_quota" class="form-control" required></div>
                    
                    <div class="col-12 mt-4">
                        <label class="form-label small text-muted fw-bold">Update Signature</label>
                        
                        <div class="sig-container mb-2">
                            <canvas id="edit-sig-canvas"></canvas>
                            <span class="sig-placeholder" id="edit-sig-placeholder">Tanda tangan baru di sini</span>
                        </div>
                        <input type="hidden" name="edit_signature_data" id="edit-sig-data">
                        
                        <div class="d-flex align-items-center mb-2">
                            <span class="text-muted small mx-2">- ATAU -</span>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>
                        <label class="form-label small text-muted">Upload Gambar Baru (.PNG)</label>
                        <input type="file" name="edit_signature_file" class="form-control form-control-sm" accept="image/png">
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-3 w-100" onclick="clearEditSign()"><i class="bi bi-eraser me-1"></i> Reset Canvas</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="edit_user" class="btn btn-success" onclick="saveEditSign()">Update Data</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    var addPad, editPad;

    function initSignaturePad(canvasId, placeholderId) {
        var canvas = document.getElementById(canvasId);
        var placeholder = document.getElementById(placeholderId);
        var pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255, 255, 255, 0)' });
        pad.addEventListener("beginStroke", () => { if(placeholder) placeholder.style.display = 'none'; });
        return pad;
    }

    function resizeCanvas(canvas) {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }

    document.addEventListener("DOMContentLoaded", function() {
        addPad = initSignaturePad('add-sig-canvas', 'add-sig-placeholder');
        editPad = initSignaturePad('edit-sig-canvas', 'edit-sig-placeholder');

        document.getElementById('addUserModal').addEventListener('shown.bs.modal', function () {
            resizeCanvas(document.getElementById('add-sig-canvas'));
            addPad.clear(); 
            document.getElementById('add-sig-placeholder').style.display = 'block';
        });

        document.getElementById('editUserModal').addEventListener('shown.bs.modal', function () {
            resizeCanvas(document.getElementById('edit-sig-canvas'));
            editPad.clear(); 
            document.getElementById('edit-sig-placeholder').style.display = 'block';
        });

        document.querySelectorAll(".btn-edit").forEach(btn => {
            btn.addEventListener("click", function() {
                document.getElementById("edit_id").value = this.getAttribute("data-id");
                document.getElementById("edit_username").value = this.getAttribute("data-username");
                document.getElementById("edit_email").value = this.getAttribute("data-email");
                document.getElementById("edit_phone").value = this.getAttribute("data-phone");
                document.getElementById("edit_role").value = this.getAttribute("data-role");
                document.getElementById("edit_division").value = this.getAttribute("data-division");
                document.getElementById("edit_job_title").value = this.getAttribute("data-jobtitle");
                document.getElementById("edit_quota").value = this.getAttribute("data-quota");
            });
        });
    });

    function clearAddSign() { addPad.clear(); document.getElementById('add-sig-placeholder').style.display = 'block'; }
    function saveAddSign() { if(!addPad.isEmpty()) document.getElementById('add-sig-data').value = addPad.toDataURL('image/png'); }
    
    function clearEditSign() { editPad.clear(); document.getElementById('edit-sig-placeholder').style.display = 'block'; }
    function saveEditSign() { if(!editPad.isEmpty()) document.getElementById('edit-sig-data').value = editPad.toDataURL('image/png'); }
</script>