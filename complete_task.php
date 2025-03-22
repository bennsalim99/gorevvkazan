<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['task_id'])) {
    header('Location: my_tasks.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$task_id = (int)$_POST['task_id'];

try {
    $db->beginTransaction();

    // Görev ve katılım bilgilerini kontrol et
    $stmt = $db->prepare("
        SELECT t.*, tp.status as participation_status
        FROM tasks t
        JOIN task_participants tp ON t.id = tp.task_id
        WHERE t.id = ? AND tp.user_id = ? AND t.status = 'active'
    ");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        throw new Exception("Görev bulunamadı veya bu göreve katılmadınız.");
    }

    if ($task['participation_status'] !== 'pending') {
        throw new Exception("Bu görev zaten tamamlanmış veya reddedilmiş.");
    }

    // Ekran görüntüsü kontrolü
    if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Ekran görüntüsü yükleme hatası.");
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $_FILES['screenshot']['tmp_name']);
    finfo_close($file_info);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception("Sadece JPEG, PNG ve GIF formatları kabul edilmektedir.");
    }

    // Ekran görüntüsünü kaydet
    $upload_dir = 'uploads/screenshots/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('screenshot_') . '.' . $file_extension;
    $upload_path = $upload_dir . $filename;

    if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $upload_path)) {
        throw new Exception("Dosya yükleme hatası.");
    }

    // Görevi tamamlandı olarak işaretle
    $stmt = $db->prepare("
        UPDATE task_participants 
        SET status = 'completed', completed_at = CURRENT_TIMESTAMP, screenshot_path = ?
        WHERE task_id = ? AND user_id = ?
    ");
    $stmt->execute([$upload_path, $task_id, $user_id]);

    $db->commit();
    
    $_SESSION['success_message'] = "Görev başarıyla tamamlandı! Görev sahibi onayladıktan sonra puanınız hesabınıza eklenecektir.";
    header('Location: my_tasks.php');
    exit();

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: my_tasks.php');
    exit();
} 