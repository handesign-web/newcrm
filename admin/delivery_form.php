<?php
$page_title = "Input Delivery";
include 'includes/header.php';
include 'includes/sidebar.php';
require_once '../config/database.php';

// PROSES SIMPAN
if(isset($_POST['save_delivery'])) {
    $date = $_POST['delivery_date'];
    
    // Item & Project Info
    $item = $_POST['item_name'];
    $proj_id = $_POST['project_id'];
    $proj_name = $_POST['project_name']; // Nama project dari input text
    $pkg = $_POST['data_package'];
    $qty = $_POST['qty'];
    
    // Sender
    $s_id = $_POST['sender_id'];
    $s_comp = $_POST['sender_company'];
    $s_name = $_POST['sender_name'];
    $s_phone = $_POST['sender_phone'];
    $s_addr = $_POST['sender_address'];
    
    // Receiver
    $r_id = $_POST['receiver_id'];
    $r_comp = $_POST['receiver_company'];
    $r_name = $_POST['receiver_name'];
    $r_phone = $_POST['receiver_phone'];
    $r_addr = $_POST['receiver_address'];
    
    // Courier
    $cour = strtolower($_POST['courier_name']);
    $track = $_POST['tracking_number'];
    $price = str_replace(['.', ','], '', $_POST['delivery_price']);

    // 1. Simpan Project Baru jika opsi "New" dipilih & nama diisi
    if($proj_id == 'new' && !empty($proj_name)) {
        // Cek duplikat agar tidak double
        $cekProj = $conn->query("SELECT id FROM projects WHERE name = '$proj_name'");
        if($cekProj->num_rows == 0) {
            $conn->query("INSERT INTO projects (name) VALUES ('$proj_name')");
        }
    }

    // 2. Simpan Kontak Baru (Sender)
    if($s_id == 'new') {
        $conn->query("INSERT INTO delivery_contacts (type, company, name, phone, address) VALUES ('sender', '$s_comp', '$s_name', '$s_phone', '$s_addr')");
    }
    
    // 3. Simpan Kontak Baru (Receiver)
    if($r_id == 'new') {
        $conn->query("INSERT INTO delivery_contacts (type, company, name, phone, address) VALUES ('receiver', '$r_comp', '$r_name', '$r_phone', '$r_addr')");
    }

    // 4. Simpan Transaksi Delivery
    $sql = "INSERT INTO deliveries (delivery_date, item_name, project_name, data_package, qty, sender_company, sender_name, sender_phone, sender_address, receiver_company, receiver_name, receiver_phone, receiver_address, courier_name, tracking_number, delivery_price) 
            VALUES ('$date', '$item', '$proj_name', '$pkg', '$qty', '$s_comp', '$s_name', '$s_phone', '$s_addr', '$r_comp', '$r_name', '$r_phone', '$r_addr', '$cour', '$track', '$price')";
    
    if($conn->query($sql)) {
        echo "<script>alert('Data tersimpan!'); window.location='delivery_list.php';</script>";
    } else {
        echo "<script>alert('Error: ".$conn->error."');</script>";
    }
}

// AMBIL DATA UTK DROPDOWN
$projects = $conn->query("SELECT * FROM projects ORDER BY name ASC");
$senders = $conn->query("SELECT * FROM delivery_contacts WHERE type='sender' ORDER BY name ASC");
$receivers = $conn->query("SELECT * FROM delivery_contacts WHERE type='receiver' ORDER BY name ASC");
?>

<div class="page-heading">
    <h3>Input Delivery Baru</h3>
</div>

<div class="page-content">
    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            
            <h6 class="text-primary border-bottom pb-2 mb-3">Item & Project Information</h6>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Date</label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Project</label>
                    <select name="project_id" id="project_select" class="form-select mb-1" onchange="fillProject(this.value)">
                        <option value="new">+ Create New Project</option>
                        <?php while($p = $projects->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>" data-name="<?= $p['name'] ?>">
                                <?= $p['name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="project_name" id="project_name" class="form-control" placeholder="Type Project Name" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Item Name</label>
                    <input type="text" name="item_name" class="form-control" placeholder="e.g. Modem, SIM Card" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Data Package</label>
                    <input type="text" name="data_package" class="form-control" placeholder="Optional">
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-bold">Qty</label>
                    <input type="number" name="qty" class="form-control" value="1" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 border-end">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Sender Information</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Sender</label>
                        <select name="sender_id" id="sender_select" class="form-select" onchange="fillContact('sender', this.value)">
                            <option value="new">+ Create New Sender</option>
                            <?php while($s = $senders->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>" 
                                    data-comp="<?= $s['company'] ?>" 
                                    data-name="<?= $s['name'] ?>" 
                                    data-phone="<?= $s['phone'] ?>" 
                                    data-addr="<?= $s['address'] ?>">
                                    <?= $s['name'] ?> (<?= $s['company'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-2"><input type="text" name="sender_company" id="s_comp" class="form-control form-control-sm" placeholder="Company"></div>
                    <div class="mb-2"><input type="text" name="sender_name" id="s_name" class="form-control form-control-sm" placeholder="Name" required></div>
                    <div class="mb-2"><input type="text" name="sender_phone" id="s_phone" class="form-control form-control-sm" placeholder="Phone"></div>
                    <div class="mb-2"><textarea name="sender_address" id="s_addr" class="form-control form-control-sm" rows="2" placeholder="Address"></textarea></div>
                </div>

                <div class="col-md-6">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Receiver Information</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Receiver</label>
                        <select name="receiver_id" id="receiver_select" class="form-select" onchange="fillContact('receiver', this.value)">
                            <option value="new">+ Create New Receiver</option>
                            <?php while($r = $receivers->fetch_assoc()): ?>
                                <option value="<?= $r['id'] ?>" 
                                    data-comp="<?= $r['company'] ?>" 
                                    data-name="<?= $r['name'] ?>" 
                                    data-phone="<?= $r['phone'] ?>" 
                                    data-addr="<?= $r['address'] ?>">
                                    <?= $r['name'] ?> (<?= $r['company'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-2"><input type="text" name="receiver_company" id="r_comp" class="form-control form-control-sm" placeholder="Company"></div>
                    <div class="mb-2"><input type="text" name="receiver_name" id="r_name" class="form-control form-control-sm" placeholder="Name" required></div>
                    <div class="mb-2"><input type="text" name="receiver_phone" id="r_phone" class="form-control form-control-sm" placeholder="Phone"></div>
                    <div class="mb-2"><textarea name="receiver_address" id="r_addr" class="form-control form-control-sm" rows="2" placeholder="Address"></textarea></div>
                </div>
            </div>

            <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Delivery Information</h6>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Courier Name</label>
                    <select name="courier_name" class="form-select" required>
                        <option value="jne">JNE</option>
                        <option value="jnt">J&T</option>
                        <option value="sicepat">SiCepat</option>
                        <option value="pos">POS Indonesia</option>
                        <option value="tiki">TIKI</option>
                        <option value="anteraja">AnterAja</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Tracking Number</label>
                    <input type="text" name="tracking_number" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Price (Rp)</label>
                    <input type="number" name="delivery_price" class="form-control" placeholder="0">
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="delivery_list.php" class="btn btn-light me-2">Cancel</a>
                <button type="submit" name="save_delivery" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i> Simpan Data</button>
            </div>
        </div>
    </form>
</div>

<script>
// Logic Sender & Receiver
function fillContact(type, id) {
    const prefix = (type === 'sender') ? 's' : 'r';
    const select = document.getElementById(type + '_select');
    
    if (id === 'new') {
        document.getElementById(prefix + '_comp').value = '';
        document.getElementById(prefix + '_name').value = '';
        document.getElementById(prefix + '_phone').value = '';
        document.getElementById(prefix + '_addr').value = '';
    } else {
        const option = select.options[select.selectedIndex];
        document.getElementById(prefix + '_comp').value = option.getAttribute('data-comp');
        document.getElementById(prefix + '_name').value = option.getAttribute('data-name');
        document.getElementById(prefix + '_phone').value = option.getAttribute('data-phone');
        document.getElementById(prefix + '_addr').value = option.getAttribute('data-addr');
    }
}

// Logic Project (BARU)
function fillProject(id) {
    const input = document.getElementById('project_name');
    if (id === 'new') {
        input.value = '';
        input.focus();
    } else {
        const select = document.getElementById('project_select');
        const name = select.options[select.selectedIndex].getAttribute('data-name');
        input.value = name;
    }
}
</script>

<?php include 'includes/footer.php'; ?>