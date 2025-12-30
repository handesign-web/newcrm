<?php
// admin/includes/tsel_helper.php

// Helper Logger
function apiLogger($msg) {
    $logFile = __DIR__ . '/../../tsel_api_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | " . $msg . "\n", FILE_APPEND);
}

function getTselSetting($conn, $key) {
    $q = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key'");
    return ($q && $q->num_rows > 0) ? $q->fetch_assoc()['setting_value'] : '';
}

function generateTselSignature($apiKey, $secretKey) {
    // SAMA DENGAN BASH: timestamp=$(date -u +%s)
    $timestamp = gmdate('U'); 
    
    // SAMA DENGAN BASH: echo -n "$api_key$secret$timestamp" | md5sum
    $signatureString = $apiKey . $secretKey . $timestamp;
    $signature = md5($signatureString);
    
    return $signature;
}

function callTselApi($conn, $endpoint, $method = 'GET', $data = []) {
    $apiKey = getTselSetting($conn, 'tsel_api_key');
    $secretKey = getTselSetting($conn, 'tsel_secret_key');
    $baseUrl = getTselSetting($conn, 'tsel_base_url'); 
    
    // Generate Signature Baru Setiap Kali Request
    $signature = generateTselSignature($apiKey, $secretKey);
    
    // Pastikan URL bersih
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    // Headers Persis Sesuai cURL
    $headers = [
        "api-key: $apiKey",
        "x-signature: $signature",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        // Pastikan JSON dikirim sebagai string raw
        $jsonData = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        
        apiLogger("REQUEST [POST] $url - Payload: " . $jsonData . " - Sig: $signature");
    } else {
        apiLogger("REQUEST [GET] $url - Sig: $signature");
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    apiLogger("RESPONSE [$httpCode]: " . ($response ?: "Error: $error"));

    if ($error) {
        return ['status' => false, 'message' => "CURL Error: $error"];
    }

    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => false, 
            'message' => "Invalid JSON (HTTP $httpCode)", 
            'raw_response' => $response,
            'http_code' => $httpCode
        ];
    }

    $result['http_code'] = $httpCode; 
    return $result;
}
?>