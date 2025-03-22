<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Kullanıcının puanını kontrol et
$stmt = $db->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_points = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = clean($_POST['title']);
    $description = clean($_POST['description']);
    $points_required = (int)$_POST['points_required'];
    $points_reward = (int)$_POST['points_reward'];
    $max_participants = (int)$_POST['max_participants'];
    $task_link = clean($_POST['task_link']);

    $errors = [];

    // Validasyonlar
    if (empty($title)) {
        $errors[] = "Başlık boş olamaz";
    }
    if (empty($description)) {
        $errors[] = "Açıklama boş olamaz";
    }
    if ($points_required < 0) {
        $errors[] = "Gereken puan 0'dan küçük olamaz";
    }
    if ($points_reward < 0) {
        $errors[] = "Ödül puanı 0'dan küçük olamaz";
    }
    if ($max_participants < 1) {
        $errors[] = "Katılımcı sayısı en az 1 olmalıdır";
    }
    if ($points_required > $user_points) {
        $errors[] = "Yeterli puanınız yok";
    }
    if (!empty($task_link) && !filter_var($task_link, FILTER_VALIDATE_URL)) {
        $errors[] = "Geçerli bir URL giriniz";
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO tasks (creator_id, title, description, points_required, points_reward, max_participants, task_link) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $description, $points_required, $points_reward, $max_participants, $task_link]);

            // Kullanıcının puanını düşür
            $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->execute([$points_required, $user_id]);

            header('Location: tasks.php');
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
    <title>Görev Oluştur - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            min-height: 100vh;
        }
        
        .task-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        
        .task-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99,102,241,0.2);
        }
        
        .points-info {
            background: linear-gradient(135deg, #34d399 0%, #059669 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        
        .example-task {
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="task-card">
                    <div class="task-header">
                        <h3 class="mb-0">Yeni Görev Oluştur</h3>
                    </div>

                    <div class="points-info mb-4">
                        <h5><i class="fas fa-coins me-2"></i>Mevcut Puanınız: <?php echo $user_points; ?></h5>
                        <p class="mb-0">Görev oluşturmak için gereken puanlar hesabınızdan düşülecektir.</p>
                    </div>

                    <div class="example-task mb-4">
                        <h5><i class="fas fa-lightbulb me-2"></i>Örnek Görev</h5>
                        <p class="mb-0">Instagram'da @sallim_istyn hesabını takip edip, ekran görüntüsü ile kanıtlayan katılımcılara 50 puan ödül! Görev 1 dakika bekleme süresi içerir.</p>
                    </div>

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
                            <label for="title" class="form-label">Görev Başlığı</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                value="Instagram Takip Görevi - @sallim_istyn" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Görev Açıklaması</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required>1. @sallim_istyn Instagram hesabını takip edin
2. Takip ettiğinize dair ekran görüntüsü alın
3. Ekran görüntüsünü yükleyin
4. 1 dakika bekledikten sonra görevi tamamlayın

Not: Takibi bırakmanız durumunda puanınız geri alınacaktır.</textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="task_link" class="form-label">Görev Linki</label>
                            <input type="url" class="form-control" id="task_link" name="task_link" 
                                value="https://www.instagram.com/sallim_istyn" required>
                            <div class="form-text">Instagram profil linki</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="points_required" class="form-label">Gereken Puan</label>
                                    <input type="number" class="form-control" id="points_required" name="points_required" 
                                        value="100" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="points_reward" class="form-label">Ödül Puanı</label>
                                    <input type="number" class="form-control" id="points_reward" name="points_reward" 
                                        value="50" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_participants" class="form-label">Maksimum Katılımcı</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                        value="100" min="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i>Görev Oluştur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 