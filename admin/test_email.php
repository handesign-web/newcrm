<?php
// admin/test_email.php
// Script Debugging untuk Tes Kirim Email

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/header.php';
include 'includes/sidebar.php';
// Load functions (asumsi path benar)
include '../config/functions.php'; 

// Load PHPMailer Manual jika belum
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$msg = "";

if (isset($_POST['send_test'])) {
    $to = $_POST['test_email'];
    $subject = "Test Email Debugging";
    $body = "<h1>Email Test Berhasil!</h1><p>Ini adalah email percobaan dari sistem Helpdesk.</p><p>Waktu kirim: " . date('Y-m-d H:i:s') . "</p>";

    // --- DEBUGGING PROCESS ---
    echo "<div class='card mb-3'><div class='card-header bg-dark text-white'>Logs Debugging</div><div class='card-body'><pre>";
    
    // 1. Cek Setting Database
    global $conn;
    $settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM settings");
    while($r = $res->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];
    
    echo "<b>[INFO] Setting Database:</b>\n";
    echo "Host: " . ($settings['smtp_host'] ?? 'KOSONG') . "\n";
    echo "User: " . ($settings['smtp_user'] ?? 'KOSONG') . "\n";
    echo "Port: " . ($settings['smtp_port'] ?? 'KOSONG') . "\n";
    echo "Secure: " . ($settings['smtp_secure'] ?? 'KOSONG') . "\n";
    
    // 2. Cek Library
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "\n<b style='color:red'>[ERROR] Class PHPMailer TIDAK DITEMUKAN!</b>\n";
        echo "Pastikan Anda sudah menjalankan 'composer install' atau include library manual.\n";
    } else {
        echo "\n<b>[INFO] Class PHPMailer Ditemukan.</b> Memulai proses kirim...\n\n";
        
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Aktifkan Verbose Debug
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_user'];
            $mail->Password   = $settings['smtp_pass'];
            
            if (($settings['smtp_secure'] ?? 'tls') == 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port       = $settings['smtp_port'];
            
            // Bypass SSL (Hanya untuk debugging jika sertifikat bermasalah)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom($settings['smtp_user'], 'Helpdesk Debugger');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            echo "\n<b style='color:green'>[SUCCESS] Email berhasil dikirim!</b>\n";
            $msg = "<div class='alert alert-success'>Test Email Berhasil Dikirim!</div>";
        } catch (Exception $e) {
            echo "\n<b style='color:red'>[ERROR] Gagal Kirim Email:</b>\n" . $mail->ErrorInfo . "\n";
            $msg = "<div class='alert alert-danger'>Test Email Gagal! Lihat log di atas.</div>";
        }
    }
    echo "</pre></div></div>";
}
?>

<div class="page-heading">
    <h3>Email Debugging Tool</h3>
</div>

<div class="page-content">
    <?= $msg ?>
    <div class="card">
        <div class="card-header">Test Konfigurasi SMTP</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group mb-3">
                    <label>Kirim Test Email Ke:</label>
                    <input type="email" name="test_email" class="form-control" placeholder="Masukkan email penerima..." required>
                </div>
                <button type="submit" name="send_test" class="btn btn-primary">Kirim Test Email & Lihat Log</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>