<?php
$page_title = "Dashboard Clients";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// --- 1. DATA PENGAMBILAN (QUERIES) ---

// A. Total Company
$total_clients = $conn->query("SELECT COUNT(*) as t FROM clients")->fetch_assoc()['t'];

// B. Dokumen Stats (NDA, Contract, Both)
$nda_only = $conn->query("SELECT COUNT(*) as t FROM clients WHERE nda_file IS NOT NULL AND nda_file != ''")->fetch_assoc()['t'];
$contract_only = $conn->query("SELECT COUNT(*) as t FROM clients WHERE contract_file IS NOT NULL AND contract_file != ''")->fetch_assoc()['t'];
$both_docs = $conn->query("SELECT COUNT(*) as t FROM clients WHERE (nda_file IS NOT NULL AND nda_file != '') AND (contract_file IS NOT NULL AND contract_file != '')")->fetch_assoc()['t'];

// C. Statistik Berdasarkan Tipe Langganan (Subscription) - DIPERBAIKI
$sub_labels = [];
$sub_data = [];
// Menggunakan COALESCE untuk menangani data NULL/Kosong
$sqlSub = "SELECT COALESCE(NULLIF(subscription_type, ''), 'Unknown') as sub_type, COUNT(*) as total 
           FROM clients 
           GROUP BY sub_type";
$resSub = $conn->query($sqlSub);

if ($resSub->num_rows > 0) {
    while($row = $resSub->fetch_assoc()) {
        $sub_labels[] = $row['sub_type'];
        $sub_data[] = (int)$row['total'];
    }
} else {
    // Default data jika kosong agar chart tetap muncul (placeholder)
    $sub_labels = ['No Data'];
    $sub_data = [0];
}

// D. Statistik Berdasarkan Status Client
$stat_labels = [];
$stat_data = [];
$sqlStat = "SELECT COALESCE(NULLIF(status, ''), 'Unknown') as status_name, COUNT(*) as total 
            FROM clients 
            GROUP BY status_name";
$resStat = $conn->query($sqlStat);

if ($resStat->num_rows > 0) {
    while($row = $resStat->fetch_assoc()) {
        $stat_labels[] = $row['status_name'];
        $stat_data[] = (int)$row['total'];
    }
} else {
    $stat_labels = ['No Data'];
    $stat_data = [0];
}

// E. Sales Person Performance
$sales_stats = [];
$sqlSales = "SELECT u.username, COUNT(c.id) as total_client,
             SUM(CASE WHEN c.status = 'Subscribe' THEN 1 ELSE 0 END) as active_client
             FROM users u
             LEFT JOIN clients c ON c.sales_person_id = u.id
             JOIN divisions d ON u.division_id = d.id
             WHERE d.name = 'Business Development' OR d.code = 'BD'
             GROUP BY u.id ORDER BY total_client DESC";
$resSales = $conn->query($sqlSales);
?>

<div class="page-heading">
    <h3>Dashboard Clients</h3>
    <p class="text-subtitle text-muted">Analisis data pelanggan, status berlangganan, dan kelengkapan dokumen.</p>
</div>

<div class="page-content">
    
    <div class="row">
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                            <div class="stats-icon purple mb-2">
                                <i class="bi bi-buildings-fill"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">Total Company</h6>
                            <h6 class="font-extrabold mb-0"><?= $total_clients ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                            <div class="stats-icon blue mb-2">
                                <i class="bi bi-file-earmark-lock"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">NDA Uploaded</h6>
                            <h6 class="font-extrabold mb-0"><?= $nda_only ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                            <div class="stats-icon orange mb-2">
                                <i class="bi bi-file-earmark-check"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">Contract Signed</h6>
                            <h6 class="font-extrabold mb-0"><?= $contract_only ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body px-4 py-4-5">
                    <div class="row">
                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                            <div class="stats-icon green mb-2">
                                <i class="bi bi-patch-check-fill"></i>
                            </div>
                        </div>
                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                            <h6 class="text-muted font-semibold">Full Legal Docs</h6>
                            <h6 class="font-extrabold mb-0"><?= $both_docs ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4>Subscription Types</h4>
                </div>
                <div class="card-body">
                    <div id="chart-subscription"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4>Client Status Overview</h4>
                </div>
                <div class="card-body">
                    <div id="chart-status"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4>Sales Person Performance</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Sales Person Name</th>
                                    <th>Total Clients Handled</th>
                                    <th>Active Subscriptions</th>
                                    <th>Performance Bar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($resSales->num_rows > 0): ?>
                                    <?php while($sales = $resSales->fetch_assoc()): ?>
                                    <?php 
                                        // Hitung persentase active
                                        $total = $sales['total_client'];
                                        $active = $sales['active_client'];
                                        $percent = ($total > 0) ? round(($active / $total) * 100) : 0;
                                        
                                        $barColor = ($percent > 70) ? 'success' : (($percent > 40) ? 'info' : 'warning');
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-person-circle me-2 text-secondary"></i> <?= htmlspecialchars($sales['username']) ?></td>
                                        <td class="fw-bold fs-5 text-center"><?= $total ?></td>
                                        <td class="text-center text-success fw-bold"><?= $active ?></td>
                                        <td width="30%">
                                            <small class="text-muted">Active Rate: <?= $percent ?>%</small>
                                            <div class="progress progress-sm mt-1">
                                                <div class="progress-bar bg-<?= $barColor ?>" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data Sales Person.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // 1. CHART SUBSCRIPTION (PIE CHART) - DATA REAL DARI PHP
    var subLabels = <?= json_encode($sub_labels) ?>;
    var subSeries = <?= json_encode($sub_data) ?>;

    var optionsSub = {
        series: subSeries,
        chart: { type: 'pie', height: 350 },
        labels: subLabels,
        colors: ['#435ebe', '#55c6e8', '#ff7976', '#feb019', '#00e396'],
        legend: { position: 'bottom' },
        dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + "%" } },
        tooltip: {
            y: { formatter: function(val) { return val + " Clients" } }
        },
        plotOptions: { pie: { donut: { size: '60%' } } }
    };
    
    // Render hanya jika ada data
    if(subSeries.length > 0 && subSeries[0] !== 0) {
        var chartSub = new ApexCharts(document.querySelector("#chart-subscription"), optionsSub);
        chartSub.render();
    } else {
        document.querySelector("#chart-subscription").innerHTML = "<p class='text-center text-muted py-5'>Belum ada data tipe langganan.</p>";
    }

    // 2. CHART STATUS (BAR CHART) - DATA REAL DARI PHP
    var statLabels = <?= json_encode($stat_labels) ?>;
    var statSeries = <?= json_encode($stat_data) ?>;

    var optionsStat = {
        series: [{
            name: 'Total Clients',
            data: statSeries
        }],
        chart: { type: 'bar', height: 350 },
        plotOptions: {
            bar: { borderRadius: 4, horizontal: true, barHeight: '50%' }
        },
        dataLabels: { enabled: true },
        xaxis: { categories: statLabels },
        colors: ['#57caeb'],
        grid: { borderColor: '#f1f1f1' },
        tooltip: {
            y: { formatter: function(val) { return val + " Clients" } }
        }
    };

    if(statSeries.length > 0 && statSeries[0] !== 0) {
        var chartStat = new ApexCharts(document.querySelector("#chart-status"), optionsStat);
        chartStat.render();
    } else {
        document.querySelector("#chart-status").innerHTML = "<p class='text-center text-muted py-5'>Belum ada data status client.</p>";
    }
</script>

<?php include 'includes/footer.php'; ?>