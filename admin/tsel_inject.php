<?php
$page_title = "Process Inject Telkomsel";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// Ambil Client
$clients = $conn->query("SELECT id, company_name FROM clients ORDER BY company_name ASC");

// Ambil History Batch
$batches = $conn->query("SELECT b.*, c.company_name 
                         FROM inject_batches b 
                         LEFT JOIN clients c ON b.client_id = c.id 
                         ORDER BY b.created_at DESC LIMIT 10");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .step-card { border-left: 4px solid #435ebe; }
    .progress-wrapper { display: none; margin-top: 20px; }
    .console-log { background: #1e1e1e; color: #00ff00; padding: 10px; height: 150px; overflow-y: auto; font-family: monospace; font-size: 11px; margin-top: 10px; border-radius: 4px; }
    .table-history th { background-color: #f8f9fa; }
    
    /* Styling khusus Card Saldo */
    .border-start-primary { border-left: 5px solid #0d6efd !important; }
    .chart-container { position: relative; height: 160px; width: 160px; margin: 0 auto; }
    .chart-center-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
</style>

<div class="page-heading">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3>Process Inject Data</h3>
            <p class="text-subtitle text-muted">Eksekusi paket data ke nomor yang telah diupload.</p>
        </div>
        <div class="col-12 col-md-6 text-end">
            <a href="tsel_upload.php" class="btn btn-warning shadow-sm">
                <i class="bi bi-cloud-upload me-2"></i> Upload Data Baru
            </a>
        </div>
    </div>
</div>

<div class="page-content">
    
    <div class="card shadow-sm mb-4 border-start-primary">
        <div class="card-header bg-white pb-0 border-bottom-0">
            <h5 class="card-title text-primary"><i class="bi bi-wallet2 me-2"></i>Informasi Saldo API</h5>
        </div>
        <div class="card-body pt-2">
            
            <div id="balanceLoading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted small">Mengambil data saldo...</p>
            </div>

            <div id="balanceContent" class="row align-items-center" style="display:none;">
                <div class="col-md-3 text-center border-end">
                    <div class="chart-container">
                        <canvas id="balanceChart"></canvas>
                        <div class="chart-center-text">
                            <small class="text-muted d-block" style="font-size: 10px;">Sisa</small>
                            <strong class="text-success fs-5" id="txtChartPercent">0%</strong>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        <span class="badge bg-success me-1"> </span>Sisa
                        <span class="badge bg-warning text-dark ms-1"> </span>Pakai
                    </div>
                </div>

                <div class="col-md-9 ps-md-4">
                    <h5 class="mb-3 text-dark fw-bold" id="txtPkgName">-</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-primary fw-bold small text-uppercase">Total Kuota</label>
                            <div class="fs-3 fw-bold text-dark"><span id="txtTotal">0</span> <small class="fs-6 text-muted">GB</small></div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-warning fw-bold small text-uppercase">Terpakai</label>
                            <div class="fs-3 fw-bold text-dark"><span id="txtUsage">0</span> <small class="fs-6 text-muted">GB</small></div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-success fw-bold small text-uppercase">Sisa Saldo</label>
                            <div class="fs-3 fw-bold text-dark"><span id="txtBalance">0</span> <small class="fs-6 text-muted">GB</small></div>
                        </div>
                    </div>

                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center small">
                        <div>
                            <span class="text-muted">Status:</span> <span class="badge bg-success" id="txtStatus">-</span>
                            <span class="text-muted ms-3">Expiry:</span> <strong class="text-danger" id="txtExpiry">-</strong>
                            <span class="text-muted ms-3">Pilot MSISDN: <strong id="txtPilot">-</strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="balanceError" class="alert alert-danger mt-3" style="display:none;">
                Gagal memuat saldo. <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadBalance()">Retry</button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm step-card h-100">
                <div class="card-header bg-light"><strong>1. Pilih Data</strong></div><br>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Customer</label>
                        <select id="sel_client" class="form-select" onchange="loadBatches()">
                            <option value="">-- Pilih Client --</option>
                            <?php while($c = $clients->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Batch Upload</label>
                        <select id="sel_batch" class="form-select" onchange="enableCheckButton()" disabled>
                            <option value="">-- Pilih Client Terlebih Dahulu --</option>
                        </select>
                        <div id="batch_loading" style="display:none;" class="text-primary small mt-1">
                            <span class="spinner-border spinner-border-sm" role="status"></span> Mengambil data batch...
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 mt-3" id="btnCheck" onclick="loadDataPreview()" disabled>
                        <i class="bi bi-search me-2"></i> Tampilkan Data
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm h-100" id="previewCard" style="display:none;">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0 text-primary">2. Preview & Validasi</h5>
                    <span class="badge bg-secondary" id="total_count">0 Items</span>
                </div>
                <div class="card-body">
                    
                    <div class="table-responsive border rounded" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light sticky-top" style="z-index: 1;">
                                <tr>
                                    <th width="5%"><input type="checkbox" id="checkAll" checked onclick="toggleAll(this)"></th>
                                    <th width="5%">No</th>
                                    <th>MSISDN</th>
                                    <th>Data Package</th>
                                    <th width="20%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>

                    <div class="mt-3 pt-2 text-end" id="actionArea">
                        <button class="btn btn-light me-2 border" onclick="resetPage()">Reset</button>
                        <button class="btn btn-success fw-bold px-4" onclick="confirmInject()">
                            <i class="bi bi-play-circle-fill me-2"></i> PROSES INJECT
                        </button>
                    </div>

                    <div class="progress-wrapper" id="progressArea">
                        <h6 class="d-flex justify-content-between">
                            <span>Processing Injection...</span>
                            <span id="progressText">0/0</span>
                        </h6>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progressBar" style="width: 0%">0%</div>
                        </div>
                        <div class="console-log" id="consoleLog"></div>
                        <div class="mt-3 text-center">
                            <a href="tsel_history.php" class="btn btn-sm btn-primary w-50" id="btnFinish" style="display:none;">Lihat Hasil Lengkap</a>
                        </div>
                    </div>

                </div>
            </div>
            
            <div class="card shadow-sm h-100 d-flex align-items-center justify-content-center text-muted p-5" id="placeholderCard">
                <div class="text-center">
                    <i class="bi bi-arrow-left-circle fs-1"></i>
                    <p class="mt-2">Silakan pilih Client dan Batch di sebelah kiri.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">Riwayat Batch Terakhir</h5>
                    <a href="tsel_history.php" class="btn btn-sm btn-outline-primary">View Full History</a>
                </div>
                <div class="card-body px-0 py-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-history mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Batch Code</th>
                                    <th>Client</th>
                                    <th>Total</th>
                                    <th>Status Live</th>
                                    <th>Created At</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($batches->num_rows > 0): ?>
                                    <?php while($b = $batches->fetch_assoc()): 
                                        $bid = $b['id'];
                                        $stats = $conn->query("SELECT 
                                            SUM(CASE WHEN status='SUCCESS' THEN 1 ELSE 0 END) as s,
                                            SUM(CASE WHEN status='FAILED' THEN 1 ELSE 0 END) as f,
                                            SUM(CASE WHEN status='SUBMITTED' THEN 1 ELSE 0 END) as w
                                            FROM inject_history WHERE batch_id=$bid")->fetch_assoc();
                                        
                                        $success = intval($stats['s']);
                                        $failed = intval($stats['f']);
                                        $waiting = intval($stats['w']);
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($b['batch_code']) ?></td>
                                        <td><?= htmlspecialchars($b['company_name'] ?? '-') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $b['total_numbers'] ?> Noms</span></td>
                                        <td>
                                            <?php if($success > 0): ?> <span class="badge bg-success"><?= $success ?> Sukses</span> <?php endif; ?>
                                            <?php if($failed > 0): ?> <span class="badge bg-danger"><?= $failed ?> Gagal</span> <?php endif; ?>
                                            <?php if($waiting > 0): ?> <span class="badge bg-info text-dark"><?= $waiting ?> Proses</span> <?php endif; ?>
                                            <?php if($success == 0 && $failed == 0 && $waiting == 0): ?> <span class="badge bg-secondary">Pending</span> <?php endif; ?>
                                        </td>
                                        <td class="text-muted small"><?= date('d M Y, H:i', strtotime($b['created_at'])) ?></td>
                                        <td class="pe-4 text-end">
                                            <a href="tsel_history.php?batch_id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-info">Detail</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat inject.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    let stagingData = [];
    let isProcessing = false;
    let chartInstance = null;

    // --- A. LOAD BALANCE INFO (ON PAGE LOAD) ---
    $(document).ready(function() {
        loadBalance();
    });

    function loadBalance() {
        $("#balanceLoading").show();
        $("#balanceContent").hide();
        $("#balanceError").hide();

        $.ajax({
            url: 'tsel_process_ajax.php',
            type: 'POST',
            data: { action: 'get_balance' },
            dataType: 'json',
            success: function(res) {
                $("#balanceLoading").hide();
                
                if(res.status === 'success') {
                    const d = res.data;
                    $("#txtPkgName").text(d.packageName);
                    $("#txtBalance").text(d.balance);
                    $("#txtUsage").text(d.usage);
                    $("#txtTotal").text(d.totalQuota);
                    $("#txtStatus").text(d.status);
                    $("#txtExpiry").text(d.expiryDate);
                    $("#txtPilot").text(d.pilotMsisdn);

                    // Hitung Persen Sisa
                    let pct = 0;
                    if(d.totalQuota > 0) pct = Math.round((d.balance / d.totalQuota) * 100);
                    $("#txtChartPercent").text(pct + "%");

                    renderChart(d.balance, d.usage);
                    $("#balanceContent").fadeIn();
                } else {
                    $("#balanceError").html(`Error: ${res.message} <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadBalance()">Retry</button>`).show();
                }
            },
            error: function() {
                $("#balanceLoading").hide();
                $("#balanceError").show();
            }
        });
    }

    function renderChart(bal, usage) {
        const ctx = document.getElementById('balanceChart').getContext('2d');
        if(chartInstance) chartInstance.destroy();

        chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Sisa Saldo', 'Terpakai'],
                datasets: [{
                    data: [bal, usage],
                    backgroundColor: ['#198754', '#ffc107'], // Success Green, Warning Yellow
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '75%',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    // --- B. LOAD BATCHES (FIXED AJAX) ---
    function loadBatches() {
        let clientId = $("#sel_client").val();
        let batchSelect = $("#sel_batch");
        
        batchSelect.html('<option value="">-- Loading... --</option>').prop('disabled', true);
        $("#btnCheck").prop('disabled', true);
        $("#previewCard").hide();
        $("#placeholderCard").show();

        if(clientId) {
            $("#batch_loading").show();
            
            $.ajax({
                url: 'tsel_process_ajax.php',
                type: 'POST',
                data: { action: 'get_batches', client_id: clientId },
                dataType: 'json',
                success: function(response) {
                    $("#batch_loading").hide();
                    batchSelect.html('<option value="">-- Pilih Batch --</option>'); // Reset

                    if(Array.isArray(response) && response.length > 0) {
                        $.each(response, function(key, val) {
                            batchSelect.append(`<option value="${val.batch_name}">${val.batch_name} ${val.info}</option>`);
                        });
                        batchSelect.prop('disabled', false);
                    } else {
                        batchSelect.html('<option value="">Tidak ada batch ditemukan</option>');
                    }
                },
                error: function(xhr, status, error) {
                    $("#batch_loading").hide();
                    console.error("Batch Load Error:", error);
                    alert("Gagal load batch. Pastikan backend benar.");
                }
            });
        } else {
            batchSelect.html('<option value="">-- Pilih Client Terlebih Dahulu --</option>');
        }
    }

    function enableCheckButton() {
        if($("#sel_batch").val()) {
            $("#btnCheck").prop('disabled', false);
        } else {
            $("#btnCheck").prop('disabled', true);
        }
    }

    // --- C. LOAD PREVIEW DATA ---
    function loadDataPreview() {
        let clientId = $("#sel_client").val();
        let batchName = $("#sel_batch").val();

        if(!clientId || !batchName) return;

        $("#btnCheck").prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Loading...');
        
        $.ajax({
            url: 'tsel_process_ajax.php',
            type: 'POST',
            data: { action: 'get_staging_data', client_id: clientId, batch_name: batchName },
            dataType: 'json',
            success: function(res) {
                stagingData = res;
                renderTable();
                $("#placeholderCard").hide();
                $("#previewCard").fadeIn();
                $("#btnCheck").prop('disabled', false).html('<i class="bi bi-search me-2"></i> Tampilkan Data');
            }
        });
    }

    function renderTable() {
        let html = '';
        if(stagingData.length === 0) {
            html = '<tr><td colspan="5" class="text-center p-3 text-muted">Data kosong atau semua nomor sudah diproses.</td></tr>';
            $("#actionArea").hide();
        } else {
            stagingData.forEach((item, index) => {
                html += `
                    <tr id="row_${index}">
                        <td><input type="checkbox" class="row-check" value="${index}" checked></td>
                        <td>${index + 1}</td>
                        <td class="font-monospace fw-bold">${item.msisdn}</td>
                        <td>${item.package_name}</td>
                        <td id="status_${index}"><span class="badge bg-secondary">Ready</span></td>
                    </tr>
                `;
            });
            $("#actionArea").show();
        }
        $("#previewBody").html(html);
        $("#total_count").text(stagingData.length + " Nomor");
    }

    function toggleAll(source) {
        $('.row-check').prop('checked', source.checked);
    }

    function resetPage() {
        if(isProcessing) return;
        $("#previewCard").hide();
        $("#placeholderCard").show();
        $("#sel_batch").val('');
        $("#btnCheck").prop('disabled', true);
    }

    // --- D. PROSES INJECT (LOOPING ASYNC) ---
    function confirmInject() {
        let checkedCount = $('.row-check:checked').length;
        if(checkedCount === 0) { alert("Pilih minimal 1 nomor!"); return; }
        
        if(confirm(`KONFIRMASI: Yakin ingin memproses ${checkedCount} nomor?`)) {
            startProcess();
        }
    }

    async function startProcess() {
        isProcessing = true;
        $("#actionArea").slideUp();
        $("#progressArea").slideDown();
        $(".row-check").prop('disabled', true);
        $("#checkAll").prop('disabled', true);

        // 1. Create Batch Record
        let clientId = $("#sel_client").val();
        let batchNameOriginal = $("#sel_batch").val();
        let newBatchCode = batchNameOriginal + "-RUN-" + Math.floor(Date.now() / 1000).toString().substr(-4);
        
        // Ambil indeks yang dicentang
        let selectedIndices = [];
        $('.row-check:checked').each(function() {
            selectedIndices.push($(this).val());
        });
        
        let total = selectedIndices.length;

        // Create Batch di DB
        let batchRes = await $.post('tsel_process_ajax.php', {
            action: 'create_batch',
            client_id: clientId,
            batch_code: newBatchCode,
            total: total
        }, null, 'json');
        
        let batchId = batchRes.batch_id;
        logConsole(`Batch ID: ${batchId} Created. Starting...`);

        // 2. Loop Inject per Item
        let success = 0;
        let failed = 0;

        for (let i = 0; i < total; i++) {
            let idx = selectedIndices[i];
            let item = stagingData[idx];

            // Update status row jadi spinner
            $(`#status_${idx}`).html('<span class="spinner-border spinner-border-sm text-primary"></span>');

            try {
                let res = await $.ajax({
                    url: 'tsel_process_ajax.php',
                    type: 'POST',
                    data: {
                        action: 'inject_single',
                        batch_id: batchId,
                        staging_id: item.id, 
                        msisdn: item.msisdn,
                        package: item.package_name
                    },
                    dataType: 'json'
                });

                if (res.status === 'success') {
                    $(`#status_${idx}`).html('<span class="badge bg-info">SUBMITTED</span>');
                    logConsole(`[${i+1}/${total}] ${item.msisdn}: OK (ReqID: ${res.req_id})`);
                    success++;
                } else {
                    $(`#status_${idx}`).html('<span class="badge bg-danger">FAIL</span>');
                    logConsole(`[${i+1}/${total}] ${item.msisdn}: FAIL - ${res.message}`);
                    failed++;
                }
            } catch (e) {
                $(`#status_${idx}`).html('<span class="badge bg-dark">ERR</span>');
                logConsole(`[${i+1}/${total}] Error Koneksi`);
                failed++;
            }

            // Update Progress Bar
            let pct = Math.round(((i + 1) / total) * 100);
            $("#progressBar").css("width", pct + "%").text(pct + "%");
            $("#progressText").text(`${i + 1} / ${total}`);
        }

        logConsole(`--- DONE: Success ${success}, Failed ${failed} ---`);
        
        $("#progressBar").removeClass('progress-bar-animated bg-success').addClass('bg-primary');
        $("#btnFinish").show(); // Tampilkan tombol history
        
        // Refresh Saldo Otomatis Setelah Inject Selesai
        loadBalance();
        
        alert("Proses Selesai! Cek log history.");
    }

    function logConsole(msg) {
        let t = new Date().toLocaleTimeString();
        $("#consoleLog").append(`<div><span class="text-muted">[${t}]</span> ${msg}</div>`);
        let d = document.getElementById("consoleLog");
        d.scrollTop = d.scrollHeight;
    }
</script>