<?php
// 1. AKTIFKAN DEBUGGING (Agar pesan error muncul jika ada masalah)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. INCLUDE CONFIG DENGAN PATH ABSOLUT
$configPath = __DIR__ . '/config/functions.php';

if (file_exists($configPath)) {
    include $configPath;
} else {
    die("<strong>Error Critical:</strong> File config/functions.php tidak ditemukan. Pastikan struktur folder benar.");
}

// 3. CEK KONEKSI DATABASE
if (!isset($conn) || $conn->connect_error) {
    die("<strong>Error Database:</strong> Koneksi database gagal. Cek config/database.php.");
}

// 4. PROSES FORM
if (isset($_POST['submit_ticket'])) {
    
    // Sanitasi Input Sederhana
    $type = $conn->real_escape_string($_POST['type']);
    $email = $conn->real_escape_string($_POST['email']);
    $company = $conn->real_escape_string($_POST['company']);
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $description = $conn->real_escape_string($_POST['description']);
    
    // A. Generate ID Unik (Fungsi dari functions.php)
    if (function_exists('generateTicketID')) {
        $ticketCode = generateTicketID($type, $conn);
    } else {
        die("Fungsi generateTicketID tidak ditemukan di functions.php");
    }
    
    // B. Handle File Upload
    $attachment = null;
    $uploadDir = __DIR__ . '/uploads/'; // Path absolut folder uploads
    
    // Buat folder uploads jika belum ada
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowedSize = 2 * 1024 * 1024; // 2MB
        $fileSize = $_FILES['attachment']['size'];
        $fileTmp = $_FILES['attachment']['tmp_name'];
        $originalName = $_FILES['attachment']['name'];
        
        // Cek Ukuran
        if ($fileSize <= $allowedSize) {
            // Rename file agar unik (timestamp_namafile)
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $originalName);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $attachment = $fileName;
            } else {
                echo "<script>alert('Gagal mengupload file. Cek permission folder.');</script>";
            }
        } else {
            echo "<script>alert('File terlalu besar! Maksimal 2MB.');</script>";
        }
    }
    
    // C. Insert Database
    $query = "INSERT INTO tickets (ticket_code, type, email, company, name, phone, subject, description, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("sssssssss", $ticketCode, $type, $email, $company, $name, $phone, $subject, $description, $attachment);
        
        if ($stmt->execute()) {
            
            // D. Kirim Discord Notification
            $discordFields = [
                ["name" => "Ticket ID", "value" => $ticketCode, "inline" => true],
                ["name" => "From", "value" => "$name ($company)", "inline" => true],
                ["name" => "Subject", "value" => $subject],
                ["name" => "Description", "value" => substr($description, 0, 100) . "..."]
            ];
            
            if (function_exists('sendToDiscord')) {
                sendToDiscord("New Ticket Created!", "A new ticket has been submitted.", $discordFields);
            }
            
            // E. Kirim Email ke User
            $emailBody = "Halo $name,\n\nTicket Anda berhasil dibuat dengan ID: $ticketCode.\nAnda dapat melacak statusnya di website kami.";
            if (function_exists('sendEmailNotification')) {
                sendEmailNotification($email, "Ticket Created: $ticketCode", $emailBody);
            }
            
            // REDIRECT SUCCESS
            // Perhatikan: parameter saya ubah jadi track_id agar sesuai dengan track_ticket.php
            echo "<script>
                alert('Ticket Berhasil Dibuat! ID Anda: $ticketCode'); 
                window.location.href = 'track_ticket.php?track_id=$ticketCode';
            </script>";
            exit(); // Pastikan script berhenti setelah redirect
            
        } else {
            // Error saat Execute
            echo "Error Database Execute: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Error saat Prepare
        echo "Error Database Prepare: " . $conn->error;
    }
} else {
    // Jika file diakses langsung tanpa submit form
    header("Location: create_ticket.php");
    exit();
}
?>