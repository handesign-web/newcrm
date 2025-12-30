<?php
// admin/tsel_process_ajax.php
session_start();

// Matikan output error ke layar agar JSON bersih
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

include '../config/database.php';
include 'includes/tsel_helper.php';

// Bersihkan buffer sebelum kirim header
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

try {
    // --- 1. GET BATCH LIST ---
    if ($action == 'get_batches') {
        $cid = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $sql = "SELECT batch_name, COUNT(*) as total_items 
                FROM inject_staging 
                WHERE client_id = $cid 
                GROUP BY batch_name 
                ORDER BY uploaded_at DESC";
        $res = $conn->query($sql);
        $batches = [];
        if ($res) {
            while($row = $res->fetch_assoc()) {
                $batches[] = [
                    'batch_name' => $row['batch_name'],
                    'info' => " (Total: {$row['total_items']})"
                ];
            }
        }
        echo json_encode($batches); exit;
    }

    // --- 2. GET STAGING DATA ---
    if ($action == 'get_staging_data') {
        $cid = intval($_POST['client_id']);
        $batch = $conn->real_escape_string($_POST['batch_name']);
        $sql = "SELECT id, msisdn, package_name, denom_id 
                FROM inject_staging 
                WHERE client_id = $cid AND batch_name = '$batch'";
        $res = $conn->query($sql);
        $data = [];
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        echo json_encode($data); exit;
    }

    // --- 3. CREATE BATCH REAL ---
    if ($action == 'create_batch') {
        $code = $conn->real_escape_string($_POST['batch_code']);
        $client = intval($_POST['client_id']);
        $total = intval($_POST['total']);
        $uid = $_SESSION['user_id'];
        $conn->query("INSERT INTO inject_batches (batch_code, client_id, total_numbers, created_by) VALUES ('$code', $client, $total, $uid)");
        echo json_encode(['status' => 'success', 'batch_id' => $conn->insert_id]); exit;
    }

    // --- 4. INJECT SINGLE (UPDATED LOGIC: LANGSUNG SUCCESS) ---
    if ($action == 'inject_single') {
        $stagingId = intval($_POST['staging_id'] ?? 0);
        $batchId = intval($_POST['batch_id']);
        $msisdn = $conn->real_escape_string($_POST['msisdn']);
        $denomName = $conn->real_escape_string($_POST['package']);
        
        $subKey = getTselSetting($conn, 'tsel_subscription_key');
        $payload = [
            "subscriptionKey" => $subKey,
            "denomName"       => $denomName,
            "targetMsisdn"    => $msisdn,
            "deliveryType"    => "Instant"
        ];

        // Call API
        $resp = callTselApi($conn, '/create-gift-request', 'POST', $payload);
        
        // Logika Response
        $isSuccess = isset($resp['status']) && $resp['status'] === true;
        $reqId = $resp['data']['requestId'] ?? ('ERR-' . time() . '-' . rand(100,999));
        $rawResp = json_encode($resp);
        
        // --- PERUBAHAN UTAMA DI SINI ---
        // Jika API return status:true dan ada requestId, kita anggap SUCCESS langsung
        // tanpa menunggu callback.
        
        $apiStatus = 'FAILED';
        $errCode = '';
        $errMsg = '';

        if ($isSuccess) {
            $apiStatus = 'SUCCESS'; // Langsung SUCCESS
        } else {
            $apiStatus = 'FAILED';
            $errCode = $resp['errorCode'] ?? $resp['error_code'] ?? '';
            $errMsg  = $resp['errorMessage'] ?? $resp['error_message'] ?? $resp['message'] ?? 'Unknown Error';
        }

        // Simpan ke Database
        $stmt = $conn->prepare("INSERT INTO inject_history (batch_id, msisdn, denom_name, request_id, status, api_response, error_code, error_message, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssssss", $batchId, $msisdn, $denomName, $reqId, $apiStatus, $rawResp, $errCode, $errMsg);
        $stmt->execute();

        // Update Staging (Opsional)
        if($stagingId > 0) {
            $conn->query("UPDATE inject_staging SET status='PROCESSED' WHERE id=$stagingId");
        }

        if ($isSuccess) {
            // Update counter success batch
            $conn->query("UPDATE inject_batches SET success_count = success_count + 1 WHERE id = $batchId");
            echo json_encode(['status' => 'success', 'req_id' => $reqId]);
        } else {
            // Update counter failed batch
            $conn->query("UPDATE inject_batches SET failed_count = failed_count + 1 WHERE id = $batchId");
            echo json_encode(['status' => 'failed', 'message' => "$errMsg (Code: $errCode)"]);
        }
        exit;
    }

    // --- 5. GET BALANCE INFO ---
    if ($action == 'get_balance') {
        $subKey = getTselSetting($conn, 'tsel_subscription_key');
        if (empty($subKey)) { echo json_encode(['status' => 'failed', 'message' => 'Key Config Missing']); exit; }

        $endpoint = "/get-balance?subscriptionKey=" . $subKey;
        $resp = callTselApi($conn, $endpoint, 'GET');

        if (isset($resp['status']) && $resp['status'] === true && isset($resp['data'])) {
            $d = $resp['data'];
            $unit = $d['unit'] ?? 'MB';
            $divider = ($unit == 'MB') ? 1024 : 1; 

            $data = [
                'packageName' => $d['servicePackageName'] ?? '-',
                'pilotMsisdn' => $d['pilotCharge'] ?? '-',
                'status'      => $d['status'] ?? '-',
                'expiryDate'  => isset($d['expiryDate']) ? date('d M Y, H:i', strtotime($d['expiryDate'])) : '-',
                'totalQuota'  => round(($d['limitUsage'] ?? 0) / $divider, 2),
                'usage'       => round(($d['usage'] ?? 0) / $divider, 2),
                'balance'     => round(($d['balance'] ?? 0) / $divider, 2)
            ];
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'failed', 'message' => $resp['message'] ?? 'Error fetching balance']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>