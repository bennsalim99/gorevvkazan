<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Kullanıcı bilgilerini getir
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit();
}

// İstatistikleri getir
$stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE creator_id = ?");
$stmt->execute([$user_id]);
$created_tasks = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("SELECT COUNT(*) FROM task_participants WHERE user_id = ?");
$stmt->execute([$user_id]);
$participated_tasks = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("SELECT COUNT(*) FROM task_participants WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_tasks = $stmt->fetchColumn() ?: 0;

// Son tamamlanan görevler
$stmt = $db->prepare("
    SELECT t.title, t.points_reward, tp.completed_at
    FROM task_participants tp
    JOIN tasks t ON tp.task_id = t.id
    WHERE tp.user_id = ? AND tp.status = 'completed'
    ORDER BY tp.completed_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_completions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $email = clean($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $new_password_confirm = $_POST['new_password_confirm'];
    
    $errors = [];
    
    // Kullanıcı adı ve e-posta kontrolü
    if ($username !== $user['username']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Bu kullanıcı adı zaten kullanılıyor";
        }
    }
    
    if ($email !== $user['email']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Bu e-posta adresi zaten kullanılıyor";
        }
    }
    
    // Şifre değişikliği kontrolü
    if (!empty($current_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Mevcut şifre hatalı";
        } elseif (empty($new_password)) {
            $errors[] = "Yeni şifre boş olamaz";
        } elseif ($new_password !== $new_password_confirm) {
            $errors[] = "Yeni şifreler eşleşmiyor";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Yeni şifre en az 6 karakter olmalıdır";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Temel bilgileri güncelle
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $user_id]);
            
            // Şifre değişikliği varsa güncelle
            if (!empty($current_password) && !empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
            
            $db->commit();
            $_SESSION['success_message'] = "Profil bilgileriniz başarıyla güncellendi.";
            header('Location: profile.php');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
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
    <title>Profilim - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
            color: white;
        }

        .profile-stats {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: -4rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 10px;
            background: #f8fafc;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #4f46e5;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .stat-card p {
            color: #64748b;
            margin: 0;
        }

        .recent-activity {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .activity-item {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            background: #f8fafc;
            transition: transform 0.3s ease;
        }

        .activity-item:hover {
            transform: translateX(5px);
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .points-badge {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><?php echo clean($user['username']); ?></h1>
                    <p class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Kayıt: <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="points-badge">
                        <i class="fas fa-coins me-2"></i>
                        <?php echo number_format($user['points']); ?> Puan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="profile-stats">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo number_format($completed_tasks); ?></h3>
                        <p>Tamamlanan Görev</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-tasks"></i>
                        <h3><?php echo number_format($created_tasks); ?></h3>
                        <p>Oluşturulan Görev</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-coins"></i>
                        <h3><?php echo number_format($user['points']); ?></h3>
                        <p>Toplam Puan</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="recent-activity">
            <h3 class="mb-4">
                <i class="fas fa-history me-2"></i>
                Son Tamamlanan Görevler
            </h3>
            <?php if (empty($recent_completions)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Henüz tamamlanan görev bulunmuyor.
                </div>
            <?php else: ?>
                <?php foreach ($recent_completions as $completion): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><?php echo clean($completion['title']); ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($completion['completed_at'])); ?>
                                </small>
                            </div>
                            <div class="points-badge">
                                <?php echo number_format($completion['points_reward']); ?> Puan
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 