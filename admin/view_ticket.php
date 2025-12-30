<?php
$page_title = "Detail Ticket";
include 'includes/header.php';
include 'includes/sidebar.php';
include '../config/functions.php';

// 1. Cek ID Ticket dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('ID Ticket tidak valid!'); window.location='tickets.php';</script>";
    exit;
}

$ticket_id = intval($_GET['id']);
$msg_status = "";

// --- LOGIKA 1: ASSIGN TICKET ---
if (isset($_POST['submit_assign'])) {
    $assign_to = intval($_POST['assigned_to']);
    $assign_to_sql = ($assign_to == 0) ? "NULL" : $assign_to;
    
    if ($conn->query("UPDATE tickets SET assigned_to = $assign_to_sql WHERE id = $ticket_id")) {
        $msg_status = "<div class='alert alert-success'>Tiket berhasil ditugaskan (Assigned).</div>";
        
        // Kirim Notif Log ke Discord
        if (function_exists('sendToDiscord')) {
            $adminName = "Unassigned";
            if($assign_to > 0) {
                $resAdm = $conn->query("SELECT username FROM users WHERE id = $assign_to");
                if($resAdm->num_rows > 0) $adminName = $resAdm->fetch_assoc()['username'];
            }
            $t_check = $conn->query("SELECT ticket_code, discord_thread_id FROM tickets WHERE id = $ticket_id")->fetch_assoc();
            $discordFields = [
                ["name" => "Ticket ID", "value" => $t_check['ticket_code'], "inline" => true],
                ["name" => "Assigned To", "value" => $adminName, "inline" => true],
                ["name" => "Updated By", "value" => $_SESSION['username'], "inline" => true]
            ];
            $thread_id = isset($t_check['discord_thread_id']) ? $t_check['discord_thread_id'] : null;
            sendToDiscord("Ticket Assigned", "Ticket ownership has been updated.", $discordFields, $thread_id);
        }
    } else {
        $msg_status = "<div class='alert alert-danger'>Gagal update assignment.</div>";
    }
}

// --- LOGIKA 2: PROSES REPLY & UPDATE STATUS ---
if (isset($_POST['submit_reply'])) {
    $reply_msg = $conn->real_escape_string($_POST['reply_message']);
    $new_status = $conn->real_escape_string($_POST['ticket_status']);
    
    // Upload Attachment Logic
    $attachment = null;
    $uploadDir = __DIR__ . '/../uploads/'; 
    
    if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == 0) {
        $allowedSize = 2 * 1024 * 1024; 
        if ($_FILES['reply_attachment']['size'] <= $allowedSize) {
            $fileName = time() . '_admin_' . preg_replace("/[^a-zA-Z0-9.]/", "", $_FILES['reply_attachment']['name']);
            if (move_uploaded_file($_FILES['reply_attachment']['tmp_name'], $uploadDir . $fileName)) {
                $attachment = $fileName;
            } else {
                $msg_status = "<div class='alert alert-danger'>Gagal upload file.</div>";
            }
        } else {
            $msg_status = "<div class='alert alert-danger'>File terlalu besar! Max 2MB.</div>";
        }
    }

    if (strpos($msg_status, 'alert-danger') === false) { 
        // Insert Reply
        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user, message, attachment) VALUES (?, 'Admin', ?, ?)");
        $stmt->bind_param("iss", $ticket_id, $reply_msg, $attachment);
        
        if ($stmt->execute()) {
            // Update Status
            $conn->query("UPDATE tickets SET status = '$new_status' WHERE id = $ticket_id");

            // Ambil Data Ticket 
            $t_data = $conn->query("SELECT * FROM tickets WHERE id = $ticket_id")->fetch_assoc();

            // --- KIRIM EMAIL DENGAN STATUS ---
            if (function_exists('sendEmailNotification')) {
                $emailSubject = "Balasan Ticket #" . $t_data['ticket_code'];
                
                $emailBody = "<h3>Halo " . $t_data['name'] . ",</h3>";
                $emailBody .= "<p>Ticket Anda <strong>#" . $t_data['ticket_code'] . "</strong> telah dibalas oleh Admin.</p>";
                
                // [BARU] Menambahkan Status Ticket
                $emailBody .= "<p><strong>Status Ticket:</strong> <span style='color:blue; font-weight:bold;'>" . strtoupper($new_status) . "</span></p>";
                
                $emailBody .= "<p><strong>Pesan Admin:</strong><br>" . nl2br(htmlspecialchars($_POST['reply_message'])) . "</p>";
                
                if($attachment) $emailBody .= "<p><em>(Admin menyertakan lampiran)</em></p>";
                
                $emailBody .= "<hr><p>Silakan cek detailnya di website kami.</p>";
                
                sendEmailNotification($t_data['email'], $emailSubject, $emailBody);
            }

            // Kirim Discord (Reply to Thread)
            if (function_exists('sendToDiscord')) {
                $discordFields = [
                    ["name" => "Ticket ID", "value" => $t_data['ticket_code'], "inline" => true],
                    ["name" => "Admin Reply", "value" => (strlen($reply_msg) > 900 ? substr($reply_msg,0,900).'...' : $reply_msg)],
                    ["name" => "Status", "value" => strtoupper($new_status), "inline" => true]
                ];
                if($attachment) $discordFields[] = ["name" => "Attachment", "value" => "Yes (Check Dashboard)", "inline" => true];
                
                $thread_id = isset($t_data['discord_thread_id']) ? $t_data['discord_thread_id'] : null;
                sendToDiscord("Ticket Replied by Admin", "Admin has replied.", $discordFields, $thread_id);
            }

            $msg_status = "<div class='alert alert-success'>Balasan berhasil dikirim!</div>";
        } else {
            $msg_status = "<div class='alert alert-danger'>Gagal menyimpan database.</div>";
        }
    }
}

// 3. AMBIL DATA TICKET UTAMA
$sql = "SELECT t.*, u.username as assigned_name FROM tickets t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = $ticket_id";
$ticket = $conn->query($sql)->fetch_assoc();

if (!$ticket) {
    echo "<div class='p-4'>Ticket tidak ditemukan.</div>";
    include 'includes/footer.php'; exit;
}

// 4. AMBIL LIST ADMIN
$admins = [];
$res_adm = $conn->query("SELECT id, username FROM users WHERE role = 'admin'");
while($row = $res_adm->fetch_assoc()) { $admins[] = $row; }

// 5. AMBIL DATA REPLIES
$replies = [];
$res_rep = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id = $ticket_id ORDER BY created_at ASC");
while($row = $res_rep->fetch_assoc()) { $replies[] = $row; }

// Helper Functions
function formatText($text) { return nl2br(htmlspecialchars($text)); }
function isImage($file) { return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']); }
?>

<style>
    .chat-box {
        background-color: #f8f9fa; /* Background abu muda */
        padding: 20px;
        border-radius: 0 0 10px 10px;
        max-height: 600px;
        overflow-y: auto;
    }
    
    .chat-row {
        display: flex;
        margin-bottom: 20px;
        align-items: flex-start;
    }
    
    .chat-row.admin {
        flex-direction: row-reverse; /* Admin dikanan */
    }
    
    .chat-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .chat-avatar.user { background-color: #ffc107; color: #333; margin-right: 15px; }
    .chat-avatar.admin { background-color: #435ebe; color: #fff; margin-left: 15px; }
    
    .chat-bubble {
        position: relative;
        max-width: 75%;
        padding: 15px 20px;
        border-radius: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        line-height: 1.6;
    }
    
    /* Bubble User (Kiri) */
    .chat-row:not(.admin) .chat-bubble {
        background-color: #ffffff;
        color: #333;
        border-top-left-radius: 0;
    }
    
    /* Bubble Admin (Kanan) */
    .chat-row.admin .chat-bubble {
        background-color: #435ebe; /* Biru Mazer */
        color: #ffffff;
        border-top-right-radius: 0;
    }
    
    .chat-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 0.85rem;
        opacity: 0.8;
    }
    
    .chat-time { font-size: 0.75rem; }
    
    .chat-attachment {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(0,0,0,0.1);
    }
    .chat-row.admin .chat-attachment { border-top-color: rgba(255,255,255,0.2); }
</style>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Detail Ticket #<?= $ticket['ticket_code'] ?></h3>
                <p class="text-subtitle text-muted">Manage, assign, dan balas tiket dari client.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first text-end">
                <a href="tickets.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
            </div>
        </div>
    </div>

    <section class="section">
        <?= $msg_status ?>
        
        <div class="row">
            <div class="col-md-8">
                
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 text-primary"><?= htmlspecialchars($ticket['subject']) ?></h5>
                            <small class="text-muted">Dibuat: <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></small>
                        </div>
                        <div>
                            <span class="badge bg-<?= ($ticket['status']=='open'?'success':($ticket['status']=='progress'?'warning':'secondary')) ?> fs-6">
                                <?= strtoupper($ticket['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
                            <div class="chat-avatar user me-3"><?= strtoupper(substr($ticket['name'],0,1)) ?></div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?= htmlspecialchars($ticket['name']) ?></h6>
                                <small class="text-muted d-block"><?= htmlspecialchars($ticket['company']) ?></small>
                                <small class="text-muted"><i class="bi bi-envelope"></i> <?= htmlspecialchars($ticket['email']) ?></small>
                            </div>
                        </div>
                        
                        <h6 class="text-uppercase text-muted small fw-bold ls-1">Deskripsi Masalah</h6>
                        <div class="mb-3 text-dark">
                            <?= formatText($ticket['description']) ?>
                        </div>

                        <?php if($ticket['attachment']): ?>
                        <div class="mt-3">
                            <a href="../uploads/<?= $ticket['attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-paperclip"></i> Lihat Lampiran Awal
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h6 class="mb-0"><i class="bi bi-chat-text-fill me-2"></i> Riwayat Percakapan</h6>
                    </div>
                    
                    <div class="chat-box">
                        <?php if(!empty($replies)): ?>
                            <?php foreach($replies as $reply): ?>
                                <?php $isAdmin = ($reply['user'] == 'Admin'); ?>
                                
                                <div class="chat-row <?= $isAdmin ? 'admin' : 'user' ?>">
                                    
                                    <div class="chat-avatar <?= $isAdmin ? 'admin' : 'user' ?>">
                                        <?= $isAdmin ? 'A' : strtoupper(substr($ticket['name'],0,1)) ?>
                                    </div>

                                    <div class="chat-bubble">
                                        <div class="chat-header">
                                            <span class="fw-bold"><?= $isAdmin ? 'Admin Support' : htmlspecialchars($ticket['name']) ?></span>
                                            <span class="chat-time"><?= date('d M H:i', strtotime($reply['created_at'])) ?></span>
                                        </div>
                                        
                                        <div class="chat-message">
                                            <?= formatText($reply['message']) ?>
                                        </div>

                                        <?php if(!empty($reply['attachment'])): ?>
                                            <div class="chat-attachment">
                                                <?php if(isImage($reply['attachment'])): ?>
                                                    <a href="../uploads/<?= $reply['attachment'] ?>" target="_blank">
                                                        <img src="../uploads/<?= $reply['attachment'] ?>" class="img-fluid rounded" style="max-height: 150px;">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../uploads/<?= $reply['attachment'] ?>" target="_blank" class="btn btn-sm btn-light border text-dark py-0 px-2" style="font-size: 0.8rem;">
                                                        <i class="bi bi-file-earmark-arrow-down"></i> Download File
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-chat-square-dots fs-1 opacity-25"></i>
                                <p class="mt-2">Belum ada balasan diskusi.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="col-md-4">
                
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="card-title mb-0">Petugas (Assignee)</h6>
                    </div>
                    <div class="card-body pt-3">
                        <form method="POST">
                            <div class="input-group">
                                <select name="assigned_to" class="form-select">
                                    <option value="0">-- Unassigned --</option>
                                    <?php foreach($admins as $adm): ?>
                                        <option value="<?= $adm['id'] ?>" <?= ($ticket['assigned_to'] == $adm['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($adm['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="submit_assign" class="btn btn-outline-primary">
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card sticky-top shadow-sm" style="top: 20px; z-index: 1;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="card-title mb-0">Balas Ticket</h6>
                    </div>
                    <div class="card-body pt-4">
                        <form method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Update Status</label>
                                <select name="ticket_status" class="form-select">
                                    <option value="open" <?= $ticket['status'] == 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="progress" <?= $ticket['status'] == 'progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="hold" <?= $ticket['status'] == 'hold' ? 'selected' : '' ?>>Hold</option>
                                    <option value="closed" <?= $ticket['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                                    <option value="canceled" <?= $ticket['status'] == 'canceled' ? 'selected' : '' ?>>Canceled</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase text-muted">Pesan Balasan</label>
                                <textarea name="reply_message" class="form-control" rows="6" placeholder="Tulis balasan Anda disini..." required></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Lampiran (Optional)</label>
                                <input type="file" name="reply_attachment" class="form-control form-control-sm">
                                <div class="form-text text-muted" style="font-size: 0.75rem;">Max 2MB. Gambar/Dokumen.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="submit_reply" class="btn btn-primary">
                                    <i class="bi bi-send-fill me-2"></i> Kirim Balasan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>