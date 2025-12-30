<?php
require_once '../config/database.php';

if(isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $sql = "SELECT * FROM deliveries WHERE id = $id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    if($row):
?>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted text-uppercase fw-bold">Courier</small>
                            <h5 class="fw-bold text-dark mb-0"><?= strtoupper($row['courier_name']) ?></h5>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted text-uppercase fw-bold">Tracking Number</small>
                            <h5 class="fw-bold text-primary mb-0"><?= $row['tracking_number'] ?></h5>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <small class="text-muted text-uppercase fw-bold">Status</small>
                            <?php 
                                $statusClass = ($row['status'] == 'Delivered') ? 'success' : 'warning';
                            ?>
                            <h5 class="fw-bold text-<?= $statusClass ?> mb-0"><?= $row['status'] ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <h6 class="border-bottom pb-2 text-primary"><i class="bi bi-arrow-up-circle me-2"></i> Sender Details</h6>
            <table class="table table-borderless table-sm">
                <tr><td width="30%" class="text-muted">Name</td><td class="fw-bold"><?= $row['sender_name'] ?></td></tr>
                <tr><td class="text-muted">Company</td><td><?= $row['sender_company'] ?></td></tr>
                <tr><td class="text-muted">Phone</td><td><?= $row['sender_phone'] ?></td></tr>
                <tr><td class="text-muted">Address</td><td><?= $row['sender_address'] ?></td></tr>
            </table>
        </div>

        <div class="col-md-6 mb-3">
            <h6 class="border-bottom pb-2 text-success"><i class="bi bi-arrow-down-circle me-2"></i> Receiver Details</h6>
            <table class="table table-borderless table-sm">
                <tr><td width="30%" class="text-muted">Name</td><td class="fw-bold"><?= $row['receiver_name'] ?></td></tr>
                <tr><td class="text-muted">Company</td><td><?= $row['receiver_company'] ?></td></tr>
                <tr><td class="text-muted">Phone</td><td><?= $row['receiver_phone'] ?></td></tr>
                <tr><td class="text-muted">Address</td><td><?= $row['receiver_address'] ?></td></tr>
            </table>
        </div>

        <div class="col-12">
            <h6 class="border-bottom pb-2 text-dark"><i class="bi bi-box-seam me-2"></i> Item Information</h6>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>Project</th> <th>Item Name</th>
                            <th>Data Package</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Delivery Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php if(!empty($row['project_name'])): ?>
                                    <span class="badge bg-info text-dark">
                                        <i class="bi bi-kanban me-1"></i> <?= $row['project_name'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['item_name'] ?></td>
                            <td><?= $row['data_package'] ?></td>
                            <td class="text-center"><?= $row['qty'] ?></td>
                            <td class="text-end">Rp <?= number_format($row['delivery_price'], 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="col-12 mt-2">
            <div class="d-flex justify-content-between text-muted small">
                <span>Created: <?= date('d M Y H:i', strtotime($row['created_at'])) ?></span>
                <?php if($row['delivered_date']): ?>
                    <span class="text-success fw-bold">Delivered At: <?= date('d M Y H:i', strtotime($row['delivered_date'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
    endif;
}
?>