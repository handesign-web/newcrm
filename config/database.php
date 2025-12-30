<?php
// config/database.php
$host = 'localhost';
$user = 'linksfie_admin_it';
$pass = 'Kumisan5'; // Default XAMPP kosong
$db_name = 'linksfie_newcrm';

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Set timezone agar timestamp sesuai waktu Indonesia
date_default_timezone_set('Asia/Jakarta');
?>