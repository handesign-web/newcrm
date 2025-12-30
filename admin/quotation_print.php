<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. UPDATE QUERY: Tambahkan u.signature as sales_sign
$sql = "SELECT q.*, c.company_name, c.address as c_address, c.pic_name, c.pic_phone, 
               u.username as sales_name, u.email as sales_email, u.phone as sales_phone,
               u.signature as sales_sign
        FROM quotations q 
        JOIN clients c ON q.client_id = c.id
        JOIN users u ON q.created_by_user_id = u.id
        WHERE q.id = $id";
$q = $conn->query($sql)->fetch_assoc();

if(!$q) die("Quotation not found");

$items = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = $id");

$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation <?= $q['quotation_no'] ?></title>
    <style>
        /* RESET & BASE */
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            font-size: 11px; 
            margin: 0; 
            padding: 0; 
            color: #000; 
            -webkit-print-color-adjust: exact; 
        }
        @page { margin: 1.5cm; size: A4; }

        /* WATERMARK (3 IMAGES LAYOUT) */
        .watermark-container {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 10;
            display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 350px 0; pointer-events: none;
        }
        .watermark-img { width: 80%; opacity: 0.08; object-fit: contain; max-height: 250px; }

        /* HEADER */
        .header-table { width: 100%; margin-bottom: 20px; border: none; }
        .header-table td { vertical-align: top; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; line-height: 1.3; color: #333; max-width: 300px; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; color: #333; padding-top: 20px; }

        /* INFO BOXES */
        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 140px; }
        .info-spacer { width: 4%; }
        
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .label-col { width: 80px; font-weight: normal; } 
        .sep-col { width: 10px; text-align: center; }

        /* ITEMS TABLE */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 11px; }
        .items-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
        .col-no { width: 5%; text-align: center; }
        .col-item { width: 35%; }
        .col-qty { width: 8%; text-align: center; }
        .col-price { width: 15%; text-align: right; }
        .col-desc { width: 22%; font-size: 10px; }
        .col-mode { width: 15%; text-align: center; }

        /* REMARKS */
        .remarks-section { margin-bottom: 40px; padding-top: 10px; }
        .remarks-title { font-weight: bold; font-size: 11px; margin-bottom: 8px; display: block; text-transform: uppercase; }
        .remarks-content { font-size: 11px; line-height: 1.6; margin-left: 10px; }

        /* SIGNATURE (UPDATED FOR IMAGE) */
        .footer-table { width: 100%; margin-top: 30px; page-break-inside: avoid; }
        .sign-box { 
            width: 250px; 
            float: right; 
            text-align: center; 
        }
        .sign-company { font-size: 11px; font-weight: normal; margin-bottom: 5px; } 
        
        /* Container untuk gambar tanda tangan agar posisinya stabil */
        .sign-image-container {
            height: 80px; 
            display: flex;
            align-items: flex-end; /* Rata bawah */
            justify-content: center;
            margin-bottom: 5px;
        }
        .sign-image {
            max-height: 220px;
            max-width: 370px;
            display: block;
        }
        
        .sign-name { font-size: 12px; font-weight: bold; text-decoration: underline; }

    </style>
</head>
<body onload="window.print()">

    <div class="watermark-container">
        <img src="../uploads/<?= $sets['company_watermark'] ?>" class="watermark-img" onerror="this.style.display='none'">
    </div>

    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo" alt="Logo">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'])) ?></div>
            </td>
            <td align="right" valign="top">
                <div class="doc-title">QUOTATION</div>
            </td>
        </tr>
    </table>

    <br>
    <br>
    <table class="info-wrapper">
        <tr>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="label-col">To</td><td class="sep-col">:</td><td><strong><?= htmlspecialchars($q['company_name']) ?></strong></td></tr>
                    <tr><td class="label-col">Address</td><td class="sep-col">:</td><td><?= nl2br(htmlspecialchars($q['c_address'])) ?></td></tr>
                    <tr><td class="label-col">Attn.</td><td class="sep-col">:</td><td><?= htmlspecialchars($q['pic_name']) ?> (<?= htmlspecialchars($q['pic_phone']) ?>)</td></tr>
                </table>
            </td>
            <td class="info-spacer"></td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="label-col">Quotation</td><td class="sep-col">:</td><td><strong><?= $q['quotation_no'] ?></strong></td></tr>
                    <tr><td class="label-col">Date</td><td class="sep-col">:</td><td><?= date('d/m/Y', strtotime($q['quotation_date'])) ?></td></tr>
                    <tr><td class="label-col">Currency</td><td class="sep-col">:</td><td><?= $q['currency'] ?></td></tr>
                    <tr><td colspan="3" style="height: 5px;"></td></tr> 
                    <tr><td class="label-col">Contact</td><td class="sep-col">:</td><td><?= $q['sales_name'] ?></td></tr>
                    <tr><td class="label-col">Email</td><td class="sep-col">:</td><td><?= $q['sales_email'] ?></td></tr>
                    <tr><td class="label-col">Tel</td><td class="sep-col">:</td><td><?= $q['sales_phone'] ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-item">Item</th>
                <th class="col-qty">Qty</th>
                <th class="col-price">Unit Price (<?= $q['currency'] ?>)</th>
                <th class="col-desc">Description</th>
                <th class="col-mode">Charge Mode</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; while($item = $items->fetch_assoc()): ?>
            <tr>
                <td class="col-no"><?= $no++ ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td class="col-qty"><?= $item['qty'] ?></td>
                <td class="col-price"><?= number_format($item['unit_price'], 0, ',', '.') ?></td>
                <td class="col-desc"><?= htmlspecialchars($item['description']) ?></td>
                <td class="col-mode"><?= $item['charge_mode'] ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if($no < 4): for($i=0; $i<(4-$no); $i++): ?>
            <tr><td style="padding: 15px;">&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; endif; ?>
        </tbody>
    </table>

    <div class="remarks-section">
        <span class="remarks-title">REMARK :</span>
        <div class="remarks-content"><?= nl2br(htmlspecialchars($q['remarks'])) ?></div>
    </div>
    
    <br>
    <br>
    <br>

    <div class="footer-table">
        <div class="sign-box">
            <div class="sign-company">PT. Linksfield Networks Indonesia</div>
            
            <div class="sign-image-container">
                <?php if (!empty($q['sales_sign']) && file_exists('../uploads/' . $q['sales_sign'])): ?>
                    <img src="../uploads/<?= $q['sales_sign'] ?>" class="sign-image" alt="Signature">
                <?php else: ?>
                    <?php endif; ?>
            </div>
            
            <div class="sign-name"><?= htmlspecialchars($q['sales_name']) ?></div>
        </div>
    </div>

</body>
</html>