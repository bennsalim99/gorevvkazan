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

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$user_id = $_SESSION['user_id'];

try {
    $db->beginTransaction();

    // Görevi kontrol et
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND status = 'active'");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        throw new Exception("Görev bulunamadı.");
    }

    // Daha önce katılıp katılmadığını kontrol et
    $stmt = $db->prepare("SELECT COUNT(*) FROM task_participants WHERE task_id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Bu göreve zaten katıldınız.");
    }

    // Kendi görevine katılmayı engelle
    if ($task['creator_id'] == $user_id) {
        throw new Exception("Kendi görevinize katılamazsınız.");
    }

    // Maksimum katılımcı sayısını kontrol et
    $stmt = $db->prepare("SELECT COUNT(*) FROM task_participants WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $current_participants = $stmt->fetchColumn();

    if ($current_participants >= $task['max_participants']) {
        throw new Exception("Görev maksimum katılımcı sayısına ulaşmış.");
    }

    // Göreve katıl
    $stmt = $db->prepare("INSERT INTO task_participants (task_id, user_id) VALUES (?, ?)");
    $stmt->execute([$task_id, $user_id]);

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 