<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Admin kontrolü
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$is_admin = (bool)$stmt->fetchColumn();

if (!$is_admin) {
    header('Location: index.php');
    exit();
}

// Kullanıcı işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $target_user_id = (int)$_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'delete':
                try {
                    $db->beginTransaction();
                    
                    // Kullanıcının görevlerini sil
                    $stmt = $db->prepare("DELETE FROM tasks WHERE creator_id = ?");
                    $stmt->execute([$target_user_id]);
                    
                    // Kullanıcının görev katılımlarını sil
                    $stmt = $db->prepare("DELETE FROM task_participants WHERE user_id = ?");
                    $stmt->execute([$target_user_id]);
                    
                    // Kullanıcının destek mesajlarını sil
                    $stmt = $db->prepare("DELETE FROM support_messages WHERE user_id = ?");
                    $stmt->execute([$target_user_id]);
                    
                    // Kullanıcının destek taleplerini sil
                    $stmt = $db->prepare("DELETE FROM support_tickets WHERE user_id = ?");
                    $stmt->execute([$target_user_id]);
                    
                    // Kullanıcıyı sil
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    
                    $db->commit();
                    $_SESSION['success_message'] = "Kullanıcı başarıyla silindi.";
                } catch (PDOException $e) {
                    $db->rollBack();
                    $_SESSION['error_message'] = "Kullanıcı silinirken bir hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'toggle_admin':
                try {
                    $stmt = $db->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    $_SESSION['success_message'] = "Kullanıcının admin durumu güncellendi.";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Admin durumu güncellenirken bir hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'reset_points':
                try {
                    $stmt = $db->prepare("UPDATE users SET points = 0 WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    $_SESSION['success_message'] = "Kullanıcının puanları sıfırlandı.";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Puanlar sıfırlanırken bir hata oluştu: " . $e->getMessage();
                }
                break;

            case 'add_points':
                $points = (int)$_POST['points'];
                try {
                    $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                    $stmt->execute([$points, $target_user_id]);
                    $_SESSION['success_message'] = "Kullanıcıya $points puan eklendi.";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Puan eklenirken bir hata oluştu: " . $e->getMessage();
                }
                break;

            case 'toggle_vip':
                $duration = (int)$_POST['vip_duration']; // Ay cinsinden
                try {
                    if ($duration > 0) {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET is_vip = TRUE, 
                                vip_expires_at = COALESCE(
                                    GREATEST(vip_expires_at, CURRENT_TIMESTAMP),
                                    CURRENT_TIMESTAMP
                                ) + INTERVAL ? MONTH 
                            WHERE id = ?
                        ");
                        $stmt->execute([$duration, $target_user_id]);
                        $_SESSION['success_message'] = "VIP üyelik $duration ay uzatıldı.";
                    } else {
                        $stmt = $db->prepare("UPDATE users SET is_vip = FALSE, vip_expires_at = NULL WHERE id = ?");
                        $stmt->execute([$target_user_id]);
                        $_SESSION['success_message'] = "VIP üyelik iptal edildi.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "VIP durumu güncellenirken bir hata oluştu: " . $e->getMessage();
                }
                break;
        }
        
        header('Location: admin_users.php');
        exit();
    }
}

// Kullanıcıları listele
$stmt = $db->query("
    SELECT u.*,
    (SELECT COUNT(*) FROM tasks WHERE creator_id = u.id) as task_count,
    (SELECT COUNT(*) FROM task_participants WHERE user_id = u.id) as participation_count,
    (SELECT COUNT(*) FROM support_tickets WHERE user_id = u.id) as ticket_count
    FROM users u
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .modal-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
        }
        .btn-group .btn {
            margin-right: 5px;
        }
        .badge-vip {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Kullanıcı Yönetimi</h2>
            <a href="admin.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Admin Paneline Dön
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı Adı</th>
                        <th>E-posta</th>
                        <th>Puan</th>
                        <th>Görev</th>
                        <th>Katılım</th>
                        <th>Destek</th>
                        <th>Kayıt</th>
                        <th>Son Giriş</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <?php echo clean($user['username']); ?>
                                <?php if ($user['is_vip']): ?>
                                    <span class="badge badge-vip">VIP</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo clean($user['email']); ?></td>
                            <td><?php echo $user['points']; ?></td>
                            <td><?php echo $user['task_count']; ?></td>
                            <td><?php echo $user['participation_count']; ?></td>
                            <td><?php echo $user['ticket_count']; ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php
                                if ($user['last_login']) {
                                    echo date('d.m.Y H:i', strtotime($user['last_login']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($user['id'] != $user_id): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <button type="submit" class="btn btn-sm <?php echo $user['is_admin'] ? 'btn-success' : 'btn-secondary'; ?>">
                                            <?php echo $user['is_admin'] ? 'Admin' : 'Normal'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-primary">Siz</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['id'] != $user_id): ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#addPointsModal<?php echo $user['id']; ?>">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#vipModal<?php echo $user['id']; ?>">
                                            <i class="fas fa-crown"></i>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="reset_points">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Kullanıcının puanlarını sıfırlamak istediğinize emin misiniz?')">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Kullanıcıyı silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Puan Ekleme Modal -->
                                    <div class="modal fade" id="addPointsModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Puan Ekle - <?php echo clean($user['username']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="add_points">
                                                        <div class="mb-3">
                                                            <label for="points" class="form-label">Eklenecek Puan</label>
                                                            <input type="number" class="form-control" id="points" name="points" required min="1">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                        <button type="submit" class="btn btn-primary">Puan Ekle</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- VIP Modal -->
                                    <div class="modal fade" id="vipModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">VIP Üyelik - <?php echo clean($user['username']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="toggle_vip">
                                                        
                                                        <?php if ($user['is_vip']): ?>
                                                            <p>
                                                                VIP Bitiş: 
                                                                <?php echo $user['vip_expires_at'] ? date('d.m.Y H:i', strtotime($user['vip_expires_at'])) : 'Süresiz'; ?>
                                                            </p>
                                                        <?php endif; ?>

                                                        <div class="mb-3">
                                                            <label for="vip_duration" class="form-label">VIP Süre (Ay)</label>
                                                            <input type="number" class="form-control" id="vip_duration" name="vip_duration" 
                                                                value="<?php echo $user['is_vip'] ? '0' : '1'; ?>" min="0" required>
                                                            <div class="form-text">
                                                                0 girerseniz VIP üyelik iptal edilir. Pozitif bir değer girerseniz o kadar ay eklenir.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <?php echo $user['is_vip'] ? 'Güncelle' : 'VIP Yap'; ?>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 