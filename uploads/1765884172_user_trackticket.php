<?php
// ==========================================
// 1. BACKEND LOGIC (TIDAK DIUBAH - FUNGSI TETAP SAMA)
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$configPath = __DIR__ . '/config/functions.php';

if (file_exists($configPath)) {
    include $configPath;
} else {
    die("<strong>Error Critical:</strong> File config/functions.php tidak ditemukan.");
}

if (!isset($conn) || $conn->connect_error) {
    die("<strong>Error Database:</strong> Variabel koneksi (\$conn) bermasalah.");
}

$ticket = null;
$replies = [];
$error = "";
$msg_success = "";
$msg_error = "";

// LOGIKA PENCARIAN TICKET
if (isset($_GET['track_id']) && !empty($_GET['track_id'])) {
    $track_id = $conn->real_escape_string($_GET['track_id']);
    
    // Ambil Data Ticket Utama
    $sql = "SELECT * FROM tickets WHERE ticket_code = '$track_id'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
        
        // --- PROSES REPLY DARI CUSTOMER ---
        if (isset($_POST['submit_reply'])) {
            $reply_msg = $conn->real_escape_string($_POST['reply_message']);
            $ticket_id = $ticket['id'];
            $user_name = $ticket['name']; 

            // Logic Upload Attachment
            $attachment = null;
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $uploadOk = true;

            if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] == 0) {
                $allowedSize = 2 * 1024 * 1024; // 2MB
                $fileSize = $_FILES['reply_attachment']['size'];
                $fileTmp = $_FILES['reply_attachment']['tmp_name'];
                $originalName = $_FILES['reply_attachment']['name'];
                
                if ($fileSize <= $allowedSize) {
                    $fileName = time() . '_user_' . preg_replace("/[^a-zA-Z0-9.]/", "", $originalName);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        $attachment = $fileName;
                    } else {
                        $msg_error = "Gagal upload file.";
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
                    $msg_success = "Balasan terkirim!";
                    
                    if (function_exists('sendToDiscord')) {
                        $discordFields = [
                            ["name" => "Ticket ID", "value" => $ticket['ticket_code'], "inline" => true],
                            ["name" => "Reply From", "value" => $user_name . " (Customer)", "inline" => true],
                            ["name" => "Message", "value" => (strlen($reply_msg) > 900 ? substr($reply_msg,0,900).'...' : $reply_msg)]
                        ];
                        if($attachment) {
                            $discordFields[] = ["name" => "Attachment", "value" => "Yes", "inline" => true];
                        }
                        $thread_id = isset($ticket['discord_thread_id']) ? $ticket['discord_thread_id'] : null;
                        sendToDiscord("New Reply from Customer", "Customer has replied.", $discordFields, $thread_id);
                    }

                    echo "<script>window.location.href = 'track_ticket.php?track_id=$track_id';</script>";
                    exit;
                } else {
                    $msg_error = "Gagal menyimpan pesan ke database.";
                }
            }
        }
        // --- END PROSES REPLY ---

        // Ambil Diskusi/Replies
        $reply_sql = "SELECT * FROM ticket_replies WHERE ticket_id = " . intval($ticket['id']) . " ORDER BY created_at ASC";
        $reply_res = $conn->query($reply_sql);
        if ($reply_res) {
            while($row = $reply_res->fetch_assoc()) {
                $replies[] = $row;
            }
        }

    } else {
        $error = "Ticket ID <strong>" . htmlspecialchars($track_id) . "</strong> tidak ditemukan.";
    }
}

function formatTextOutput($text) {
    $clean = htmlspecialchars($text);
    $fixed = str_replace(array('\r\n', '\n', '\r'), "\n", $clean);
    return nl2br($fixed);
}

function isImage($file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Ticket - Helpdesk</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        
        body { 
            background-color: #f0f2f5; 
            font-family: 'Inter', sans-serif; 
            color: #343a40;
        }
        
        .container-main { max-width: 900px; margin: 0 auto; padding-bottom: 50px; }
        
        /* Navbar */
        .navbar-custom { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .brand-logo { font-weight: 700; color: #435ebe; text-decoration: none; font-size: 1.25rem; }
        
        /* Cards */
        .card-custom {
            border: none; border-radius: 12px; background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 20px; overflow: hidden;
        }
        
        /* Ticket Header */
        .ticket-status-bar {
            background: #f8f9fa; padding: 15px 25px; border-bottom: 1px solid #e9ecef;
            display: flex; justify-content: space-between; align-items: center;
        }
        .ticket-code { font-family: 'Consolas', monospace; font-weight: 700; color: #495057; font-size: 1.1rem; }
        
        /* Chat Area */
        .chat-box-container {
            background-color: #e5ddd5; /* Warna netral ala WhatsApp Web */
            background-image: url('data:image/svg+xml,%3Csvg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z" fill="%239C92AC" fill-opacity="0.05" fill-rule="evenodd"/%3E%3C/svg%3E');
            height: 550px; overflow-y: auto; padding: 20px;
            border-radius: 0 0 12px 12px;
        }

        /* --- CHAT MESSAGE STYLING (FIX MIRING) --- */
        .message-wrapper {
            display: flex; 
            margin-bottom: 20px;
            align-items: flex-start; /* KUNCI: Avatar tetap di atas */
            width: 100%;
        }
        
        .message-wrapper.admin { justify-content: flex-start; }
        .message-wrapper.user { justify-content: flex-end; }

        .chat-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: #fff;
            flex-shrink: 0; /* KUNCI: Avatar tidak gepeng */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 14px;
        }
        .chat-avatar.admin { background-color: #fff; color: #435ebe; margin-right: 12px; border: 1px solid #eee; }
        .chat-avatar.user { background-color: #ffc107; color: #333; margin-left: 12px; }

        .chat-bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Bubble Admin (Putih) */
        .message-wrapper.admin .chat-bubble {
            background-color: #fff; 
            color: #333;
            border-top-left-radius: 0; 
        }
        
        /* Bubble User (Biru/Hijau) */
        .message-wrapper.user .chat-bubble {
            background-color: #435ebe; /* Primary Color */
            color: #fff;
            border-top-right-radius: 0;
        }
        .message-wrapper.user .chat-bubble a { color: #eef2f7; text-decoration: underline; }
        .message-wrapper.user .text-muted { color: rgba(255,255,255,0.7) !important; }

        /* Meta (Nama & Jam) */
        .chat-info {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 4px; font-size: 0.75rem;
        }
        .message-wrapper.user .chat-info { color: rgba(255,255,255,0.9); }
        .message-wrapper.admin .chat-info { color: #6c757d; }

        /* File Attachment */
        .attachment-box {
            margin-top: 10px; padding-top: 8px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        .message-wrapper.user .attachment-box { border-top-color: rgba(255,255,255,0.2); }

        /* Footer Reply */
        .reply-area {
            background: #fff; padding: 20px; border-radius: 12px; margin-top: 20px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.03);
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-custom sticky-top py-3">
        <div class="container container-main">
            <a class="brand-logo" href="index.php">
                <i class="bi bi-headset me-2"></i>Helpdesk System
            </a>
            <a href="create_ticket.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">
                <i class="bi bi-plus-lg"></i> Buat Ticket
            </a>
        </div>
    </nav>

    <div class="container container-main mt-4">
        
        <div class="card-custom p-4">
            <h5 class="text-center mb-3 fw-bold text-secondary">Lacak Status Ticket</h5>
            <form action="" method="GET">
                <div class="input-group input-group-lg">
                    <input type="text" name="track_id" class="form-control fs-6 border-end-0 bg-light" 
                           placeholder="Masukkan Nomor Ticket (Contoh: LFID-SUP-2025...)" 
                           value="<?= isset($_GET['track_id']) ? htmlspecialchars($_GET['track_id']) : '' ?>" required>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="bi bi-search me-2"></i>Cari
                    </button>
                </div>
            </form>
            <?php if($error): ?>
                <div class="alert alert-danger mt-3 mb-0 d-flex align-items-center shadow-sm">
                    <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i> <div><?= $error ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($ticket): ?>
        
        <div class="card-custom">
            <div class="ticket-status-bar">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Ticket Number</small>
                    <div class="ticket-code">#<?= htmlspecialchars($ticket['ticket_code']) ?></div>
                </div>
                <?php 
                    $status = strtolower($ticket['status']);
                    $bg = ($status=='open')?'success':(($status=='progress')?'warning':(($status=='closed')?'secondary':'danger'));
                ?>
                <span class="badge bg-<?= $bg ?> px-3 py-2 rounded-pill text-uppercase fw-bold"><?= $status ?></span>
            </div>
            <div class="p-4">
                <h4 class="fw-bold mb-3"><?= htmlspecialchars($ticket['subject']) ?></h4>
                <div class="d-flex align-items-center text-muted small mb-3">
                    <span class="me-3"><i class="bi bi-person me-1"></i> <?= htmlspecialchars($ticket['name']) ?></span>
                    <span class="me-3"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($ticket['company']) ?></span>
                    <span><i class="bi bi-clock me-1"></i> <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></span>
                </div>
                
                <div class="alert alert-light border">
                    <strong class="d-block mb-1 text-dark">Deskripsi:</strong>
                    <div class="text-secondary"><?= formatTextOutput($ticket['description']) ?></div>
                </div>

                <?php if(!empty($ticket['attachment'])): ?>
                    <a href="uploads/<?= htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="btn btn-sm btn-light border text-primary fw-bold">
                        <i class="bi bi-paperclip me-1"></i> Lihat Lampiran Awal
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-custom mb-0">
            <div class="p-3 border-bottom bg-white fw-bold text-primary">
                <i class="bi bi-chat-text-fill me-2"></i> Riwayat Percakapan
            </div>
            
            <div class="chat-box-container" id="chatContainer">
                <?php if(count($replies) > 0): ?>
                    <?php foreach($replies as $reply): ?>
                        <?php $isAdmin = ($reply['user'] == 'Admin'); ?>
                        
                        <div class="message-wrapper <?= $isAdmin ? 'admin' : 'user' ?>">
                            
                            <?php if($isAdmin): ?>
                                <div class="chat-avatar admin">
                                    <i class="bi bi-headset"></i>
                                </div>
                            <?php endif; ?>

                            <div class="chat-bubble">
                                <div class="chat-info">
                                    <strong class="<?= $isAdmin ? 'text-dark' : 'text-white' ?>">
                                        <?= $isAdmin ? 'Support Team' : 'Anda' ?>
                                    </strong>
                                    <span class="<?= $isAdmin ? 'text-muted' : 'text-white-50' ?>" style="font-size:0.7rem">
                                        <?= date('d/m H:i', strtotime($reply['created_at'])) ?>
                                    </span>
                                </div>
                                
                                <div><?= formatTextOutput($reply['message']) ?></div>

                                <?php if(!empty($reply['attachment'])): ?>
                                    <div class="attachment-box">
                                        <?php if(isImage($reply['attachment'])): ?>
                                            <a href="uploads/<?= $reply['attachment'] ?>" target="_blank">
                                                <img src="uploads/<?= $reply['attachment'] ?>" class="img-fluid rounded border" style="max-height: 150px;">
                                            </a>
                                        <?php else: ?>
                                            <a href="uploads/<?= $reply['attachment'] ?>" target="_blank" class="text-decoration-none small">
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Download File
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if(!$isAdmin): ?>
                                <div class="chat-avatar user">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted opacity-50">
                        <i class="bi bi-chat-square-dots fs-1 mb-2"></i>
                        <p>Belum ada riwayat percakapan.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($ticket['status'] != 'closed' && $ticket['status'] != 'canceled'): ?>
        <div class="reply-area">
            <?php if($msg_error): ?><div class="alert alert-danger py-2 mb-3"><?= $msg_error ?></div><?php endif; ?>
            <?php if($msg_success): ?><div class="alert alert-success py-2 mb-3"><?= $msg_success ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Tulis Balasan</label>
                    <textarea name="reply_message" class="form-control bg-light" rows="3" placeholder="Ketik pesan Anda disini..." required style="resize:none;"></textarea>
                </div>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-paperclip"></i></span>
                            <input type="file" name="reply_attachment" class="form-control">
                        </div>
                        <div class="form-text mt-1 ps-1" style="font-size: 0.7rem;">Format: JPG, PNG, PDF (Max 2MB)</div>
                    </div>
                    <div class="col-md-4 text-end mt-2 mt-md-0">
                        <button type="submit" name="submit_reply" class="btn btn-primary w-100 fw-bold shadow-sm">
                            <i class="bi bi-send-fill me-2"></i> Kirim Balasan
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div class="card-custom mt-3 p-4 bg-light text-center">
                <span class="badge bg-secondary mb-2 fs-6">TIKET DITUTUP</span>
                <p class="text-muted mb-0 small">Tiket ini telah selesai atau dibatalkan. Anda tidak dapat mengirim balasan baru.</p>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="index.php" class="text-decoration-none text-muted fw-bold small hover-primary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Utama
            </a>
        </div>

        <?php endif; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var chatBox = document.getElementById("chatContainer");
            if(chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
    </script>

</body>
</html>