<?php
// config/functions.php

// 1. INCLUDE DATABASE & AUTOLOAD
// Gunakan require_once agar tidak error double include
require_once __DIR__ . '/database.php';

// Cek autoload dari Composer jika ada
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ==========================================
// FUNGSI KIRIM EMAIL (DB BASED + LOGGING + SSL FIX)
// ==========================================
function sendEmailNotification($to, $subject, $body) {
    global $conn; // Menggunakan koneksi database global

    // 1. Ambil Setting dari Database
    $settings = [];
    $sql = "SELECT setting_key, setting_value FROM settings";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    // Cek apakah setting lengkap
    if (empty($settings['smtp_host']) || empty($settings['smtp_user']) || empty($settings['smtp_pass'])) {
        $errorMsg = date('Y-m-d H:i:s') . " - Error: Setting SMTP belum lengkap di database.\n";
        file_put_contents(__DIR__ . '/email_error.log', $errorMsg, FILE_APPEND);
        return false;
    }

    // 2. Inisialisasi PHPMailer
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $errorMsg = date('Y-m-d H:i:s') . " - Error: Library PHPMailer tidak ditemukan. Pastikan sudah install via Composer.\n";
        file_put_contents(__DIR__ . '/email_error.log', $errorMsg, FILE_APPEND);
        return false;
    }

    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = $settings['smtp_pass']; 
        
        // Security Settings
        $secureType = isset($settings['smtp_secure']) ? strtolower($settings['smtp_secure']) : 'tls';
        if ($secureType == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = isset($settings['smtp_port']) ? intval($settings['smtp_port']) : 587;

        // [PENTING] Bypass SSL Check (Sesuai hasil Debugging yang sukses)
        // Ini membantu jika server hosting memiliki isu sertifikat SSL self-signed
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $fromName = !empty($settings['company_name']) ? $settings['company_name'] : 'Helpdesk System';
        $mail->setFrom($settings['smtp_user'], $fromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Versi teks untuk klien email lama

        $mail->send();
        return true;

    } catch (Exception $e) {
        // CATAT ERROR KE FILE LOG
        $errorMsg = date('Y-m-d H:i:s') . " - Gagal kirim ke $to. Error Info: " . $mail->ErrorInfo . "\n";
        file_put_contents(__DIR__ . '/email_error.log', $errorMsg, FILE_APPEND);
        return false;
    }
}

// ==========================================
// FUNGSI GENERATE ID TICKET (EKSTERNAL)
// ==========================================
function generateTicketID($type, $conn) {
    $prefixMap = ['support' => 'LFID-SUP', 'payment' => 'LFID-PAY', 'info' => 'LFID-INFO'];
    $prefix = isset($prefixMap[$type]) ? $prefixMap[$type] : 'LFID-GEN';
    $date = date('Ymd'); 
    $query = "SELECT ticket_code FROM tickets WHERE ticket_code LIKE '$prefix-$date-%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $parts = explode('-', $row['ticket_code']);
        $lastNum = (int)end($parts); 
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNum = '001';
    }
    return "$prefix-$date-$newNum";
}

// ==========================================
// FUNGSI GENERATE ID TICKET (INTERNAL)
// ==========================================
function generateInternalTicketID($target_division_id, $conn) {
    $res = $conn->query("SELECT code FROM divisions WHERE id = $target_division_id");
    $divCode = ($res->num_rows > 0) ? $res->fetch_object()->code : 'GEN';
    
    $prefix = "LF-INT-$divCode";
    $date = date('Ymd'); 
    
    $query = "SELECT ticket_code FROM internal_tickets WHERE ticket_code LIKE '$prefix-$date-%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $parts = explode('-', $row['ticket_code']);
        $lastNum = (int)end($parts); 
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNum = '001';
    }
    return "$prefix-$date-$newNum";
}

// ==========================================
// FUNGSI DISCORD WEBHOOK (CUSTOMER)
// ==========================================
function sendToDiscord($title, $message, $fields = [], $thread_id = null, $thread_name = null) {
    global $conn;
    
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='discord_webhook'");
    if($res->num_rows > 0){
        $webhookurl = $res->fetch_object()->setting_value;
    } else {
        return false;
    }

    if(empty($webhookurl)) return false;

    // Bersihkan Fields
    $cleanFields = [];
    foreach ($fields as $field) {
        $cleanVal = str_replace(array('\r\n', '\n', '\r'), "\n", $field['value']);
        $cleanVal = stripslashes($cleanVal);
        // Limit karakter agar tidak error
        if(strlen($cleanVal) > 1000) $cleanVal = substr($cleanVal, 0, 1000) . "...";
        $cleanFields[] = ["name" => $field['name'], "value" => $cleanVal, "inline" => isset($field['inline']) ? $field['inline'] : false];
    }

    $embed = [
        "title" => $title,
        "description" => $message,
        "color" => hexdec("3366ff"),
        "fields" => $cleanFields,
        "footer" => ["text" => "Helpdesk System Notification"],
        "timestamp" => date("c")
    ];

    $payload = ["embeds" => [$embed]];

    // [FIX] Logika Forum Channel: Harus ada thread_name jika thread baru
    $finalUrl = $webhookurl . "?wait=true"; 
    
    if ($thread_id) {
        $finalUrl .= "&thread_id=" . $thread_id;
    } else {
        $payload["thread_name"] = $thread_name ? $thread_name : $title;
    }

    return executeCurl($finalUrl, $payload);
}

// ==========================================
// FUNGSI DISCORD KHUSUS INTERNAL (FIXED)
// ==========================================
function sendInternalDiscord($title, $message, $fields = [], $thread_id = null, $thread_name = null) {
    global $conn;
    
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='discord_webhook_internal'");
    if($res->num_rows > 0){
        $webhookurl = $res->fetch_object()->setting_value;
    } else {
        return false;
    }

    if(empty($webhookurl)) return false;

    // Bersihkan Fields
    $cleanFields = [];
    foreach ($fields as $field) {
        $cleanVal = str_replace(array('\r\n', '\n', '\r'), "\n", $field['value']);
        $cleanVal = stripslashes($cleanVal);
        if(strlen($cleanVal) > 1000) $cleanVal = substr($cleanVal, 0, 1000) . "...";
        
        $cleanFields[] = ["name" => $field['name'], "value" => $cleanVal, "inline" => isset($field['inline']) ? $field['inline'] : false];
    }

    $embed = [
        "title" => $title, 
        "description" => $message,
        "color" => hexdec("ff9900"), 
        "fields" => $cleanFields,
        "footer" => ["text" => "Internal System Notification"],
        "timestamp" => date("c")
    ];

    $payload = ["embeds" => [$embed]];
    
    $finalUrl = $webhookurl . "?wait=true";
    
    if ($thread_id) {
        $finalUrl .= "&thread_id=" . $thread_id;
    } else {
        $payload["thread_name"] = $thread_name ? $thread_name : "INTERNAL: " . $title;
    }

    return executeCurl($finalUrl, $payload);
}

// ==========================================
// FUNGSI BANTUAN EKSEKUSI CURL (CORE)
// ==========================================
function executeCurl($url, $payload) {
    $ch = curl_init($url);
    $json_data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // Bypass SSL untuk menghindari error sertifikat di XAMPP
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error_msg = curl_error($ch);
    
    curl_close($ch);

    // LOGIKA PENGECEKAN ERROR & LOGGING
    if ($http_code < 200 || $http_code >= 300) {
        $logMsg = date("Y-m-d H:i:s") . " | Error sending to Discord | Code: $http_code | URL: $url | Response: $body | Curl Error: $error_msg \n";
        file_put_contents(__DIR__ . '/discord_error_log.txt', $logMsg, FILE_APPEND);
        return false; 
    }

    return json_decode($body, true);
}

// ==========================================
// FUNGSI GENERATE NOMOR QUOTATION (QLF)
// ==========================================
function generateQuotationNo($conn) {
    $prefix = "QLF" . date('Ym'); // QLF202510
    $sql = "SELECT quotation_no FROM quotations WHERE quotation_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    if ($res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['quotation_no']; 
        $sequence = intval(substr($lastNo, -4)); 
        $newSeq = $sequence + 1;
    } else {
        $newSeq = 1;
    }
    
    return $prefix . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// FUNGSI GENERATE NOMOR INVOICE (INVLF)
// ==========================================
function generateInvoiceNo($conn) {
    $prefix = "INVLF" . date('Ym'); 
    
    $sql = "SELECT invoice_no FROM invoices WHERE invoice_no LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    if ($res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['invoice_no'];
        $sequence = intval(substr($lastNo, -4));
        $newSeq = $sequence + 1;
    } else {
        $newSeq = 1;
    }
    
    return $prefix . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}

// ==========================================
// FUNGSI GENERATE NOMOR DO (DL)
// ==========================================
function generateDONumber($conn) {
    $prefix = "DL" . date('Ym'); 
    $sql = "SELECT do_number FROM delivery_orders WHERE do_number LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    
    if ($res->num_rows > 0) {
        $lastNo = $res->fetch_assoc()['do_number'];
        $sequence = intval(substr($lastNo, -4));
        $newSeq = $sequence + 1;
    } else {
        $newSeq = 1;
    }
    
    return $prefix . str_pad($newSeq, 4, '0', STR_PAD_LEFT);
}
?>