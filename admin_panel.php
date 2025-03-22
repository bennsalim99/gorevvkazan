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

// VIP kullanıcı sayısı
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_vip = TRUE");
$stats['vip_users'] = $stmt->fetchColumn();

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

// Toplam puan
$stmt = $db->query("SELECT SUM(points) FROM users");
$stats['total_points'] = $stmt->fetchColumn() ?: 0;

// Son aktiviteler
$activities = [];

// Son kayıt olan kullanıcılar
$stmt = $db->query("
    SELECT id, username, email, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$activities['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son oluşturulan görevler
$stmt = $db->query("
    SELECT t.*, u.username as creator_name
    FROM tasks t
    JOIN users u ON t.creator_id = u.id
    ORDER BY t.created_at DESC
    LIMIT 5
");
$activities['recent_tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son destek talepleri
$stmt = $db->query("
    SELECT st.*, u.username
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    ORDER BY st.created_at DESC
    LIMIT 5
");
$activities['recent_tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
        }
        
        .admin-sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #224abe 100%);
            min-height: 100vh;
            color: white;
            padding-top: 1rem;
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            margin: 0.2rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .admin-sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 1.5rem;
            text-align: center;
        }
        
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .activity-card {
            background: white;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.15rem 1.75rem rgba(0, 0, 0, 0.1);
        }
        
        .activity-card .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
        }
        
        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e3e6f0;
            transition: background-color 0.2s;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background-color: #f8f9fc;
        }
        
        .badge-vip {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 admin-sidebar">
                <div class="text-center mb-4">
                    <h4>Admin Panel</h4>
                    <p class="mb-0">Görev Yap Kazan</p>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="admin_panel.php">
                        <i class="fas fa-home"></i> Ana Sayfa
                    </a>
                    <a class="nav-link" href="admin_users.php">
                        <i class="fas fa-users"></i> Kullanıcılar
                    </a>
                    <a class="nav-link" href="admin_tasks.php">
                        <i class="fas fa-tasks"></i> Görevler
                    </a>
                    <a class="nav-link" href="admin_tickets.php">
                        <i class="fas fa-ticket-alt"></i> Destek Talepleri
                    </a>
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-external-link-alt"></i> Siteye Dön
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2 class="mb-4">Kontrol Paneli</h2>

                <!-- İstatistikler -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-users stat-icon text-primary"></i>
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p class="mb-0">Toplam Kullanıcı</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-crown stat-icon text-warning"></i>
                            <h3><?php echo $stats['vip_users']; ?></h3>
                            <p class="mb-0">VIP Kullanıcı</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-tasks stat-icon text-success"></i>
                            <h3><?php echo $stats['active_tasks']; ?></h3>
                            <p class="mb-0">Aktif Görev</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-ticket-alt stat-icon text-danger"></i>
                            <h3><?php echo $stats['open_tickets']; ?></h3>
                            <p class="mb-0">Açık Destek Talebi</p>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Son Kullanıcılar -->
                    <div class="col-md-4">
                        <div class="activity-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Son Kayıt Olan Kullanıcılar</h5>
                                <a href="admin_users.php" class="btn btn-sm btn-primary">Tümü</a>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($activities['recent_users'] as $user): ?>
                                    <div class="activity-item">
                                        <h6 class="mb-1"><?php echo clean($user['username']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Son Görevler -->
                    <div class="col-md-4">
                        <div class="activity-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Son Oluşturulan Görevler</h5>
                                <a href="admin_tasks.php" class="btn btn-sm btn-primary">Tümü</a>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($activities['recent_tasks'] as $task): ?>
                                    <div class="activity-item">
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

                    <!-- Son Destek Talepleri -->
                    <div class="col-md-4">
                        <div class="activity-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Son Destek Talepleri</h5>
                                <a href="admin_tickets.php" class="btn btn-sm btn-primary">Tümü</a>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($activities['recent_tickets'] as $ticket): ?>
                                    <div class="activity-item">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 