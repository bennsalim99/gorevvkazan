<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Destek taleplerini listele
$stmt = $db->prepare("
    SELECT st.*, 
    (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id) as message_count
    FROM support_tickets st 
    WHERE st.user_id = ? 
    ORDER BY st.created_at DESC
");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Yeni destek talebi oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = clean($_POST['subject']);
    $message = clean($_POST['message']);
    
    $errors = [];
    
    if (empty($subject)) {
        $errors[] = "Konu boş olamaz";
    }
    if (empty($message)) {
        $errors[] = "Mesaj boş olamaz";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, message) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $subject, $message]);
            
            $ticket_id = $db->lastInsertId();
            
            $stmt = $db->prepare("INSERT INTO support_messages (ticket_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$ticket_id, $user_id, $message]);
            
            $_SESSION['success_message'] = "Destek talebiniz başarıyla oluşturuldu.";
            header('Location: support.php');
            exit();
        } catch (PDOException $e) {
            $errors[] = "Bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Merkezi - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Yeni Destek Talebi</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Konu</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Mesajınız</label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Gönder</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <h2>Destek Talepleriniz</h2>
                <?php if (empty($tickets)): ?>
                    <div class="alert alert-info">
                        Henüz destek talebiniz bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($tickets as $ticket): ?>
                            <a href="ticket.php?id=<?php echo $ticket['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo clean($ticket['subject']); ?></h5>
                                    <small><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo mb_substr(clean($ticket['message']), 0, 100); ?>...</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small>Mesaj sayısı: <?php echo $ticket['message_count']; ?></small>
                                    <span class="badge bg-<?php echo $ticket['status'] === 'open' ? 'success' : ($ticket['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                        <?php
                                        echo $ticket['status'] === 'open' ? 'Açık' : 
                                            ($ticket['status'] === 'in_progress' ? 'İşlemde' : 'Kapalı');
                                        ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 