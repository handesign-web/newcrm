<?php
include '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

$id = intval($_GET['id']);

// 1. AMBIL DATA HEADER INVOICE
$sql = "SELECT i.*, q.po_number_client, q.currency, q.remarks,
               c.company_name, c.address as c_address, c.pic_name, c.pic_phone,
               u.username as sales_name, u.email as sales_email, u.phone as sales_phone, u.signature as sales_sign
        FROM invoices i
        JOIN quotations q ON i.quotation_id = q.id
        JOIN clients c ON q.client_id = c.id
        JOIN users u ON i.created_by_user_id = u.id
        WHERE i.id = $id";
$inv = $conn->query($sql)->fetch_assoc();
if(!$inv) die("Invoice not found");

// 2. LOGIKA PENGAMBILAN ITEM (DIPERBAIKI)
// Cek apakah ada item khusus yang tersimpan di tabel invoice_items (hasil edit/validasi)
$sql_inv_items = "SELECT * FROM invoice_items WHERE invoice_id = $id";
$check_items = $conn->query($sql_inv_items);

if ($check_items && $check_items->num_rows > 0) {
    // PRIORITAS 1: Gunakan item dari invoice_items (Data yang sudah divalidasi/diedit)
    $items = $check_items;
} else {
    // PRIORITAS 2 (Fallback): Gunakan item asli dari quotation (Untuk invoice manual/legacy)
    $items = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = " . $inv['quotation_id']);
}

// 3. AMBIL SETTINGS
$sets = [];
$res = $conn->query("SELECT * FROM settings");
while($row = $res->fetch_assoc()) $sets[$row['setting_key']] = $row['setting_value'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= $inv['invoice_no'] ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; color: #000; -webkit-print-color-adjust: exact; }
        @page { margin: 1.5cm; size: A4; }

        /* WATERMARK */
        .watermark-container { 
            position: fixed; 
            top: 42%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            width: 80%; 
            z-index: -1000; 
            text-align: center;
            pointer-events: none;
        }
        .watermark-img { 
            width: 100%; 
            opacity: 0.08; 
            height: auto; 
        }

        /* HEADER */
        .header-table { width: 100%; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-addr { font-size: 10px; color: #333; max-width: 300px; line-height: 1.3; }
        .doc-title { text-align: right; font-size: 24px; font-weight: bold; text-transform: uppercase; padding-top: 20px; }

        /* INFO BOXES */
        .info-wrapper { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .info-box { width: 48%; border: 1px solid #000; padding: 10px; vertical-align: top; height: 150px; }
        .info-spacer { width: 4%; }
        .inner-table { width: 100%; font-size: 11px; }
        .inner-table td { padding-bottom: 3px; vertical-align: top; }
        .lbl { width: 90px; } 
        .sep { width: 10px; text-align: center; }

        /* TABLE ITEMS */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* SUMMARY ROW */
        .summary-row td { border: 1px solid #000; padding: 8px; }
        .label-cell { background-color: #f2f2f2; font-weight: bold; text-align: right; }
        .value-cell { text-align: right; font-weight: bold; }
        .border-none { border: none !important; }

        /* FOOTER LAYOUT */
        .footer-layout { width: 100%; margin-top: 20px; page-break-inside: avoid; }
        .footer-left { width: 60%; vertical-align: top; padding-right: 20px; }
        .footer-right { width: 40%; vertical-align: top; text-align: center; padding-top: 200px; }

        /* PAYMENT & NOTE */
        .note { font-style: italic; font-size: 10px; margin-bottom: 20px; }
        .payment-info { font-size: 11px; }
        .payment-title { font-weight: bold; margin-bottom: 5px; display: block; }
        .payment-details { line-height: 1.5; white-space: pre-line; }

        /* SIGNATURE */
        .sign-company { font-size: 11px; font-weight: normal; margin-bottom: 10px; }
        .sign-img { max-height: 120px; max-width: 100%; display: block; margin: 0 auto 5px auto; }
        .sign-name { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body onload="window.print()">

    <!-- Watermark -->
    <div class="watermark-container">
        <img src="../uploads/<?= $sets['company_watermark'] ?>" class="watermark-img" onerror="this.style.display='none'">
    </div>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <img src="../uploads/<?= $sets['company_logo'] ?>" class="logo">
                <div class="company-addr"><?= nl2br(htmlspecialchars($sets['company_address_full'])) ?></div>
            </td>
            <td align="right" valign="top"><div class="doc-title">INVOICE</div></td>
        </tr>
    </table>

    <br>

    <!-- Info Boxes -->
    <table class="info-wrapper">
        <tr>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">To</td><td class="sep">:</td><td><strong><?= htmlspecialchars($inv['company_name']) ?></strong></td></tr>
                    <tr><td class="lbl">Address</td><td class="sep">:</td><td><?= nl2br(htmlspecialchars($inv['c_address'])) ?></td></tr>
                    <tr><td class="lbl">Attn.</td><td class="sep">:</td><td><?= htmlspecialchars($inv['pic_name']) ?> (<?= htmlspecialchars($inv['pic_phone']) ?>)</td></tr>
                </table>
            </td>
            <td class="info-spacer"></td>
            <td class="info-box">
                <table class="inner-table">
                    <tr><td class="lbl">Invoice Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td></tr>
                    <tr><td class="lbl">Due Date</td><td class="sep">:</td><td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td></tr>
                    <tr><td class="lbl">Invoice Number</td><td class="sep">:</td><td><strong><?= $inv['invoice_no'] ?></strong></td></tr>
                    <tr><td class="lbl">PO. Reference</td><td class="sep">:</td><td><?= $inv['po_number_client'] ?></td></tr>
                    <tr><td class="lbl">Currency</td><td class="sep">:</td><td><?= $inv['currency'] ?></td></tr>
                    <tr><td colspan="3" style="height:5px"></td></tr>
                    <tr><td class="lbl">Contact</td><td class="sep">:</td><td><?= $inv['sales_name'] ?></td></tr>
                    <tr><td class="lbl">Email</td><td class="sep">:</td><td><?= $inv['sales_email'] ?></td></tr>
                    <tr><td class="lbl">Tel</td><td class="sep">:</td><td><?= $inv['sales_phone'] ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Description</th>
                <th width="8%">Qty</th>
                <th width="17%">Payment Method</th>
                <th width="15%">Unit Price (<?= $inv['currency'] ?>)</th>
                <th width="20%">Total (<?= $inv['currency'] ?>)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; $grandTotal = 0;
            // Loop items (menggunakan data dari logika prioritas di atas)
            while($item = $items->fetch_assoc()): 
                $lineTotal = $item['qty'] * $item['unit_price'];
                $grandTotal += $lineTotal;
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <?= htmlspecialchars($item['item_name']) ?> 
                    <?php if(!empty($item['description'])): ?>
                        <br><small class="text-muted"><?= nl2br(htmlspecialchars($item['description'])) ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $item['qty'] ?></td>
                <td class="text-center"><?= $inv['payment_method'] ?></td>
                <td class="text-right"><?= number_format($item['unit_price'], 0, ',', '.') ?></td>
                <td class="text-right"><?= number_format($lineTotal, 0, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
            
            <?php 
                $vatAmount = $grandTotal * 0.11; // 11% VAT
                $totalAll = $grandTotal + $vatAmount;
            ?>
            
            <!-- Summary Rows -->
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell">Sub Total</td>
                <td class="value-cell"><?= number_format($grandTotal, 0, ',', '.') ?></td>
            </tr>
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell">VAT (11%)</td>
                <td class="value-cell"><?= number_format($vatAmount, 0, ',', '.') ?></td>
            </tr>
            <tr class="summary-row">
                <td colspan="4" class="border-none"></td>
                <td class="label-cell">Total</td>
                <td class="value-cell"><?= number_format($totalAll, 0, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Footer -->
    <table class="footer-layout">
        <tr>
            <td class="footer-left">
                <div class="note">
                    <strong>Note :</strong><br>
                    <?= nl2br(htmlspecialchars($sets['invoice_note_default'] ?? '')) ?>
                </div>

                <div class="payment-info">
                    <span class="payment-title">Payment Method</span>
                    <div class="payment-details">
                        <?= htmlspecialchars($sets['invoice_payment_info'] ?? '') ?>
                    </div>
                </div>
            </td>

            <td class="footer-right">
                <div class="sign-company">PT. Linksfield Networks Indonesia</div>
                <?php if (!empty($inv['sales_sign']) && file_exists('../uploads/' . $inv['sales_sign'])): ?>
                    <img src="../uploads/<?= $inv['sales_sign'] ?>" class="sign-img">
                <?php else: ?>
                    <div style="height: 100px;"></div>
                <?php endif; ?>
                <div class="sign-name"><?= htmlspecialchars($inv['sales_name']) ?></div>
            </td>
        </tr>
    </table>

</body>
</html>
