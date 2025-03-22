<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: support.php');
    exit();
}

$ticket_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Destek talebini ve mesajları getir
$stmt = $db->prepare("
    SELECT st.*, u.username
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    WHERE st.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['error_message'] = "Destek talebi bulunamadı.";
    header('Location: support.php');
    exit();
}

// Kullanıcının admin olup olmadığını kontrol et
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$is_admin = (bool)$stmt->fetchColumn();

// Sadece talep sahibi veya adminler görebilir
if ($ticket['user_id'] != $user_id && !$is_admin) {
    $_SESSION['error_message'] = "Bu destek talebini görüntüleme yetkiniz yok.";
    header('Location: support.php');
    exit();
}

// Mesajları getir
$stmt = $db->prepare("
    SELECT sm.*, u.username
    FROM support_messages sm
    JOIN users u ON sm.user_id = u.id
    WHERE sm.ticket_id = ?
    ORDER BY sm.created_at ASC
");
$stmt->execute([$ticket_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Yeni mesaj gönderme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = clean($_POST['message']);
    
    if (!empty($message)) {
        try {
            // Mesajı kaydet
            $stmt = $db->prepare("INSERT INTO support_messages (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$ticket_id, $user_id, $message]);
            
            // Destek talebinin durumunu güncelle
            if ($is_admin) {
                $status = 'in_progress';
                if (isset($_POST['close_ticket'])) {
                    $status = 'closed';
                }
                
                $stmt = $db->prepare("UPDATE support_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$status, $ticket_id]);
            }
            
            header('Location: ticket.php?id=' . $ticket_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Mesaj gönderilemedi: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Talebi #<?php echo $ticket_id; ?> - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .message {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        .message-user {
            background-color: #f8f9fa;
        }
        .message-admin {
            background-color: #e3f2fd;
        }
        .message-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Destek Talebi #<?php echo $ticket_id; ?></h2>
                    <span class="badge bg-<?php 
                        echo $ticket['status'] === 'open' ? 'success' : 
                            ($ticket['status'] === 'in_progress' ? 'warning' : 'secondary');
                    ?>">
                        <?php
                        echo $ticket['status'] === 'open' ? 'Açık' : 
                            ($ticket['status'] === 'in_progress' ? 'İşlemde' : 'Kapalı');
                        ?>
                    </span>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo clean($ticket['subject']); ?></h5>
                        <small>Oluşturan: <?php echo clean($ticket['username']); ?> - <?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(clean($ticket['message'])); ?></p>
                    </div>
                </div>

                <div class="messages mb-4">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['user_id'] == $ticket['user_id'] ? 'message-user' : 'message-admin'; ?>">
                            <div class="message-meta mb-2">
                                <strong><?php echo clean($message['username']); ?></strong>
                                <span class="ms-2"><?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?></span>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(clean($message['message'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="message" class="form-label">Yanıt Yaz</label>
                                    <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                                </div>
                                <?php if ($is_admin): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="close_ticket" name="close_ticket">
                                        <label class="form-check-label" for="close_ticket">
                                            Talebi kapat
                                        </label>
                                    </div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">Gönder</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 