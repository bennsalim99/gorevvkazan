<?php
require_once 'config.php';

// 24 saat geçmiş bekleyen başvuruları otomatik onayla
try {
    $db->beginTransaction();

    // 24 saat geçmiş bekleyen başvuruları bul
    $stmt = $db->prepare("
        SELECT tp.*, t.points_reward, t.creator_id
        FROM task_participants tp
        JOIN tasks t ON tp.task_id = t.id
        WHERE tp.status = 'pending'
        AND tp.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($submissions as $submission) {
        // Başvuruyu onayla
        $stmt = $db->prepare("
            UPDATE task_participants 
            SET status = 'completed', 
                completed_at = CURRENT_TIMESTAMP,
                auto_approved = 1
            WHERE id = ?
        ");
        $stmt->execute([$submission['id']]);

        // Kullanıcıya puanını ver
        $stmt = $db->prepare("
            UPDATE users 
            SET points = points + ? 
            WHERE id = ?
        ");
        $stmt->execute([$submission['points_reward'], $submission['user_id']]);
    }

    $db->commit();
    echo "Otomatik onaylama başarılı. " . count($submissions) . " başvuru onaylandı.";

} catch (Exception $e) {
    $db->rollBack();
    echo "Hata: " . $e->getMessage();
}
?> 