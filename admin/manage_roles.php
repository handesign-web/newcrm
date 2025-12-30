<?php
$page_title = "Manage Access Permissions";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 1. PROSES SIMPAN PERMISSION ---
if (isset($_POST['save_permissions'])) {
    $divisionId = intval($_POST['division_id']);
    $selectedMenus = $_POST['menus'] ?? [];

    // Hapus permission lama
    $conn->query("DELETE FROM division_permissions WHERE division_id = $divisionId");

    // Insert permission baru
    if (!empty($selectedMenus)) {
        $stmt = $conn->prepare("INSERT INTO division_permissions (division_id, menu_id) VALUES (?, ?)");
        foreach ($selectedMenus as $menuId) {
            $stmt->bind_param("ii", $divisionId, $menuId);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    echo "<script>alert('Permissions updated successfully!'); window.location='manage_roles.php?division_id=$divisionId';</script>";
}

// --- 2. AMBIL DATA DIVISI ---
$divisions = $conn->query("SELECT * FROM divisions ORDER BY id ASC");

// Divisi yang sedang diedit (Default: 1/IT jika tidak ada parameter)
$activeDivId = isset($_GET['division_id']) ? intval($_GET['division_id']) : 1; 

// Ambil Nama Divisi Aktif
$activeDivName = '';
$dNameQ = $conn->query("SELECT name FROM divisions WHERE id = $activeDivId");
if($dNameQ->num_rows > 0) {
    $activeDivName = $dNameQ->fetch_assoc()['name'];
}

// --- 3. AMBIL SEMUA MENU & STATUS PERMISSION ---
// Kita ambil SEMUA menu, lalu cek apakah menu tersebut ada di tabel permission untuk divisi aktif
$sqlMenus = "SELECT m.*, 
            (SELECT COUNT(*) FROM division_permissions dp WHERE dp.division_id = $activeDivId AND dp.menu_id = m.id) as is_permitted
            FROM menus m 
            ORDER BY m.sort_order ASC";
$menusRes = $conn->query($sqlMenus);

// --- 4. SUSUN MENU JADI TREE (PARENT -> CHILDREN) ---
$menuTree = [];
// Langkah A: Masukkan semua menu ke array asosiatif berdasarkan key
$allMenus = [];
if ($menusRes) {
    while($row = $menusRes->fetch_assoc()) {
        $row['children'] = []; // Siapkan tempat untuk anak
        $allMenus[$row['menu_key']] = $row;
    }
}

// Langkah B: Susun Parent-Child
foreach ($allMenus as $key => &$menu) {
    if (!empty($menu['parent_menu'])) {
        // Jika punya parent, masukkan diri sendiri ke array children milik parent
        if (isset($allMenus[$menu['parent_menu']])) {
            $allMenus[$menu['parent_menu']]['children'][] = $menu;
        }
    } else {
        // Jika tidak punya parent, masukkan ke root tree
        $menuTree[] = &$menu;
    }
}
unset($menu); // Bersihkan reference
?>

<div class="page-heading">
    <h3>Manage Access Permissions</h3>
    <p class="text-subtitle text-muted">Atur hak akses menu sidebar untuk setiap Divisi.</p>
</div>

<div class="page-content">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white fw-bold">Select Division</div>
                <div class="list-group list-group-flush">
                    <?php while($d = $divisions->fetch_assoc()): ?>
                        <a href="?division_id=<?= $d['id'] ?>" class="list-group-item list-group-item-action <?= ($activeDivId == $d['id']) ? 'active bg-light text-primary fw-bold' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($d['name']) ?></span>
                                <span class="badge bg-secondary"><?= $d['code'] ?></span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                    <h5 class="m-0 text-primary">Access Control for: <span class="fw-bold text-dark"><?= htmlspecialchars($activeDivName) ?></span></h5>
                    
                    <button type="submit" form="permForm" name="save_permissions" class="btn btn-success px-4">
                        <i class="bi bi-save me-2"></i> Save Changes
                    </button>
                </div>
                
                <div class="card-body p-0">
                    <form id="permForm" method="POST">
                        <input type="hidden" name="division_id" value="<?= $activeDivId ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3">Menu Name</th>
                                        <th class="text-center" width="15%">Allow Access</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($menuTree)): ?>
                                        <tr><td colspan="2" class="text-center p-4 text-muted">Belum ada data menu di database.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($menuTree as $parent): ?>
                                            <tr class="table-secondary fw-bold">
                                                <td class="ps-4">
                                                    <i class="<?= htmlspecialchars($parent['icon']) ?> me-2"></i> 
                                                    <?= htmlspecialchars($parent['menu_label']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input class="form-check-input parent-check" type="checkbox" 
                                                               name="menus[]" value="<?= $parent['id'] ?>"
                                                               data-key="<?= $parent['menu_key'] ?>"
                                                               <?= ($parent['is_permitted'] > 0) ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <?php if(!empty($parent['children'])): ?>
                                                <?php foreach($parent['children'] as $child): ?>
                                                <tr>
                                                    <td class="ps-5 border-start ms-4">
                                                        <i class="bi bi-arrow-return-right me-2 text-muted"></i>
                                                        <?= htmlspecialchars($child['menu_label']) ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input class="form-check-input child-check child-<?= $parent['menu_key'] ?>" type="checkbox" 
                                                                   name="menus[]" value="<?= $child['id'] ?>"
                                                                   <?= ($child['is_permitted'] > 0) ? 'checked' : '' ?>>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const parentChecks = document.querySelectorAll('.parent-check');
        
        parentChecks.forEach(parent => {
            parent.addEventListener('change', function() {
                const key = this.getAttribute('data-key');
                const children = document.querySelectorAll('.child-' + key);
                
                // Jika Parent dicentang -> Anak ikut dicentang
                // Jika Parent di-uncheck -> Anak ikut di-uncheck
                children.forEach(child => {
                    child.checked = this.checked;
                });
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>