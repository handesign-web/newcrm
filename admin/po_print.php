<?php
// =========================================================================
// SETUP AWAL DAN KONEKSI DATABASE
// =========================================================================

require_once '../config/database.php'; 

$items = false; 

if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in po_print.php: " . ($conn->connect_error ?? 'Connection object not set'));
    http_response_code(500);
    die("Internal Server Error: Database connection is not available.");
}

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access Denied: Please login.");
}

$id = intval($_GET['id']);
if ($id <= 0) {
    http_response_code(400);
    die("Invalid Purchase Order ID.");
}

// 1. Query Header PO
$sql = "SELECT 
            po.*, 
            v.company_name as vendor_name, 
            v.address as v_address, 
            v.pic_name, 
            u.username as created_by_name, 
            u.email as created_by_email, 
            u.phone as created_by_phone, 
            u.signature as user_signature,
            s.setting_value AS company_address,
            s2.setting_value AS company_logo_path
        FROM purchase_orders po
        LEFT JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN users u ON po.created_by_user_id = u.id
        LEFT JOIN settings s ON s.setting_key = 'company_address_full'
        LEFT JOIN settings s2 ON s2.setting_key = 'company_logo'
        WHERE po.id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("FATAL: Prepared statement failed for PO header. Error: " . $conn->error);
    http_response_code(500);
    die("Internal Server Error: Failed to retrieve Purchase Order header data.");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$po = $result->fetch_assoc();
$stmt->close();

if(!$po) {
    http_response_code(404);
    die("Purchase Order not found");
}

$po['pic_phone'] = ''; 

// 2. Query Item PO & Hitung Total Manual
$items_sql = "SELECT qty, unit_price, charge_mode, description 
              FROM po_items 
              WHERE po_id = ?";
              
$items_stmt = $conn->prepare($items_sql);
$items_data = [];
$calculated_total = 0;

if ($items_stmt) {
    $items_stmt->bind_param("i", $po['id']);
    if ($items_stmt->execute()) {
        $res = $items_stmt->get_result();
        while($row = $res->fetch_assoc()) {
            // Hitung total per baris
            $qty = floatval($row['qty']);
            $price = floatval($row['unit_price']);
            $line_total = $qty * $price;
            
            $row['line_total'] = $line_total;
            $calculated_total += $line_total;
            $items_data[] = $row;
        }
    }
    $items_stmt->close();
}

// 3. Perhitungan VAT (Unit Price * 11%)
// Sesuai permintaan: Total adalah jumlah unit price * qty
// VAT adalah 11% dari Total tersebut.
$sub_total = $calculated_total;
$vat_rate = 0.11;
$vat_amount = $sub_total * $vat_rate;
$grand_total = $sub_total + $vat_amount;

// Format Tanggal
$po_date_formatted = date('d/m/Y', strtotime($po['po_date'] ?? date('Y-m-d')));

// Format Uang
function format_money($amount) {
    return number_format($amount, 0, ',', '.');
}

$company_address = nl2br(htmlspecialchars($po['company_address'] ?? 'Menara Bidakara 2, 18 Floor<br>Jl. Jendral Gatot Subroto No.Kav. 71-73, RT.8/RW.8,<br>Menteng Dalam, Kec. Tebet, Kota Jakarta Selatan,<br>Daerah Khusus Ibukota Jakarta 12870'));
$company_logo_path = $po['company_logo_path'] ?? 'default_logo.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order <?= htmlspecialchars($po['po_number']) ?></title>
    <style>
        /* ======================================= */
        /* CSS REVISI 100% SESUAI GAMBAR */
        /* ======================================= */
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; color: #000; -webkit-print-color-adjust: exact; }
        @page { margin: 1cm 1.5cm; size: A4; }

        .container { width: 100%; margin: 0 auto; }
        
        /* HEADER */
        .header-table { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo-box { width: 60%; vertical-align: top; }
        .header-right { width: 40%; vertical-align: top; text-align: right; }
        
        .logo { max-height: 50px; float: left; margin-right: 15px; margin-top: 0; } 
        .company-info { overflow: hidden; }
        .company-name { font-weight: bold; font-size: 11px; margin-top: 5px; line-height: 1.2; text-transform: uppercase; }
        .company-addr { font-size: 9px; color: #000; line-height: 1.3; margin-top: 2px; }
        
        .doc-title { text-align: right; font-size: 20px; font-weight: bold; text-transform: uppercase; color: #000; padding-top: 15px; }

        /* INFO BOXES */
        .info-wrapper { 
            width: 100%; 
            border: 1px solid #000; /* Border keliling */
            margin-bottom: 25px; 
            border-collapse: collapse; 
        }
        .info-col { width: 50%; vertical-align: top; padding: 5px 8px; }
        .info-col-left { border-right: 1px solid #000; } /* Garis tengah */
        
        .inner-table { width: 100%; font-size: 10px; line-height: 1.4; }
        .inner-table td { padding: 1px 0; vertical-align: top; }
        .lbl { width: 70px; } /* Label tidak bold di contoh gambar */
        .sep { width: 10px; text-align: center; }
        .val { font-weight: normal; }
        
        /* ITEMS TABLE */
        .items-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 0; 
            font-size: 10px; 
            border: 1px solid #000; 
        }
        .items-table th { 
            border: 1px solid #000; 
            background-color: #fff; /* Header putih */
            padding: 5px; 
            text-align: center; 
            font-weight: bold; 
        }
        .items-table td { 
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            padding: 5px; 
            vertical-align: middle; 
        }
        
        /* Lebar Kolom */
        .col-no { width: 5%; text-align: center; }
        .col-desc { width: 35%; text-align: center; } /* Judul kolom center */
        .col-qty { width: 8%; text-align: center; }
        .col-price { width: 17%; text-align: center; }
        .col-total { width: 17%; text-align: center; }
        .col-mode { width: 18%; text-align: center; }

        /* Data Alignment */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; padding-left: 5px; }

        /* TOTAL SECTION (Menyatu dengan tabel item) */
        .total-row td {
            border-top: 1px solid #000;
            padding: 3px 5px;
        }
        .total-label {
            text-align: right;
            font-weight: bold;
            border-right: 1px solid #000;
        }
        .total-value {
            text-align: right;
            font-weight: bold;
        }
        
        /* Mata uang di kiri, angka di kanan */
        .currency-float { float: left; margin-right: 5px; }

        /* SIGNATURE AREA */
        .signature-section { 
            width: 100%; 
            margin-top: 40px; 
            text-align: right; 
        }
        .signature-box { 
            display: inline-block; 
            text-align: center; 
            width: 220px; 
            margin-right: 20px;
        }
        .sig-company { 
            font-weight: bold; 
            font-size: 9px; 
            margin-bottom: 5px; 
            text-transform: uppercase; 
        }
        .sig-label { font-size: 9px; margin-bottom: 10px; }
        .sig-img { 
            height: 70px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 2px; 
        }
        .sig-img img { max-height: 100%; max-width: 100%; }
        .sig-name { 
            font-weight: bold; 
            font-size: 10px; 
            border-bottom: 1px solid #000; 
            display: inline-block; 
            min-width: 120px; 
            padding-bottom: 1px;
        }
    </style>
</head>
<body onload="window.print()">

    <div class="container">
        <table class="header-table">
            <tr>
                <td class="logo-box">
                    <img src="../uploads/<?= htmlspecialchars($company_logo_path) ?>" class="logo">
                    <div class="company-info">
                        <div class="company-name">PT. LINKSFIELD NETWORKS INDONESIA</div>
                        <div class="company-addr"><?= $company_address ?></div>
                    </div>
                </td>
                <td class="header-right">
                    <div class="doc-title">PURCHASE ORDER</div>
                </td>
            </tr>
        </table>

        <table class="info-wrapper">
            <tr>
                <td class="info-col info-col-left">
                    <table class="inner-table">
                        <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($po['vendor_name'] ?? '') ?></strong></td></tr>
                        <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($po['v_address'] ?? '')) ?></td></tr>
                        <tr><td class="lbl">Attn</td><td class="sep">:</td><td><?= htmlspecialchars($po['pic_name'] ?? '') ?></td></tr>
                    </table>
                </td>
                <td class="info-col">
                    <table class="inner-table">
                        <tr><td class="lbl">PO Date</td><td class="sep">:</td><td><?= $po_date_formatted ?></td></tr>
                        <tr><td class="lbl">PO No.</td><td class="sep">:</td><td><strong><?= htmlspecialchars($po['po_number'] ?? '') ?></strong></td></tr>
                        <tr><td class="lbl">Currency</td><td class="sep">:</td><td><?= htmlspecialchars($po['currency'] ?? 'IDR') ?></td></tr>
                        <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= htmlspecialchars($po['created_by_name'] ?? '') ?></td></tr>
                        <tr><td class="lbl">Email</td><td class="sep">:</td><td><?= htmlspecialchars($po['created_by_email'] ?? '') ?></td></tr>
                        <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= htmlspecialchars($po['created_by_phone'] ?? '') ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-desc">Description</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Unit Price (Rp)</th>
                    <th class="col-total">Total (Rp)</th>
                    <th class="col-mode">Charge Mode</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                // Loop Item
                if (!empty($items_data)): 
                    foreach($items_data as $item): 
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-left"><?= htmlspecialchars($item['description'] ?? '') ?></td>
                    <td class="text-center"><?= number_format($item['qty'], 0, ',', '.') ?></td>
                    <td class="text-right">
                        <span class="currency-float">Rp</span>
                        <?= format_money($item['unit_price']) ?>
                    </td>
                    <td class="text-right">
                        <span class="currency-float">Rp</span>
                        <?= format_money($item['line_total']) ?>
                    </td>
                    <td class="text-center"><?= htmlspecialchars($item['charge_mode'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">- No Items -</td>
                </tr>
                <?php endif; ?>

                <tr class="total-row">
                    <td colspan="3" rowspan="3" style="vertical-align: top; border-bottom: 1px solid #000; border-right: 1px solid #000;">
                        <div style="font-weight: bold; margin-bottom: 5px;">Remarks:</div>
                        <?= nl2br(htmlspecialchars($po['remarks'] ?? '')) ?>
                    </td>
                    
                    <td class="total-label">Total</td>
                    <td class="total-value">
                        <span class="currency-float">Rp</span>
                        <?= format_money($sub_total) ?>
                    </td>
                    <td style="border: none; border-left: 1px solid #000;"></td>
                </tr>

                <tr class="total-row">
                    <td class="total-label">VAT 11%</td>
                    <td class="total-value">
                        <span class="currency-float">Rp</span>
                        <?= format_money($vat_amount) ?>
                    </td>
                    <td style="border: none; border-left: 1px solid #000;"></td>
                </tr>

                <tr class="total-row">
                    <td class="total-label">Grand Total</td>
                    <td class="total-value">
                        <span class="currency-float">Rp</span>
                        <?= format_money($grand_total) ?>
                    </td>
                    <td style="border: none; border-left: 1px solid #000;"></td>
                </tr>
            </tbody>
        </table>

        <div class="signature-section">
            <div class="signature-box">
                <div class="sig-company">PT. LINKSFIELD NETWORKS INDONESIA</div>
                <div class="sig-label">Prepared by,</div>
                
                <div class="sig-img">
                    <?php if (!empty($po['user_signature']) && file_exists('../uploads/' . $po['user_signature'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($po['user_signature']) ?>" alt="Signature">
                    <?php else: ?>
                        <div style="height: 60px;"></div>
                    <?php endif; ?>
                </div>
                
                <div class="sig-company">PT LINKSFIELD NETWORKS INDONESIA</div>
                <div class="sig-name"><?= htmlspecialchars($po['created_by_name'] ?? '') ?></div>
            </div>
        </div>

    </div>

</body>
</html>