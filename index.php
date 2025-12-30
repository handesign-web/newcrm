<?php
// =================================================================
// 1. BACKEND LOGIC
// =================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$configPath = __DIR__ . '/config/functions.php';
if (file_exists($configPath)) { include $configPath; }

// Variabel Default
$ticket = null;
$replies = [];
$track_error = "";
$msg_success = "";
$msg_error = "";

// Default View
$current_view = 'default'; 

// Cek Navigasi dari URL
if (isset($_GET['view'])) {
    $current_view = $_GET['view'];
}

// LOGIKA 1: TRACKING TICKET
if (isset($_GET['track_id']) && !empty($_GET['track_id'])) {
    $current_view = 'track_result'; 
    $track_id = $conn->real_escape_string($_GET['track_id']);
    
    // Ambil Data Ticket
    $sql = "SELECT * FROM tickets WHERE ticket_code = '$track_id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
        
        // LOGIKA 2: KIRIM BALASAN (REPLY)
        if (isset($_POST['submit_reply'])) {
            $reply_msg = $conn->real_escape_string($_POST['reply_message']);
            $ticket_id = $ticket['id'];
            $user_name = $ticket['name']; 

            // Upload File Logic
            $attachment = null;
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $uploadOk = true;

            // Cek apakah ada file yang diupload
            if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == 0) {
                $allowed = 2 * 1024 * 1024; // 2MB
                if ($_FILES['reply_attachment']['size'] <= $allowed) {
                    // Sanitasi nama file
                    $fileExt = pathinfo($_FILES['reply_attachment']['name'], PATHINFO_EXTENSION);
                    $cleanName = preg_replace("/[^a-zA-Z0-9]/", "", pathinfo($_FILES['reply_attachment']['name'], PATHINFO_FILENAME));
                    $fileName = time() . '_user_' . $cleanName . '.' . $fileExt;
                    
                    if (move_uploaded_file($_FILES['reply_attachment']['tmp_name'], $uploadDir . $fileName)) {
                        $attachment = $fileName;
                    } else { 
                        $msg_error = "Gagal upload file ke server."; 
                        $uploadOk = false; 
                    }
                } else { 
                    $msg_error = "File terlalu besar (Max 2MB)."; 
                    $uploadOk = false; 
                }
            }

            if ($uploadOk) {
                $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user, message, attachment) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $ticket_id, $user_name, $reply_msg, $attachment);
                
                if ($stmt->execute()) {
                    // Kirim Notif Discord
                    if (function_exists('sendToDiscord')) {
                        $discordFields = [
                            ["name" => "Ticket ID", "value" => $ticket['ticket_code'], "inline" => true],
                            ["name" => "Reply From", "value" => $user_name . " (Customer)", "inline" => true],
                            ["name" => "Message", "value" => (strlen($reply_msg)>900?substr($reply_msg,0,900).'...':$reply_msg)]
                        ];
                        if($attachment) {
                            $discordFields[] = ["name" => "Attachment", "value" => "Yes", "inline" => true];
                        }
                        $thread_id = isset($ticket['discord_thread_id']) ? $ticket['discord_thread_id'] : null;
                        sendToDiscord("New Reply from Customer", "Customer has replied.", $discordFields, $thread_id);
                    }
                    
                    // Redirect agar tidak resubmit form saat refresh
                    // PENTING: Pastikan URL mengandung track_id agar balik ke halaman chat
                    header("Location: index.php?track_id=$track_id&view=track_result");
                    exit;
                } else { 
                    $msg_error = "Gagal simpan ke database."; 
                }
            }
        }

        // Ambil History Chat (Refresh setelah post)
        $replies = [];
        $reply_res = $conn->query("SELECT * FROM ticket_replies WHERE ticket_id = " . intval($ticket['id']) . " ORDER BY created_at ASC");
        if ($reply_res) { while($row = $reply_res->fetch_assoc()) { $replies[] = $row; } }

    } else {
        $track_error = "Ticket ID <strong>" . htmlspecialchars($track_id) . "</strong> tidak ditemukan.";
        $current_view = 'track_search'; 
    }
}

// Helper Functions
function formatTextOutput($text) { return nl2br(htmlspecialchars($text)); }
function isImage($file) { return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk Portal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* RESET & LAYOUT */
        body, html { height: 100%; overflow: hidden; background-color: #f2f7ff; font-family: 'Inter', sans-serif; }
        #auth { height: 100%; }
        
        /* SIDEBAR KIRI */
        #auth-left {
            height: 100%; overflow-y: auto; padding: 3rem;
            display: flex; flex-direction: column; justify-content: center;
            background: #fff; z-index: 10;
        }

        /* AREA KANAN (BIRU) */
        #auth-right {
            background-color: #435ebe;
            background-image: linear-gradient(135deg, #435ebe 0%, #25396f 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        /* BUTTONS & ICONS SYMMETRY FIX */
        .btn-circle {
            width: 42px; height: 42px; padding: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; transition: all 0.2s ease;
        }
        .btn-circle i { font-size: 1.2rem; line-height: 0; display: flex; align-items: center; justify-content: center; }
        
        /* Menu Button Active State */
        .btn-menu.active { background-color: #435ebe; color: white; border-color: #435ebe; }
        .btn-menu i { font-size: 1.2rem; vertical-align: middle; margin-top: -3px; display: inline-block; }

        /* CARD UMUM */
        .content-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 600px;
            padding: 2.5rem;
            animation: slideUp 0.4s ease-out;
        }

        /* TRACK RESULT WRAPPER */
        .track-result-wrapper {
            background: #f0f2f5;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            width: 100%; max-width: 900px; height: 85vh; 
            display: flex; flex-direction: column;
            overflow: hidden; animation: slideUp 0.4s ease-out; position: relative;
        }

        /* Header Ticket */
        .ticket-header {
            background: #ffffff;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            flex-shrink: 0; z-index: 5;
        }
        .ticket-status-badge {
            font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;
            padding: 6px 12px; border-radius: 20px; text-transform: uppercase;
        }

        /* Chat Area */
        .chat-scroll-area {
            flex-grow: 1; overflow-y: auto; padding: 1.5rem;
            background-color: #e5ddd5;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%239C92AC' fill-opacity='0.08' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        /* Message Rows */
        .message-row { display: flex; align-items: flex-start; margin-bottom: 1.25rem; width: 100%; }
        .message-row.admin { justify-content: flex-start; }
        .message-row.user { justify-content: flex-end; }

        /* AVATAR FIX (PERFECT CENTER) */
        .msg-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 16px; flex-shrink: 0;
            line-height: 1; /* Reset line height */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .avatar-admin { background: #fff; color: #435ebe; margin-right: 12px; }
        .avatar-user { background: #ffc107; color: #333; margin-left: 12px; }

        /* Bubbles */
        .msg-bubble {
            max-width: 70%; padding: 12px 16px; border-radius: 12px;
            position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            font-size: 0.95rem; line-height: 1.5;
        }
        .message-row.admin .msg-bubble { background: #ffffff; color: #111; border-top-left-radius: 0; }
        .message-row.user .msg-bubble { background: #435ebe; color: #ffffff; border-top-right-radius: 0; }
        .message-row.user .msg-bubble a { color: #fff; text-decoration: underline; }

        .msg-meta { font-size: 0.7rem; margin-top: 4px; display: block; opacity: 0.7; }
        .message-row.user .msg-meta { text-align: right; color: #fff; }
        .message-row.admin .msg-meta { text-align: left; color: #666; }

        /* FOOTER INPUT AREA */
        .ticket-footer { background: #ffffff; padding: 1rem 1.5rem; border-top: 1px solid #e0e0e0; flex-shrink: 0; }
        
        .input-group-modern {
            background: #f8f9fa; border-radius: 30px; padding: 5px;
            border: 1px solid #e0e0e0; display: flex; align-items: center;
        }
        .input-group-modern:focus-within { border-color: #435ebe; box-shadow: 0 0 0 3px rgba(67, 94, 190, 0.1); }
        .input-modern { border: none; background: transparent; box-shadow: none !important; padding: 10px 15px; }

        /* Indikator File Upload */
        .file-indicator {
            position: absolute; top: 0; right: 0;
            width: 10px; height: 10px; background-color: #dc3545;
            border-radius: 50%; border: 2px solid #fff; display: none;
        }
        .btn-attachment.has-file .file-indicator { display: block; }
        .btn-attachment.has-file { color: #435ebe !important; background-color: #e7f1ff; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .chat-scroll-area::-webkit-scrollbar { width: 6px; }
        .chat-scroll-area::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.2); border-radius: 10px; }
    </style>
</head>

<body>
    <div id="auth">
        <div class="row h-100 g-0">
            
            <div class="col-lg-5 col-12 d-flex flex-column h-100 shadow position-relative" style="z-index:100; background:#fff;">
                <div id="auth-left">
                    <div class="mb-5">
                        <h3 class="fw-bold text-primary d-flex align-items-center">
                            <i class="bi bi-life-preserver me-2" style="font-size: 1.8rem;"></i> Helpdesk System
                        </h3>
                    </div>

                    <h1 class="auth-title mb-2">Welcome</h1>
                    <p class="auth-subtitle mb-5 text-secondary">Silakan pilih menu bantuan di bawah ini.</p>

                    <div class="d-grid gap-3">
                        <a href="?view=create" class="btn btn-outline-primary btn-lg p-3 text-start shadow-sm btn-menu <?= ($current_view=='create')?'active':'' ?>">
                            <i class="bi bi-plus-circle-fill me-2"></i> Buat Ticket Baru
                        </a>
                        <a href="?view=track_search" class="btn btn-outline-primary btn-lg p-3 text-start shadow-sm btn-menu <?= ($current_view=='track_search' || $current_view=='track_result')?'active':'' ?>">
                            <i class="bi bi-search me-2"></i> Lacak Status Ticket
                        </a>
                    </div>

                    <div class="mt-auto pt-5 text-center">
                        <p class="text-secondary small">Staff Administrator? <a href="login.php" class="fw-bold text-primary">Login Disini</a>.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7 d-none d-lg-block">
                <div id="auth-right">
                    
                    <?php if($current_view == 'default'): ?>
                    <div class="text-center text-white">
                        <div class="mb-4"><i class="bi bi-chat-square-quote-fill" style="font-size: 5rem; opacity: 0.8;"></i></div>
                        <h2 class="fw-bold">Halo! Ada yang bisa kami bantu?</h2>
                        <p class="fs-5 opacity-75">Pilih menu di sebelah kiri untuk memulai.</p>
                    </div>
                    <?php endif; ?>

                    <?php if($current_view == 'create'): ?>
                    <div class="content-card">
                        <h4 class="mb-4 text-primary fw-bold border-bottom pb-3">Buat Ticket Baru</h4>
                        <form action="process_ticket.php" method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Jenis Ticket</label>
                                    <select name="type" class="form-select"><option value="support">Support</option><option value="payment">Payment</option></select>
                                </div>
                                <div class="col-md-6"><label class="form-label small fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label small fw-bold">Perusahaan</label><input type="text" name="company" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label small fw-bold">Nama</label><input type="text" name="name" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label small fw-bold">No. Telp</label><input type="text" name="phone" class="form-control" required></div>
                                <div class="col-md-12"><label class="form-label small fw-bold">Subject</label><input type="text" name="subject" class="form-control" required></div>
                                <div class="col-md-12"><label class="form-label small fw-bold">Deskripsi</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
                                <div class="col-md-12"><label class="form-label small fw-bold">Lampiran</label><input type="file" name="attachment" class="form-control"></div>
                                <div class="col-12 pt-2"><button type="submit" name="submit_ticket" class="btn btn-primary w-100 py-2 fw-bold">Kirim Ticket</button></div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if($current_view == 'track_search'): ?>
                    <div class="content-card text-center">
                        <div class="mb-4 text-primary"><i class="bi bi-search" style="font-size: 3.5rem;"></i></div>
                        <h3 class="fw-bold text-dark">Lacak Status Ticket</h3>
                        <p class="text-muted mb-4">Masukkan Nomor ID Ticket Anda untuk melihat progress terbaru.</p>
                        
                        <form action="index.php" method="GET">
                            <div class="mb-3 text-start">
                                <label class="form-label fw-bold ms-1 text-muted small text-uppercase">Nomor Ticket</label>
                                <input type="text" name="track_id" class="form-control form-control-lg text-center font-monospace fs-5" placeholder="LFID-SUP-xxxx..." required>
                            </div>
                            <?php if($track_error): ?>
                                <div class="alert alert-danger d-flex align-items-center justify-content-center p-2 mb-3 small">
                                    <i class="bi bi-exclamation-circle-fill me-2"></i> <?= $track_error ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold">Cari Ticket</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if($current_view == 'track_result' && $ticket): ?>
                    <div class="track-result-wrapper">
                        
                        <div class="ticket-header">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <a href="?view=track_search" class="btn btn-light border btn-circle text-muted" title="Kembali">
                                        <i class="bi bi-arrow-left"></i>
                                    </a>
                                    <div>
                                        <h5 class="fw-bold mb-0 text-dark lh-1"><?= htmlspecialchars($ticket['subject']) ?></h5>
                                        <small class="text-muted font-monospace">#<?= $ticket['ticket_code'] ?></small>
                                    </div>
                                </div>
                                <?php 
                                    $st = strtolower($ticket['status']); 
                                    $bg = ($st=='open')?'success':(($st=='progress')?'warning text-dark':(($st=='closed')?'secondary':'danger')); 
                                ?>
                                <span class="badge bg-<?= $bg ?> ticket-status-badge"><?= strtoupper($st) ?></span>
                            </div>
                            
                            <div class="d-flex align-items-center text-muted small mt-3 ps-5 ms-3">
                                <span class="me-3"><i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($ticket['name']) ?></span>
                                <span><i class="bi bi-clock me-1"></i> <?= date('d M, H:i', strtotime($ticket['created_at'])) ?></span>
                            </div>
                            
                            <div class="mt-2 ps-5 ms-3">
                                <a class="text-decoration-none small text-primary fw-bold" data-bs-toggle="collapse" href="#detailProblem" role="button">
                                    Lihat Detail Masalah <i class="bi bi-chevron-down"></i>
                                </a>
                                <div class="collapse mt-2" id="detailProblem">
                                    <div class="card card-body bg-light border-0 small text-secondary">
                                        <?= formatTextOutput($ticket['description']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chat-scroll-area" id="chatContainer">
                            <?php if(count($replies) > 0): ?>
                                <?php foreach($replies as $reply): $isAdmin = ($reply['user'] == 'Admin'); ?>
                                    
                                    <div class="message-row <?= $isAdmin ? 'admin' : 'user' ?>">
                                        
                                        <?php if($isAdmin): ?>
                                            <div class="msg-avatar avatar-admin" title="Admin Support">
                                                <i class="bi bi-headset"></i>
                                            </div>
                                        <?php endif; ?>

                                        <div class="msg-bubble">
                                            <div class="fw-bold mb-1" style="font-size:0.75rem">
                                                <?= $isAdmin ? 'Support Team' : 'Anda' ?>
                                            </div>
                                            
                                            <div><?= formatTextOutput($reply['message']) ?></div>

                                            <?php if($reply['attachment']): ?>
                                                <div class="mt-2 pt-2 border-top border-opacity-25" style="border-color:inherit">
                                                    <?php if(isImage($reply['attachment'])): ?>
                                                        <a href="uploads/<?= $reply['attachment'] ?>" target="_blank">
                                                            <img src="uploads/<?= $reply['attachment'] ?>" class="img-fluid rounded border bg-white p-1" style="max-height:150px">
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="uploads/<?= $reply['attachment'] ?>" target="_blank" class="text-reset small text-decoration-none d-flex align-items-center">
                                                            <i class="bi bi-file-earmark-arrow-down me-1 fs-5"></i> Download File
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <span class="msg-meta"><?= date('H:i', strtotime($reply['created_at'])) ?></span>
                                        </div>

                                        <?php if(!$isAdmin): ?>
                                            <div class="msg-avatar avatar-user" title="Anda">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                    </div>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="d-flex flex-column align-items-center justify-content-center h-75 text-muted opacity-50">
                                    <i class="bi bi-chat-square-dots fs-1 mb-2"></i>
                                    <p class="small">Belum ada percakapan dimulai.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($ticket['status'] != 'closed' && $ticket['status'] != 'canceled'): ?>
                        <div class="ticket-footer">
                            <form action="index.php?track_id=<?= htmlspecialchars($track_id) ?>&view=track_result" method="POST" enctype="multipart/form-data">
                                <div class="input-group-modern">
                                    <label class="btn btn-link text-secondary p-2 m-0 border-0 rounded-circle d-flex align-items-center justify-content-center position-relative btn-attachment" style="width: 40px; height: 40px; cursor: pointer;" id="attachBtn">
                                        <i class="bi bi-paperclip fs-5"></i>
                                        <span class="file-indicator"></span>
                                        <input type="file" name="reply_attachment" class="d-none" id="fileInput">
                                    </label>
                                    
                                    <input type="text" name="reply_message" class="form-control input-modern" placeholder="Ketik balasan Anda..." required autocomplete="off">
                                    
                                    <button type="submit" name="submit_reply" class="btn btn-primary btn-circle shadow-sm ms-2">
                                        <i class="bi bi-send-fill fs-6 ps-1"></i>
                                    </button>
                                </div>
                                <div class="text-end mt-1">
                                    <small class="text-muted" style="font-size: 0.7rem;" id="fileNameDisplay">*Max 2MB (JPG/PDF)</small>
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                            <div class="ticket-footer text-center bg-light py-4">
                                <span class="badge bg-secondary p-2 px-3 rounded-pill"><i class="bi bi-lock-fill me-1"></i> Tiket Ditutup</span>
                            </div>
                        <?php endif; ?>

                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Auto Scroll Chat
            var chatBox = document.getElementById("chatContainer");
            if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

            // Indikator File Upload
            var fileInput = document.getElementById('fileInput');
            var attachBtn = document.getElementById('attachBtn');
            var fileNameDisplay = document.getElementById('fileNameDisplay');

            if(fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        attachBtn.classList.add('has-file');
                        fileNameDisplay.textContent = "File: " + this.files[0].name;
                        fileNameDisplay.classList.add('text-primary');
                    } else {
                        attachBtn.classList.remove('has-file');
                        fileNameDisplay.textContent = "*Max 2MB (JPG/PDF)";
                        fileNameDisplay.classList.remove('text-primary');
                    }
                });
            }
        });
    </script>
</body>
</html>