<?php
$page_title = "Vendor List";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Logic untuk Simpan/Edit Vendor
if (isset($_POST['save_vendor'])) {
    $is_edit = !empty($_POST['vendor_id']);
    $id = $is_edit ? intval($_POST['vendor_id']) : 0;

    $company_name = $conn->real_escape_string($_POST['company_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $pic_name = $conn->real_escape_string($_POST['pic_name']);

    if ($is_edit) {
        $sql = "UPDATE vendors SET company_name='$company_name', address='$address', pic_name='$pic_name' WHERE id=$id";
        $message = "Vendor Berhasil Diupdate!";
    } else {
        $sql = "INSERT INTO vendors (company_name, address, pic_name) VALUES ('$company_name', '$address', '$pic_name')";
        $message = "Vendor Berhasil Ditambahkan!";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('$message'); window.location='vendor_list.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// Logic untuk Hapus Vendor (Opsional)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM vendors WHERE id=$id";
    if ($conn->query($sql)) {
        echo "<script>alert('Vendor Berhasil Dihapus!'); window.location='vendor_list.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "\\nPastikan tidak ada Purchase Order yang terhubung.'); window.location='vendor_list.php';</script>";
    }
}

$vendors = $conn->query("SELECT * FROM vendors ORDER BY company_name ASC");
?>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Vendor List</h3>
            <p class="text-subtitle text-muted">Daftar rekanan / perusahaan vendor.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <button class="btn btn-primary shadow-sm" onclick="openModal()">
                <i class="bi bi-person-plus-fill me-2"></i> Add New Vendor
            </button>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="table1">
                    <thead class="bg-light">
                        <tr>
                            <th>Company Name</th>
                            <th>Address</th>
                            <th>PIC Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $vendors->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="fw-bold"><?= htmlspecialchars($row['company_name']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['address']) ?></td>
                            <td><?= htmlspecialchars($row['pic_name']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editVendor(<?= json_encode($row) ?>)'>
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <a href="vendor_list.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin ingin menghapus vendor <?= $row['company_name'] ?>?');">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vendorModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Add New Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="vendor_id" id="vendor_id">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Perusahaan</label>
                    <input type="text" name="company_name" id="company_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">PIC (Contact Person)</label>
                    <input type="text" name="pic_name" id="pic_name" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="save_vendor" class="btn btn-primary">Save Data</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    var myModal = new bootstrap.Modal(document.getElementById('vendorModal'));

    function openModal() {
        document.getElementById('vendor_id').value = '';
        document.getElementById('company_name').value = '';
        document.getElementById('pic_name').value = '';
        document.getElementById('address').value = '';
        document.getElementById('modalTitle').innerText = "Add New Vendor";
        myModal.show();
    }

    function editVendor(data) {
        document.getElementById('vendor_id').value = data.id;
        document.getElementById('company_name').value = data.company_name;
        document.getElementById('pic_name').value = data.pic_name;
        document.getElementById('address').value = data.address;
        document.getElementById('modalTitle').innerText = "Edit Vendor Data";
        myModal.show();
    }
</script>