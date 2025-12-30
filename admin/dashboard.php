<?php
$page_title = "Dashboard Utama";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 0. DATA USER ---
$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];
$uData = $conn->query("SELECT division_id, username FROM users WHERE id = $current_user_id")->fetch_assoc();
$current_div_id = $uData['division_id'];

// =================================================================================
// LOGIKA 1: ADMIN VIEW (FULL DATA)
// =================================================================================
if ($current_role == 'admin') {
    
    // A. STATISTIK EXTERNAL TICKETS
    $extStats = ['open'=>0, 'progress'=>0, 'closed'=>0, 'total'=>0];
    $sqlExt = "SELECT status, COUNT(*) as total FROM tickets GROUP BY status";
    $resExt = $conn->query($sqlExt);
    while($row = $resExt->fetch_assoc()) {
        $extStats[$row['status']] = $row['total'];
        $extStats['total'] += $row['total'];
    }

    // B. STATISTIK INTERNAL TICKETS
    $intStats = ['open'=>0, 'progress'=>0, 'closed'=>0, 'total'=>0];
    $sqlInt = "SELECT status, COUNT(*) as total FROM internal_tickets GROUP BY status";
    $resInt = $conn->query($sqlInt);
    while($row = $resInt->fetch_assoc()) {
        $intStats[$row['status']] = $row['total'];
        $intStats['total'] += $row['total'];
    }

    // C. GRAFIK TREND (EXTERNAL VS INTERNAL - 6 BULAN)
    $months = [];
    $dataTrendExt = [];
    $dataTrendInt = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $months[] = date('M Y', strtotime("-$i months"));
        
        $dataTrendExt[] = $conn->query("SELECT COUNT(*) as t FROM tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetch_assoc()['t'];
        $dataTrendInt[] = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetch_assoc()['t'];
    }

    // D. USER PERFORMANCE SUMMARY (UPDATED: INTERNAL ASSIGN)
    // Sekarang menghitung juga Internal Assigned & Internal Closed
    $userPerformances = [];
    $sqlPerf = "SELECT u.id, u.username, u.role,
                -- External Metrics
                (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id) as ext_assigned,
                (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id AND status='closed') as ext_closed,
                -- Internal Metrics
                (SELECT COUNT(*) FROM internal_tickets WHERE user_id = u.id) as int_created,
                (SELECT COUNT(*) FROM internal_tickets WHERE assigned_to = u.id) as int_assigned,
                (SELECT COUNT(*) FROM internal_tickets WHERE assigned_to = u.id AND status='closed') as int_closed
                FROM users u 
                WHERE u.role != 'custom'
                ORDER BY ext_assigned DESC, int_assigned DESC";
    $resPerf = $conn->query($sqlPerf);
} 

// =================================================================================
// LOGIKA 2: STANDARD VIEW (INTERNAL FOCUS)
// =================================================================================
else {
    $mySent = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE user_id = $current_user_id")->fetch_assoc()['t'];
    $myInbox = 0;
    if ($current_div_id) {
        $myInbox = $conn->query("SELECT COUNT(*) as t FROM internal_tickets WHERE target_division_id = $current_div_id")->fetch_assoc()['t'];
    }
    
    $myStatData = [0,0,0]; // Open, Progress, Closed
    $sqlStat = "SELECT status, COUNT(*) as t FROM internal_tickets WHERE user_id=$current_user_id OR target_division_id=$current_div_id GROUP BY status";
    $res = $conn->query($sqlStat);
    while($r = $res->fetch_assoc()){
        if($r['status']=='open') $myStatData[0] = $r['t'];
        if($r['status']=='progress') $myStatData[1] = $r['t'];
        if($r['status']=='closed') $myStatData[2] = $r['t'];
    }
}
?>

<div class="page-heading">
    <h3>Dashboard Overview</h3>
</div>

<div class="page-content">

    <?php if ($current_role == 'admin'): ?>
    
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                <i class="bi bi-grid-fill me-2"></i>Global Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="external-tab" data-bs-toggle="tab" data-bs-target="#external" type="button" role="tab">
                <i class="bi bi-people-fill me-2"></i>External Tickets
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="internal-tab" data-bs-toggle="tab" data-bs-target="#internal" type="button" role="tab">
                <i class="bi bi-building-fill me-2"></i>Internal Tickets
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h4>Traffic Tiket (6 Bulan Terakhir)</h4></div>
                        <div class="card-body">
                            <div id="chart-main-trend"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white mb-3">
                        <div class="card-body">
                            <h3 class="text-white"><?= $extStats['total'] ?></h3>
                            <span>Total External Tickets</span>
                        </div>
                    </div>
                    <div class="card bg-warning text-dark mb-3">
                        <div class="card-body">
                            <h3 class="text-dark"><?= $intStats['total'] ?></h3>
                            <span>Total Internal Tickets</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4>User Performance Summary</h4>
                    <small class="text-muted">Memantau beban kerja admin dan aktivitas tiket internal.</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th rowspan="2" class="align-middle">User</th>
                                    <th rowspan="2" class="align-middle">Role</th>
                                    <th colspan="2" class="text-center border-bottom text-primary">External (Customer)</th>
                                    <th colspan="3" class="text-center border-bottom text-warning text-dark">Internal (Divisi)</th>
                                </tr>
                                <tr>
                                    <th class="text-center text-primary"><small>Assigned</small></th>
                                    <th class="text-center text-primary"><small>Closed</small></th>
                                    <th class="text-center text-dark"><small>Assigned</small></th>
                                    <th class="text-center text-dark"><small>Closed</small></th>
                                    <th class="text-center text-muted"><small>Created</small></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($u = $resPerf->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= $u['username'] ?></td>
                                    <td><span class="badge bg-secondary"><?= ucfirst($u['role']) ?></span></td>
                                    
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary fs-6"><?= $u['ext_assigned'] ?></span>
                                    </td>
                                    <td class="text-center fw-bold text-success"><?= $u['ext_closed'] ?></td>
                                    
                                    <td class="text-center">
                                        <span class="badge bg-warning bg-opacity-25 text-dark fs-6"><?= $u['int_assigned'] ?></span>
                                    </td>
                                    <td class="text-center fw-bold text-success"><?= $u['int_closed'] ?></td>
                                    <td class="text-center text-muted"><?= $u['int_created'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="external" role="tabpanel">
            <h5 class="mb-3 text-primary">Statistik Tiket Customer</h5>
            <section class="row">
                <div class="col-6 col-lg-3">
                    <div class="card">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon purple"><i class="iconly-boldShow"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Total</h6><h6 class="font-extrabold mb-0"><?= $extStats['total'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon green"><i class="iconly-boldTick-Square"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Open</h6><h6 class="font-extrabold mb-0"><?= $extStats['open'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon blue"><i class="iconly-boldWork"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Progress</h6><h6 class="font-extrabold mb-0"><?= $extStats['progress'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon red"><i class="iconly-boldClose-Square"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Closed</h6><h6 class="font-extrabold mb-0"><?= $extStats['closed'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h4>Distribusi Status (External)</h4></div>
                        <div class="card-body"><div id="chart-ext-pie"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="internal" role="tabpanel">
            <h5 class="mb-3 text-warning">Statistik Tiket Internal (Antar Divisi)</h5>
            <section class="row">
                <div class="col-6 col-lg-3">
                    <div class="card bg-light">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon orange"><i class="iconly-boldCategory"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Total</h6><h6 class="font-extrabold mb-0"><?= $intStats['total'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card bg-light">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon green"><i class="iconly-boldPlus"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Open</h6><h6 class="font-extrabold mb-0"><?= $intStats['open'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card bg-light">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon blue"><i class="iconly-boldGraph"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Progress</h6><h6 class="font-extrabold mb-0"><?= $intStats['progress'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card bg-light">
                        <div class="card-body px-3 py-4-5">
                            <div class="row">
                                <div class="col-md-4"><div class="stats-icon red"><i class="iconly-boldUnlock"></i></div></div>
                                <div class="col-md-8"><h6 class="text-muted font-semibold">Closed</h6><h6 class="font-extrabold mb-0"><?= $intStats['closed'] ?></h6></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h4>Distribusi Status (Internal)</h4></div>
                        <div class="card-body"><div id="chart-int-pie"></div></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php else: ?>
        <div class="row">
            <div class="col-12 col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-white text-primary me-3"><i class="bi bi-send-fill fs-3"></i></div>
                            <div>
                                <h5 class="text-white">Tiket Dikirim</h5>
                                <h2 class="text-white font-bold mb-0"><?= $mySent ?></h2>
                                <small>Tiket yang Anda buat untuk divisi lain.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card bg-info text-white">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-white text-info me-3"><i class="bi bi-inbox-fill fs-3"></i></div>
                            <div>
                                <h5 class="text-white">Tiket Masuk Divisi</h5>
                                <h2 class="text-white font-bold mb-0"><?= $myInbox ?></h2>
                                <small>Tiket dari divisi lain ke divisi Anda.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h4>Status Tiket Anda & Divisi</h4></div>
                    <div class="card-body"><div id="chart-std-status"></div></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
<?php if ($current_role == 'admin'): ?>
    var optionsMain = {
        series: [
            { name: 'External Tickets', data: <?php echo json_encode($dataTrendExt); ?> },
            { name: 'Internal Tickets', data: <?php echo json_encode($dataTrendInt); ?> }
        ],
        chart: { height: 350, type: 'area', toolbar: { show: false } },
        colors: ['#435ebe', '#ff9900'],
        xaxis: { categories: <?php echo json_encode($months); ?> },
        stroke: { curve: 'smooth' }
    };
    new ApexCharts(document.querySelector("#chart-main-trend"), optionsMain).render();

    var optionsExtPie = {
        series: [<?= $extStats['open'] ?>, <?= $extStats['progress'] ?>, <?= $extStats['closed'] ?>, <?= $extStats['total'] - ($extStats['open']+$extStats['progress']+$extStats['closed']) ?>],
        chart: { type: 'donut', height: 320 },
        labels: ['Open', 'Progress', 'Closed', 'Other'],
        colors: ['#198754', '#0dcaf0', '#6c757d', '#dc3545']
    };
    new ApexCharts(document.querySelector("#chart-ext-pie"), optionsExtPie).render();

    var optionsIntPie = {
        series: [<?= $intStats['open'] ?>, <?= $intStats['progress'] ?>, <?= $intStats['closed'] ?>],
        chart: { type: 'donut', height: 320 },
        labels: ['Open', 'Progress', 'Closed'],
        colors: ['#198754', '#ffc107', '#6c757d']
    };
    new ApexCharts(document.querySelector("#chart-int-pie"), optionsIntPie).render();

<?php else: ?>
    var optionsStd = {
        series: [<?= $myStatData[0] ?>, <?= $myStatData[1] ?>, <?= $myStatData[2] ?>],
        chart: { type: 'pie', height: 320 },
        labels: ['Open', 'Progress', 'Closed'],
        colors: ['#198754', '#ffc107', '#6c757d']
    };
    new ApexCharts(document.querySelector("#chart-std-status"), optionsStd).render();
<?php endif; ?>
</script>