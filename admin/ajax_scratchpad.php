<?php
session_start();
require_once '../config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$invoice_no = $_POST['invoice_no'] ?? '';

if (empty($invoice_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice No Required']);
    exit;
}

// --- LOAD DATA ---
if ($action == 'load') {
    $stmt = $conn->prepare("SELECT general_notes, calculation_data FROM invoice_scratchpads WHERE invoice_no = ?");
    $stmt->bind_param("s", $invoice_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'data' => $row]);
    } else {
        echo json_encode(['status' => 'success', 'data' => null]); // Belum ada catatan
    }
}

// --- SAVE DATA ---
if ($action == 'save') {
    $notes = $_POST['notes'] ?? '';
    $calc_data = $_POST['calc_data'] ?? '[]'; // JSON string dari tabel hitungan

    // Gunakan INSERT ON DUPLICATE KEY UPDATE agar efisien
    $sql = "INSERT INTO invoice_scratchpads (invoice_no, general_notes, calculation_data) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE general_notes = VALUES(general_notes), calculation_data = VALUES(calculation_data)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $invoice_no, $notes, $calc_data);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Catatan tersimpan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
?>