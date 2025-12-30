<?php
$page_title = "Telkomsel Packages";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';
include 'includes/tsel_helper.php';

// PROSES SYNC PACKAGES
if (isset($_POST['sync_packages'])) {
    $subKey = getTselSetting($conn, 'tsel_subscription_key');
    $endpoint = "/get-whitelist-package?subscriptionKey=$subKey&page=1&pageSize=50";
    
    $response = callTselApi($conn, $endpoint, 'GET');

    if (isset($response['status']) && $response['status'] == true && isset($response['data']['denom'])) {
        $count = 0;
        foreach ($response['data']['denom'] as $denom) {
            $dId = $conn->real_escape_string($denom['denomId']);
            $dName = $conn->real_escape_string($denom['denomName']);
            // Ambil detail pertama dari allowance
            $quota = $denom['denomAllowance'][0]['quotaValue'] ?? '0';
            $unit = $denom['denomAllowance'][0]['quotaUnit'] ?? '-';
            $validity = $conn->real_escape_string($denom['denomValidity']);

            $sql = "INSERT INTO tsel_packages (denom_id, denom_name, quota_value, quota_unit, validity) 
                    VALUES ('$dId', '$dName', '$quota', '$unit', '$validity')
                    ON DUPLICATE KEY UPDATE 
                    denom_name='$dName', quota_value='$quota', quota_unit='$unit', validity='$validity'";
            if ($conn->query($sql)) $count++;
        }
        echo "<script>alert('Sync Berhasil! $count paket diperbarui.'); window.location='tsel_packages.php';</script>";
    } else {
        $msg = $response['message'] ?? 'Gagal mengambil data dari Telkomsel.';
        echo "<script>alert('Error: $msg');</script>";
    }
}

// UPDATE SETTINGS (Subscription Key)
if (isset($_POST['update_settings'])) {
    $newKey = $conn->real_escape_string($_POST['sub_key']);
    $conn->query("UPDATE settings SET setting_value='$newKey' WHERE setting_key='tsel_subscription_key'");
    echo "<script>alert('Settings updated!'); window.location='tsel_packages.php';</script>";
}

$packages = $conn->query("SELECT * FROM tsel_packages ORDER BY denom_name ASC");
$currentSubKey = getTselSetting($conn, 'tsel_subscription_key');
?>

<div class="page-heading">
    <h3>Telkomsel Data Packages</h3>
</div>

<div class="page-content">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Subscription Key (API)</label>
                    <input type="text" name="sub_key" class="form-control" value="<?= htmlspecialchars($currentSubKey) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="update_settings" class="btn btn-warning w-100">Update Key</button>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="sync_packages" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Sync API</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="table1">
                    <thead>
                        <tr>
                            <th>Denom Name</th>
                            <th>Quota</th>
                            <th>Validity</th>
                            <th>Denom ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $packages->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold"><?= $row['denom_name'] ?></td>
                            <td><span class="badge bg-info"><?= $row['quota_value'] . ' ' . $row['quota_unit'] ?></span></td>
                            <td><?= $row['validity'] ?></td>
                            <td class="text-muted small"><?= $row['denom_id'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>