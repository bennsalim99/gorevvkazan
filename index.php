<?php
require_once 'config.php';

// İstatistikleri getir
$stats = [];

try {
    // Toplam kullanıcı sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();

    // Aktif görev sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'active'");
    $stats['active_tasks'] = $stmt->fetchColumn();

    // Tamamlanan görev sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM task_participants WHERE status = 'completed'");
    $stats['completed_tasks'] = $stmt->fetchColumn();

    // Toplam dağıtılan puan
    $stmt = $db->query("
        SELECT COALESCE(SUM(t.points_reward), 0) as total_points 
        FROM task_participants tp 
        JOIN tasks t ON tp.task_id = t.id 
        WHERE tp.status = 'completed'
    ");
    $stats['total_points'] = $stmt->fetchColumn();

    // Öne çıkan görevleri getir
    $stmt = $db->prepare("
        SELECT t.*, u.username as creator_username, 
        (SELECT COUNT(*) FROM task_participants WHERE task_id = t.id) as current_participants 
        FROM tasks t 
        JOIN users u ON t.creator_id = u.id 
        WHERE t.status = 'active'
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $featured_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $stats = [
        'total_users' => 0,
        'active_tasks' => 0,
        'completed_tasks' => 0,
        'total_points' => 0
    ];
    $featured_tasks = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Görev Yap Kazan - Ana Sayfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #6366f1;
            --success: #22c55e;
            --background: #f8fafc;
            --text: #1e293b;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--background);
            color: var(--text);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 6rem 0;
            margin-bottom: 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-repeat: no-repeat;
            background-position: bottom;
            background-size: cover;
            opacity: 0.1;
        }

        .hero-content {
            color: white;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Stats Section */
        .stats-section {
            margin-top: -3rem;
            margin-bottom: 4rem;
            position: relative;
            z-index: 2;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-10px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .stat-card p {
            color: #64748b;
            margin: 0;
        }

        /* Featured Tasks */
        .featured-tasks {
            margin-bottom: 4rem;
            padding-top: 2rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text);
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 2px;
        }

        .section-title p {
            color: #64748b;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 1rem auto 0;
        }

        .task-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .task-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .task-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            color: #64748b;
        }

        .task-meta > div {
            display: flex;
            align-items: center;
        }

        .task-meta i {
            margin-right: 0.5rem;
        }

        .btn-task {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }

        .btn-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79,70,229,0.2);
            color: white;
        }

        /* Features Section */
        .features-section {
            background: white;
            padding: 4rem 0;
            border-radius: 30px;
            margin-bottom: 4rem;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
        }

        .feature-card i {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .feature-card p {
            color: #64748b;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .stat-card {
                margin-bottom: 2rem;
            }

            .section-title h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-content">
            <h1>Görevleri Tamamla, Ödülleri Kazan!</h1>
            <p class="lead">Binlerce kullanıcı arasına katıl, görevleri tamamla ve hemen kazanmaya başla.</p>
            <?php if (!isLoggedIn()): ?>
                <div class="d-flex justify-content-center gap-3">
                    <a href="register.php" class="btn btn-light btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Hemen Üye Ol
                    </a>
                    <a href="login.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
                    </a>
                </div>
            <?php else: ?>
                <a href="tasks.php" class="btn btn-light btn-lg">
                    <i class="fas fa-tasks me-2"></i>Görevleri Görüntüle
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Toplam Kullanıcı</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <i class="fas fa-tasks"></i>
                        <h3><?php echo number_format($stats['active_tasks']); ?></h3>
                        <p>Aktif Görev</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo number_format($stats['completed_tasks']); ?></h3>
                        <p>Tamamlanan Görev</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <i class="fas fa-coins"></i>
                        <h3><?php echo number_format($stats['total_points']); ?></h3>
                        <p>Dağıtılan Puan</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Tasks -->
    <section class="featured-tasks">
        <div class="container">
            <div class="section-title">
                <h2>Öne Çıkan Görevler</h2>
                <p>En popüler ve kazançlı görevleri keşfedin</p>
            </div>

            <div class="row g-4">
                <?php foreach ($featured_tasks as $task): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="task-card">
                        <h3><?php echo clean($task['title']); ?></h3>
                        <div class="task-meta">
                            <div>
                                <i class="fas fa-user"></i>
                                <?php echo clean($task['creator_username']); ?>
                            </div>
                            <div>
                                <i class="fas fa-users"></i>
                                <?php echo $task['current_participants']; ?>/<?php echo $task['max_participants']; ?>
                            </div>
                            <div>
                                <i class="fas fa-coins"></i>
                                <?php echo number_format($task['points_reward']); ?> Puan
                            </div>
                        </div>
                        <p class="mb-4"><?php echo mb_substr(clean($task['description']), 0, 100); ?>...</p>
                        <?php if (isLoggedIn()): ?>
                            <a href="tasks.php" class="btn-task">Göreve Katıl</a>
                        <?php else: ?>
                            <a href="login.php" class="btn-task">Giriş Yap ve Katıl</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-title">
                <h2>Neden Biz?</h2>
                <p>Size en iyi hizmeti sunmak için çalışıyoruz</p>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Güvenli Sistem</h3>
                        <p>SSL sertifikalı ve şifreli altyapımız ile verileriniz güvende.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-bolt"></i>
                        <h3>Hızlı Ödeme</h3>
                        <p>Görevlerinizi tamamladıktan hemen sonra puanlarınızı alın.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-headset"></i>
                        <h3>7/24 Destek</h3>
                        <p>Teknik ekibimiz sorularınızı yanıtlamak için her zaman hazır.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 