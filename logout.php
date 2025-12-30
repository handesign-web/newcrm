<?php
// 1. Mulai Sesi (Wajib untuk mengakses data sesi yang ada)
session_start();

// 2. Hapus semua variabel sesi
$_SESSION = [];

// 3. Hancurkan sesi sepenuhnya
session_unset();
session_destroy();

// 4. Arahkan pengguna kembali ke halaman Login
header("Location: login.php");
exit;
?>