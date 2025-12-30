<?php
$page_title = "Create Delivery Order";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

$is_edit = false;
$do_data = null;
$do_items = [];
$auto_no = generateDONumber($conn);
$payment_id = 0;

// MODE EDIT
if (isset($_GET['edit_id'])) {
    $is_edit = true;
    $do_id = intval($_GET['edit_id']);
    $do_data = $conn->query("SELECT * FROM delivery_orders WHERE id=$do_id")->fetch_assoc();
    $payment_id = $do_data['payment_id'];
    $auto_no = $do_data['do_number'];
    
    $resItems = $conn->query("SELECT * FROM delivery_order_items WHERE delivery_order_id=$do_id");
    while($row = $resItems->fetch_assoc()) $do_items[] = $row;
} 
// MODE CREATE (FROM PAYMENT)
elseif (isset($_GET['payment_id'])) {
    $payment_id = intval($_GET['payment_id']);
} else {
    echo "<script>window.location='delivery_order_list.php';</script>"; exit;
}

// AMBIL DATA SUMBER (PAYMENT -> INVOICE -> QUOTATION)
// Menambahkan i.payment_method ke dalam query select
$sqlSrc = "SELECT p.id as pay_id, c.company_name, c.address, c.pic_name, c.pic_phone,
           q.id as quote_id, i.payment_method as inv_payment_method
           FROM payments p
           JOIN invoices i ON p.invoice_id = i.id
           JOIN quotations q ON i.quotation_id = q.id
           JOIN clients c ON q.client_id = c.id
           WHERE p.id = $payment_id";
$src = $conn->query($sqlSrc)->fetch_assoc();

// AMBIL DATA ITEM DARI QUOTATION (Untuk Default Item Name)
$quote_items = [];
$resQItems = $conn->query("SELECT * FROM quotation_items WHERE quotation_id = " . $src['quote_id']);
while($row = $resQItems->fetch_assoc()) $quote_items[] = $row;

// Hitung Data SIM yang sudah diupload di Payment ini (Untuk Default Unit)
$countSim = $conn->query("SELECT COUNT(*) as t FROM payment_sim_data WHERE payment_id = $payment_id")->fetch_assoc()['t'];
if($countSim == 0) $countSim = 1; 

// PROSES SIMPAN
if (isset($_POST['save_do'])) {
    $do_no = $_POST['do_number'];
    $do_date = $_POST['do_date'];
    $pic_name = $conn->real_escape_string($_POST['pic_name']);
    $pic_phone = $conn->real_escape_string($_POST['pic_phone']);
    
    if ($is_edit) {
        $conn->query("UPDATE delivery_orders SET do_date='$do_date', pic_name='$pic_name', pic_phone='$pic_phone' WHERE id=$do_id");
        $conn->query("DELETE FROM delivery_order_items WHERE delivery_order_id=$do_id");
        $last_id = $do_id;
    } else {
        $conn->query("INSERT INTO delivery_orders (do_number, payment_id, do_date, pic_name, pic_phone, created_by_user_id) 
                      VALUES ('$do_no', $payment_id, '$do_date', '$pic_name', '$pic_phone', ".$_SESSION['user_id'].")");
        $last_id = $conn->insert_id;
    }

    // Insert Items
    $items = $_POST['item_name'];
    $contents = $_POST['content'];
    $units = $_POST['unit'];
    $modes = $_POST['charge_mode'];
    $descs = $_POST['description'];

    for ($i = 0; $i < count($items); $i++) {
        $in = $conn->real_escape_string($items[$i]);
        $ct = $conn->real_escape_string($contents[$i]);
        $un = intval($units[$i]);
        $cm = $conn->real_escape_string($modes[$i]);
        $ds = $conn->real_escape_string($descs[$i]);
        
        $conn->query("INSERT INTO delivery_order_items (delivery_order_id, item_name, content, unit, charge_mode, description) 
                      VALUES ($last_id, '$in', '$ct', $un, '$cm', '$ds')");
    }
    
    echo "<script>alert('Delivery Order Saved!'); window.location='delivery_order_list.php';</script>";
}
?>

<div class="page-heading"><h3><?= $is_edit ? 'Edit' : 'Create' ?> Delivery Order</h3></div>

<div class="page-content">
    <form method="POST">
        <div class="row">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light"><strong>Receiver Info</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-2"><label>Company</label><input type="text" class="form-control bg-light" value="<?= $src['company_name'] ?>" readonly></div>
                        <div class="mb-2"><label>Address</label><textarea class="form-control bg-light" rows="3" readonly><?= $src['address'] ?></textarea></div>
                        <div class="row">
                            <div class="col-6"><label>Attn Name</label><input type="text" name="pic_name" class="form-control" value="<?= $is_edit ? $do_data['pic_name'] : $src['pic_name'] ?>"></div>
                            <div class="col-6"><label>Phone</label><input type="text" name="pic_phone" class="form-control" value="<?= $is_edit ? $do_data['pic_phone'] : $src['pic_phone'] ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark"><strong>Delivery Details</strong></div>
                    <div class="card-body pt-3">
                        <div class="mb-3"><label>DO Number</label><input type="text" name="do_number" class="form-control fw-bold" value="<?= $auto_no ?>" readonly></div>
                        <div class="mb-3"><label>Delivery Date</label><input type="date" name="do_date" class="form-control" value="<?= $is_edit ? $do_data['do_date'] : date('Y-m-d') ?>"></div>
                        <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> Total SIM Uploaded: <strong><?= $countSim ?></strong> Pcs</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><strong>Items & Description</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="25%">Item (From Quote)</th>
                                <th width="20%">Content (Manual)</th>
                                <th width="10%">Unit</th>
                                <th width="15%">Charge Mode (Inv Payment)</th>
                                <th width="30%">Description (ICCID Range)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($is_edit): ?>
                                <?php foreach($do_items as $it): ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control bg-light" value="<?= $it['item_name'] ?>" readonly></td>
                                    <td><input type="text" name="content[]" class="form-control" value="<?= $it['content'] ?>" placeholder="e.g 5 GB/Month"></td>
                                    <td><input type="number" name="unit[]" class="form-control" value="<?= $it['unit'] ?>"></td>
                                    <td><input type="text" name="charge_mode[]" class="form-control" value="<?= $it['charge_mode'] ?>"></td>
                                    <td><textarea name="description[]" class="form-control" rows="1"><?= $it['description'] ?></textarea></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach($quote_items as $qItem): ?>
                                <tr>
                                    <td><input type="text" name="item_name[]" class="form-control bg-light" value="<?= $qItem['item_name'] ?>" readonly></td>
                                    <td><input type="text" name="content[]" class="form-control" placeholder="e.g 5 GB/Month" required></td>
                                    
                                    <td><input type="number" name="unit[]" class="form-control" value="<?= $countSim ?>"></td>
                                    
                                    <td><input type="text" name="charge_mode[]" class="form-control bg-light" value="<?= $src['inv_payment_method'] ?>" readonly></td>
                                    
                                    <td><textarea name="description[]" class="form-control" rows="1" placeholder="Input range ICCID/Serial Number"></textarea></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" name="save_do" class="btn btn-primary px-4">Save Delivery Order</button>
            </div>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>