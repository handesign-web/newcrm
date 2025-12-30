<?php
session_start();
include 'config/database.php'; // Pastikan path ini benar

// 1. CEK SESSION LOGIN
// Jika sudah login, kita cek apakah dia wajib ganti password atau tidak
if (isset($_SESSION['user_id'])) {
    // Jika flag force_change_password ada, paksa ke halaman ganti password
    if (isset($_SESSION['force_change_password']) && $_SESSION['force_change_password'] === true) {
        header("Location: admin/change_password_first.php");
        exit;
    }
    // Jika aman, lempar ke dashboard
    header("Location: admin/dashboard.php");
    exit;
}

$error = "";

// 2. PROSES LOGIN
if (isset($_POST['login_btn'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // [UPDATE] Tambahkan 'must_change_password' dalam query SELECT
    $sql = "SELECT id, username, password, role, division_id, must_change_password FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verifikasi Password
            if (password_verify($password, $user['password'])) {
                
                // Regenerasi Session ID untuk keamanan
                session_regenerate_id(true);

                // Set Session Utama
                $_SESSION['user_id'] = intval($user['id']);
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; 
                
                // Set Division ID
                $divId = isset($user['division_id']) ? intval($user['division_id']) : 0;
                $_SESSION['division_id'] = $divId;
                
                // [LOGIKA BARU] Cek Wajib Ganti Password
                if ($user['must_change_password'] == 1) {
                    $_SESSION['force_change_password'] = true; // Set Flag Sesi
                    header("Location: admin/change_password_first.php"); // Redirect ke halaman khusus
                    exit;
                }
                
                // Jika tidak wajib ganti password, masuk Dashboard
                header("Location: admin/dashboard.php");
                exit;
            } else {
                $error = "Password salah.";
            }
        } else {
            $error = "Username atau Email tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $error = "Terjadi kesalahan pada sistem database.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Helpdesk Admin</title>
    
    <link rel="stylesheet" href="assets/compiled/css/app.css">
    <link rel="stylesheet" href="assets/compiled/css/auth.css">
    
    <style>
        /* Konsistensi Background Kanan */
        #auth-right {
            background-image: url('https://images.unsplash.com/photo-1497215728101-856f4ea42174?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 100%;
        }
    </style>
</head>

<body>
    <div id="auth">
        <div class="row h-100">
            <div class="col-lg-5 col-12">
                <div id="auth-left">
                    <div class="auth-logo mb-5">
                        <a href="index.php">
                            <h3><i class="bi bi-life-preserver text-primary"></i> Helpdesk System</h3>
                        </a>
                    </div>

                    <h1 class="auth-title">Log in.</h1>
                    <p class="auth-subtitle mb-5">Masuk dengan data akun yang Anda miliki.</p>

                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="text" name="username" class="form-control form-control-xl" placeholder="Username / Email" required>
                            <div class="form-control-icon">
                                <i class="bi bi-person"></i>
                            </div>
                        </div>
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" name="password" class="form-control form-control-xl" placeholder="Password" required>
                            <div class="form-control-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                        </div>
                        
                        <div class="form-check form-check-lg d-flex align-items-end">
                            <input class="form-check-input me-2" type="checkbox" value="" id="flexCheckDefault">
                            <label class="form-check-label text-gray-600" for="flexCheckDefault">
                                Keep me logged in
                            </label>
                        </div>
                        
                        <button type="submit" name="login_btn" class="btn btn-primary btn-block btn-lg shadow-lg mt-5">Log in</button>
                    </form>

                    <div class="text-center mt-5 text-lg fs-4">
                        <p class="text-gray-600">Bukan Admin? <a href="index.php" class="font-bold">Kembali ke Beranda</a>.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 d-none d-lg-block">
                <div id="auth-right">
                    </div>
            </div>
        </div>
    </div>
</body>

</html>