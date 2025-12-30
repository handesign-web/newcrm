<?php
// 1. AKTIFKAN ERROR REPORTING (Hanya nyalakan saat development/perbaikan)
// Ini akan mengubah tampilan "Error 500" menjadi pesan error spesifik yang bisa dibaca.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. CEK KETERSEDIAAN FILE CONFIG
// Menggunakan __DIR__ memastikan path absolut dari folder tempat file ini berada.
$path_to_functions = __DIR__ . '/config/functions.php';

if (file_exists($path_to_functions)) {
    include $path_to_functions;
} else {
    // Jika file tidak ada, script akan berhenti dan memberitahu Anda
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; margin:20px;'>
            <strong>Critical Error:</strong> File konfigurasi tidak ditemukan!<br>
            Sistem mencari di: <code>" . $path_to_functions . "</code><br><br>
            Pastikan Anda sudah membuat folder <strong>config</strong> dan file <strong>functions.php</strong> sesuai instruksi sebelumnya.
         </div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ticket</title>
    
    <link rel="stylesheet" href="assets/compiled/css/app.css">
    <link rel="stylesheet" href="assets/compiled/css/auth.css"> </head>

<body>
    <nav class="navbar navbar-light bg-light mb-4 shadow-sm">
        <div class="container d-flex justify-content-between">
            <a class="navbar-brand fw-bold" href="index.php">Helpdesk System</a>
            <a href="track_ticket.php" class="btn btn-outline-primary btn-sm">Lacak Ticket</a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-12">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title text-white mb-0">Buat Ticket Baru</h4>
                    </div>
                    <div class="card-body py-4">
                        <form action="process_ticket.php" method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="type" class="form-label fw-bold">Jenis Ticket</label>
                                    <select name="type" id="type" class="form-select" required>
                                        <option value="" disabled selected>-- Pilih Jenis --</option>
                                        <option value="support">Support (Bantuan Teknis)</option>
                                        <option value="payment">Payment (Pembayaran)</option>
                                        <option value="info">Info (Informasi Umum)</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" placeholder="contoh@email.com" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="company" class="form-label fw-bold">Nama Perusahaan</label>
                                    <input type="text" name="company" id="company" class="form-control" placeholder="Nama PT / CV" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label fw-bold">Nama Pembuat</label>
                                    <input type="text" name="name" id="name" class="form-control" placeholder="Nama Lengkap Anda" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label fw-bold">Nomor Telepon</label>
                                    <input type="text" name="phone" id="phone" class="form-control" placeholder="0812..." required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="subject" class="form-label fw-bold">Subject / Judul Masalah</label>
                                    <input type="text" name="subject" id="subject" class="form-control" placeholder="Singkat dan Jelas" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label fw-bold">Detail / Deskripsi</label>
                                    <textarea name="description" id="description" class="form-control" rows="5" placeholder="Jelaskan masalah Anda secara rinci..." required></textarea>
                                </div>

                                <div class="col-md-12 mb-4">
                                    <label for="attachment" class="form-label fw-bold">Lampiran (Optional - Max 2MB)</label>
                                    <input type="file" name="attachment" id="attachment" class="form-control">
                                    <div class="form-text text-muted">Format yang didukung: JPG, PNG, PDF, DOCX.</div>
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <a href="index.php" class="btn btn-light me-2">Batal</a>
                                    <button type="submit" name="submit_ticket" class="btn btn-primary px-5">Submit Ticket</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-5 mb-3 text-muted">
        <small>&copy; <?= date('Y') ?> Helpdesk System</small>
    </div>

</body>
</html>