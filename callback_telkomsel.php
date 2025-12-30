<?php
// FILE: callback_telkomsel.php di ROOT FOLDER
require_once 'admin/config/database.php'; // Sesuaikan path ke database.php

function writeLog($msg) {
    file_put_contents('tsel_callback.log', date('Y-m-d H:i:s') . " | " . $msg . "\n", FILE_APPEND);
}

$rawInput = file_get_contents('php://input');
writeLog("RAW CALLBACK: " . $rawInput);

$data = json_decode($rawInput, true);
$reqId = $data['requestId'] ?? $data['request_id'] ?? null;
$status = $data['status'] ?? null;

if (!$reqId) {
    // Coba baca GET jika POST kosong (kadang Tsel kirim query params)
    $reqId = $_GET['requestId'] ?? $_GET['request_id'] ?? null;
    $status = $_GET['status'] ?? null;
}

if ($reqId) {
    // Normalisasi Status
    // Sukses bisa 'SUCCESS', '00', atau 'true' (string/bool)
    $isSuccess = (strtoupper($status) === 'SUCCESS' || $status === '00' || $status === true);
    
    $finalStatus = $isSuccess ? 'SUCCESS' : 'FAILED';
    $trxId = $data['trx_id'] ?? $data['trxId'] ?? '';
    
    // Update DB
    $stmt = $conn->prepare("UPDATE inject_history SET status = ?, telkomsel_trx_id = ?, callback_received_at = NOW() WHERE request_id = ?");
    $stmt->bind_param("sss", $finalStatus, $trxId, $reqId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        writeLog("Updated $reqId to $finalStatus");
        
        // Update Batch Counter
        $q = $conn->query("SELECT batch_id FROM inject_history WHERE request_id='$reqId'");
        if($q && $q->num_rows > 0) {
            $bid = $q->fetch_assoc()['batch_id'];
            if($isSuccess) {
                $conn->query("UPDATE inject_batches SET success_count = success_count + 1 WHERE id=$bid");
            } else {
                $conn->query("UPDATE inject_batches SET failed_count = failed_count + 1 WHERE id=$bid");
            }
        }
    } else {
        writeLog("ReqID $reqId not found in DB");
    }
} else {
    writeLog("No Request ID in Callback");
}

http_response_code(200);
echo "OK";
?>