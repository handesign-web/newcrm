<?php
$page_title = "Manage Divisions";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

if($_SESSION['role'] != 'admin') die("Access Denied");

// Add Division
if(isset($_POST['add_div'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $code = strtoupper($conn->real_escape_string($_POST['code']));
    $conn->query("INSERT INTO divisions (name, code) VALUES ('$name', '$code')");
    echo "<script>window.location='manage_divisions.php';</script>";
}

$divs = $conn->query("SELECT * FROM divisions");
?>

<div class="page-heading"><h3>Manage Divisions</h3></div>
<div class="page-content">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Add New Division</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Division Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="Ex: Finance">
                        </div>
                        <div class="mb-3">
                            <label>Division Code (Short)</label>
                            <input type="text" name="code" class="form-control" required placeholder="Ex: FIN" maxlength="5">
                        </div>
                        <button type="submit" name="add_div" class="btn btn-primary w-100">Add</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <table class="table table-striped">
                        <thead><tr><th>ID</th><th>Name</th><th>Code</th></tr></thead>
                        <tbody>
                            <?php while($row = $divs->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= $row['name'] ?></td>
                                <td><span class="badge bg-secondary"><?= $row['code'] ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>