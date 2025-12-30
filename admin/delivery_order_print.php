<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// Ambil Data Header DO + Data User Pembuat (Sender)
$sql = "SELECT d.*, c.company_name, c.address, 
               u.username as sender_name, u.signature as sender_sign 
        FROM delivery_orders d
        JOIN payments p ON d.payment_id = p.id
        JOIN invoices i ON p.invoice_id = i.id
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        JOIN users u ON d.created_by_user_id = u.id
        WHERE d.id = $id";
$do = $conn->query($sql)->fetch_assoc();
if(!$do) die("DO not found");

$items = $conn->query("SELECT * FROM delivery_order_items WHERE delivery_order_id = $id");

$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Order <?= $do['do_number'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; -webkit-print-color-adjust: exact; }
        @page { margin: 1.5cm; size: A4; }

        /* HEADER */
        .header-table { width: 100%; margin-bottom: 30px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #333; max-width: 300px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 20px; font-weight: bold; text-transform: uppercase; padding-top: 20px; }

        /* INFO BOXES */
        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 120px; }
        .info-spacer { width: 4%; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 80px; } .sep { width: 10px; text-align: center; }

        /* ITEMS TABLE (ORANGE HEADER) */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { 
            border: 1px solid #000; 
            background-color: #ff6b6b; /* Warna sesuai gambar (Merah/Orange) */
            color: white;
            padding: 8px; 
            text-align: center; 
            font-weight: bold;
        }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; text-align: center; }
        .text-left { text-align: left !important; }

        /* FOOTER REMARKS & SIGN */
        .footer-layout { width: 100%; margin-top: 40px; page-break-inside: avoid; }
        .remarks-box { width: 60%; vertical-align: top; font-size: 10px; }
        .sign-box { width: 40%; text-align: center; vertical-align: bottom; }
        
        .sign-img { max-height: 100px; display: block; margin: 0 auto; }
        .sign-line { border-top: 1px solid #000; width: 80%; margin: 5px auto 0 auto; }
        .recipient-label { margin-top: 5px; font-size: 10px; }
    </style>
</head>
<body onload="window.print()">

    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'])) ?></div>
            </td>
            <td align="right" valign="top"><div class="doc-title">DELIVERY ORDER</div></td>
        </tr>
    </table>

    <table class="info-wrapper">
        <tr>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($do['company_name']) ?></strong></td></tr>
                    <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($do['address'])) ?></td></tr>
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($do['pic_name']) ?></td></tr>
                </table>
            </td>
            <td class="info-spacer"></td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">Delivery Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($do['do_date'])) ?></td></tr>
                    <tr><td class="lbl">Delivery No</td><td class="sep">:</td><td><strong><?= $do['do_number'] ?></strong></td></tr>
                    <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= $do['pic_name'] ?></td></tr>
                    <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= $do['pic_phone'] ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Item</th>
                <th width="15%">Content</th>
                <th width="10%">Unit</th>
                <th width="15%">Charge Mode</th>
                <th width="30%">Description</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; $totalUnit = 0;
            while($item = $items->fetch_assoc()): 
                $totalUnit += $item['unit'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td class="text-left"><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= htmlspecialchars($item['content']) ?></td>
                <td><?= $item['unit'] ?></td>
                <td><?= $item['charge_mode'] ?></td>
                <td class="text-left"><?= nl2br(htmlspecialchars($item['description'])) ?></td>
            </tr>
            <?php endwhile; ?>
            <tr>
                <td colspan="3" class="text-right" style="font-weight:bold;">Total</td>
                <td style="font-weight:bold;"><?= $totalUnit ?></td>
                <td></td><td></td>
            </tr>
        </tbody>
    </table>

    <table class="footer-layout">
        <tr>
            <td class="remarks-box">
                <strong>Remarks :</strong>
                <ul style="padding-left: 15px; margin-top: 5px;">
                    <li>Please sign and stamp this delivery order</li>
                    <li>Please send it via email and whatsapp to the number above</li>
                </ul>
            </td>
            <td class="sign-box">
                <div style="height: 80px;"></div> <div class="sign-line"></div>
                <div class="recipient-label">Recipient</div>
            </td>
        </tr>
    </table>

</body>
</html>