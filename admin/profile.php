<?php
// --- 1. CONFIG & DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "My Profile";
include 'includes/header.php';
include 'includes/sidebar.php';

// Pastikan file functions dimuat
$func_path = __DIR__ . '/../config/functions.php';
if (file_exists($func_path)) {
    include_once $func_path;
}

// Pastikan koneksi database tersedia
if (!isset($conn) || $conn->connect_error) {
    die("<div class='p-4 alert alert-danger'>Error: Koneksi Database gagal.</div>");
}

// --- 2. CEK LOGIN ---
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location='../login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";

// --- 3. PROSES UPDATE DATA ---
if (isset($_POST['update_profile'])) {
    $name  = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    
    // Ambil data user lama untuk signature file lama
    $oldData = $conn->query("SELECT signature_file FROM users WHERE id = $user_id")->fetch_assoc();
    $signature_file = $oldData['signature_file'];

    // Setup Folder Upload
    $uploadDir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    // A. Cek Upload File Manual
    if (isset($_FILES['signature_upload']) && $_FILES['signature_upload']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['signature_upload']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
            $fileName = 'SIG_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['signature_upload']['tmp_name'], $uploadDir . $fileName)) {
                $signature_file = $fileName;
            } else {
                $msg = "<div class='alert alert-danger'>Gagal mengupload file signature. Cek permission folder.</div>";
            }
        }
    } 
    // B. Cek Tanda Tangan Canvas
    elseif (!empty($_POST['signature_canvas'])) {
        $data_uri = $_POST['signature_canvas'];
        if (strpos($data_uri, 'base64') !== false) {
            $encoded_image = explode(",", $data_uri)[1];
            $decoded_image = base64_decode($encoded_image);
            $fileName = 'SIG_CANVAS_' . $user_id . '_' . time() . '.png';
            if (file_put_contents($uploadDir . $fileName, $decoded_image)) {
                $signature_file = $fileName;
            }
        }
    }

    // Update Database
    if (empty($msg)) {
        $sqlUpdate = "UPDATE users SET username = ?, phone = ?, signature_file = ? WHERE id = ?";
        $stmtUp = $conn->prepare($sqlUpdate);
        if ($stmtUp) {
            $stmtUp->bind_param("sssi", $name, $phone, $signature_file, $user_id);
            if ($stmtUp->execute()) {
                $_SESSION['username'] = $name; 
                $msg = "<div class='alert alert-success'>Profil berhasil diperbarui!</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Database Error: " . $conn->error . "</div>";
            }
            $stmtUp->close();
        } else {
            $msg = "<div class='alert alert-danger'>Query Error: " . $conn->error . "</div>";
        }
    }
}

// --- 4. AMBIL DATA USER (QUERY AMAN) ---
$user = null;
$division_name = "-";

// Ambil Data User
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resUser = $stmt->get_result();
if ($resUser->num_rows > 0) {
    $user = $resUser->fetch_assoc();
} else {
    die("<div class='p-4 alert alert-danger'>User ID $user_id tidak ditemukan.</div>");
}

// [FIXED] Ambil Nama Divisi dengan Query Aman (SELECT *)
if (!empty($user['division_id'])) {
    $divId = intval($user['division_id']);
    // Cek tabel divisions ada atau tidak
    $checkDiv = $conn->query("SHOW TABLES LIKE 'divisions'");
    if ($checkDiv->num_rows > 0) {
        // Gunakan SELECT * agar tidak error "Unknown Column" jika nama kolom beda
        $resDiv = $conn->query("SELECT * FROM divisions WHERE id = $divId");
        if ($resDiv && $resDiv->num_rows > 0) {
            $d = $resDiv->fetch_assoc();
            // Deteksi otomatis nama kolom yang benar
            if(isset($d['division_name'])) {
                $division_name = $d['division_name'];
            } elseif(isset($d['name'])) {
                $division_name = $d['name'];
            } elseif(isset($d['title'])) {
                $division_name = $d['title'];
            }
        }
    }
}
?>

<style>
    canvas {
        border: 2px dashed #ccc;
        border-radius: 5px;
        cursor: crosshair;
        background-color: #fff;
        width: 100%;
        height: 150px;
    }
    .sig-container {
        position: relative;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 8px;
    }
</style>

<div class="page-heading">
    <div class="page-title mb-3">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>My Profile</h3>
                <p class="text-subtitle text-muted">Kelola informasi akun dan tanda tangan Anda.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <?= $msg ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">Informasi Akun</h5>
                            
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold">Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold">Email <small class="text-muted">(Read Only)</small></label>
                                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label fw-bold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label fw-bold">Role <small class="text-muted">(Read Only)</small></label>
                                <input type="text" class="form-control bg-light" value="<?= ucfirst($user['role']) ?>" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">Detail Pekerjaan</h5>
                            
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold">Division <small class="text-muted">(Read Only)</small></label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($division_name) ?>" readonly>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label fw-bold">Job Title <small class="text-muted">(Read Only)</small></label>
                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['job_title'] ?? '-') ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label fw-bold">Leave Quota <small class="text-muted">(Read Only)</small></label>
                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['leave_quota'] ?? 0) ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="text-primary mb-3">Update Signature</h5>
                            
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label small fw-bold">Gambar Tanda Tangan:</label>
                                    <div class="sig-container">
                                        <canvas id="sig-canvas"></canvas>
                                        <input type="hidden" name="signature_canvas" id="signature_canvas">
                                        <button type="button" class="btn btn-sm btn-secondary mt-2 w-100" id="clear-canvas">
                                            <i class="bi bi-eraser me-1"></i> Reset Canvas
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-12 text-center text-muted small my-1">- ATAU -</div>

                                <div class="col-12 mb-3">
                                    <label class="form-label small fw-bold">Upload Gambar Baru (.PNG)</label>
                                    <input type="file" name="signature_upload" class="form-control" accept="image/png, image/jpeg">
                                </div>

                                <?php if (!empty($user['signature_file'])): ?>
                                <div class="col-12 mt-2 p-2 border rounded bg-light text-center">
                                    <p class="mb-1 small fw-bold">Signature Saat Ini:</p>
                                    <img src="../uploads/signatures/<?= htmlspecialchars($user['signature_file']) ?>" style="max-height: 60px; border:1px solid #ddd; background:#fff;">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-light text-end">
                    <button type="submit" name="update_profile" class="btn btn-primary px-4 py-2" onclick="saveSignature()">
                        <i class="bi bi-save me-2"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </section>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    var canvas = document.getElementById("sig-canvas");
    var ctx = canvas.getContext("2d");
    var drawing = false;
    var hasDrawn = false; 

    function resizeCanvas() {
        var container = canvas.parentElement;
        canvas.width = container.offsetWidth - 20; 
        canvas.height = 150;
        ctx.lineWidth = 2;
        ctx.lineCap = "round";
        ctx.strokeStyle = "#000000";
    }
    
    window.addEventListener('load', resizeCanvas);
    window.addEventListener('resize', resizeCanvas);

    function getPos(canvas, evt) {
        var rect = canvas.getBoundingClientRect();
        return {
            x: (evt.clientX || evt.touches[0].clientX) - rect.left,
            y: (evt.clientY || evt.touches[0].clientY) - rect.top
        };
    }

    ['mousedown', 'touchstart'].forEach(evt => 
        canvas.addEventListener(evt, function (e) {
            drawing = true;
            hasDrawn = true;
            ctx.beginPath();
            var pos = getPos(canvas, e);
            ctx.moveTo(pos.x, pos.y);
            if(evt === 'touchstart') e.preventDefault();
        })
    );

    ['mouseup', 'touchend'].forEach(evt => 
        canvas.addEventListener(evt, function () {
            drawing = false;
            ctx.closePath();
        })
    );

    ['mousemove', 'touchmove'].forEach(evt => 
        canvas.addEventListener(evt, function (e) {
            if (!drawing) return;
            var pos = getPos(canvas, e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            if(evt === 'touchmove') e.preventDefault();
        })
    );

    document.getElementById("clear-canvas").addEventListener("click", function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawn = false;
        document.getElementById("signature_canvas").value = "";
    });

    function saveSignature() {
        if (hasDrawn) {
            var dataUrl = canvas.toDataURL("image/png");
            document.getElementById("signature_canvas").value = dataUrl;
        }
    }
</script>