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

// İstatistikleri getir
$stats = [];

// Toplam kullanıcı sayısı
$stmt = $db->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Toplam görev sayısı
$stmt = $db->query("SELECT COUNT(*) FROM tasks");
$stats['total_tasks'] = $stmt->fetchColumn();

// Aktif görev sayısı
$stmt = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'active'");
$stats['active_tasks'] = $stmt->fetchColumn();

// Tamamlanan görev sayısı
$stmt = $db->query("SELECT COUNT(*) FROM task_participants WHERE status = 'completed'");
$stats['completed_tasks'] = $stmt->fetchColumn();

// Açık destek talepleri
$stmt = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'");
$stats['open_tickets'] = $stmt->fetchColumn();

// Son 5 kullanıcı
$stmt = $db->query("
    SELECT id, username, email, created_at, last_login 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son 5 görev
$stmt = $db->query("
    SELECT t.*, u.username as creator_name
    FROM tasks t
    JOIN users u ON t.creator_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son 5 destek talebi
$stmt = $db->query("
    SELECT st.*, u.username
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    ORDER BY st.created_at DESC
    LIMIT 5
");
$recent_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <h2 class="mb-4">Admin Paneli</h2>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Toplam Kullanıcı</h5>
                        <h2 class="card-text"><?php echo $stats['total_users']; ?></h2>
                        <a href="admin_users.php" class="text-white">Kullanıcıları Yönet <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Aktif Görevler</h5>
                        <h2 class="card-text"><?php echo $stats['active_tasks']; ?></h2>
                        <a href="admin_tasks.php" class="text-white">Görevleri Yönet <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Açık Destek Talepleri</h5>
                        <h2 class="card-text"><?php echo $stats['open_tickets']; ?></h2>
                        <a href="admin_tickets.php" class="text-white">Destek Taleplerini Yönet <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Son Kullanıcılar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Son Kayıt Olan Kullanıcılar</h5>
                        <a href="admin_users.php" class="btn btn-sm btn-primary">Tümü</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_users as $user): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo clean($user['username']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Görevler -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Son Oluşturulan Görevler</h5>
                        <a href="admin_tasks.php" class="btn btn-sm btn-primary">Tümü</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_tasks as $task): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo clean($task['title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo clean($task['creator_name']); ?> tarafından
                                        <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Destek Talepleri -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Son Destek Talepleri</h5>
                        <a href="admin_tickets.php" class="btn btn-sm btn-primary">Tümü</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_tickets as $ticket): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo clean($ticket['subject']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo clean($ticket['username']); ?> tarafından
                                        <?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?>
                                    </small>
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
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 