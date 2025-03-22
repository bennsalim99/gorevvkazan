<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Lütfen giriş yapın.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Geçersiz istek.']);
    exit();
}

$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$rejection_reason = isset($_POST['rejection_reason']) ? clean($_POST['rejection_reason']) : '';
$user_id = $_SESSION['user_id'];

try {
    $db->beginTransaction();

    // Başvuruyu ve görev bilgilerini al
    $stmt = $db->prepare("
        SELECT tp.*, t.creator_id, t.points_reward, t.title
        FROM task_participants tp
        JOIN tasks t ON tp.task_id = t.id
        WHERE tp.id = ? AND tp.status = 'pending'
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        throw new Exception("Başvuru bulunamadı veya zaten işlenmiş.");
    }

    // Görev sahibi kontrolü
    if ($submission['creator_id'] != $user_id) {
        throw new Exception("Bu başvuruyu işleme yetkiniz yok.");
    }

    if ($action === 'approve') {
        // Başvuruyu onayla
        $stmt = $db->prepare("
            UPDATE task_participants 
            SET status = 'completed', completed_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$submission_id]);

        // Kullanıcıya puanını ver
        $stmt = $db->prepare("
            UPDATE users 
            SET points = points + ? 
            WHERE id = ?
        ");
        $stmt->execute([$submission['points_reward'], $submission['user_id']]);

        // Görevi tamamlandı olarak işaretle ve listeden kaldır
        $stmt = $db->prepare("
            UPDATE tasks 
            SET status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$submission['task_id']]);

        // Başarı mesajı
        $_SESSION['success_message'] = "Başvuru onaylandı ve kullanıcıya " . $submission['points_reward'] . " puan verildi. Görev tamamlandı olarak işaretlendi.";

    } elseif ($action === 'reject') {
        // Red sebebi kontrolü
        if (empty($rejection_reason)) {
            throw new Exception("Lütfen red sebebini belirtin.");
        }

        // Başvuruyu reddet
        $stmt = $db->prepare("
            UPDATE task_participants 
            SET status = 'rejected', 
                completed_at = CURRENT_TIMESTAMP,
                rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$rejection_reason, $submission_id]);

        // Başarı mesajı
        $_SESSION['success_message'] = "Başvuru reddedildi.";

    } else {
        throw new Exception("Geçersiz işlem.");
    }

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 