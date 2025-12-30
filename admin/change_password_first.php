<?php
session_start();
// Sesuaikan path ke database (naik satu folder dari admin)
include '../config/database.php';

// 1. KEAMANAN: Cek apakah user login DAN wajib ganti password
// Jika tidak ada session force, tendang ke dashboard
if (!isset($_SESSION['user_id']) || !isset($_SESSION['force_change_password'])) {
    header("Location: dashboard.php");
    exit;
}

$msg = "";
$user_id = $_SESSION['user_id'];

// 2. PROSES GANTI PASSWORD
if (isset($_POST['update_password'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];
    
    if (strlen($pass1) < 6) {
        $msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Password minimal 6 karakter.</div>";
    } elseif ($pass1 !== $pass2) {
        $msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Konfirmasi password tidak cocok.</div>";
    } else {
        $new_hash = password_hash($pass1, PASSWORD_DEFAULT);
        
        // Update Password & Matikan Flag
        $sql = "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("si", $new_hash, $user_id);
            if ($stmt->execute()) {
                // Hapus session force agar user bisa masuk dashboard
                unset($_SESSION['force_change_password']);
                
                echo "<script>alert('Password berhasil diperbarui! Selamat datang.'); window.location='dashboard.php';</script>";
                exit;
            } else {
                $msg = "<div class='alert alert-danger'>Gagal update database.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keamanan Akun - Helpdesk</title>
    
    <link rel="stylesheet" href="../assets/compiled/css/app.css">
    <link rel="stylesheet" href="../assets/compiled/css/auth.css">
    
    <style>
        /* Menggunakan Background yang SAMA dengan Login.php */
        #auth-right {
            background-image: url('https://images.unsplash.com/photo-1497215728101-856f4ea42174?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 100%;
        }
        
        /* Agar tampilan lebih rapi di layar kecil */
        .auth-logo {
            margin-bottom: 2rem;
        }
        .auth-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>

<body>
    <div id="auth">
        <div class="row h-100">
            <div class="col-lg-5 col-12">
                <div id="auth-left">
                    <div class="auth-logo">
                        <a href="#">
                            <h3><i class="bi bi-shield-lock-fill text-warning"></i> Keamanan Akun</h3>
                        </a>
                    </div>
                    
                    <h1 class="auth-title">Password Baru.</h1>
                    <p class="auth-subtitle mb-4 text-muted">
                        Halo <b><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></b>.<br>
                        Karena ini login pertama Anda (atau password baru direset), silakan buat password baru untuk melanjutkan.
                    </p>

                    <?= $msg ?>

                    <form method="POST">
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" name="pass1" class="form-control form-control-xl" placeholder="Password Baru" required autofocus>
                            <div class="form-control-icon">
                                <i class="bi bi-key"></i>
                            </div>
                        </div>
                        
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" name="pass2" class="form-control form-control-xl" placeholder="Ulangi Password Baru" required>
                            <div class="form-control-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_password" class="btn btn-primary btn-block btn-lg shadow-lg mt-4">
                            Simpan Password & Masuk
                        </button>
                    </form>

                    <div class="text-center mt-5 text-lg fs-6">
                        <p class="text-gray-600">Bukan akun Anda? <a href="../logout.php" class="font-bold text-danger">Logout</a>.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7 d-none d-lg-block">
                <div id="auth-right"></div>
            </div>
        </div>
    </div>
</body>
</html>