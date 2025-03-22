<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Aktif görevleri getir
$stmt = $db->prepare("
    SELECT t.*, u.username as creator_username, 
    (SELECT COUNT(*) FROM task_participants WHERE task_id = t.id) as current_participants 
    FROM tasks t 
    JOIN users u ON t.creator_id = u.id 
    WHERE t.status = 'active'
    ORDER BY t.created_at DESC
");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının katıldığı görevleri getir
$stmt = $db->prepare("SELECT task_id FROM task_participants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$participated_tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Görevler - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .task-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .task-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
        }
        
        .task-body {
            padding: 1.5rem;
        }
        
        .task-footer {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-radius: 0 0 15px 15px;
        }
        
        .btn-join {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,60,114,0.3);
            color: white;
        }
        
        .btn-join:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .task-info {
            display: flex;
            align-items: center;
            color: #6c757d;
            margin-right: 1rem;
        }
        
        .task-info i {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <h2 class="mb-4">
            <i class="fas fa-tasks me-2"></i>
            Aktif Görevler
        </h2>

        <?php if (empty($tasks)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Henüz aktif görev bulunmuyor.
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <div class="task-card card">
                    <div class="task-header">
                        <h3 class="mb-2"><?php echo clean($task['title']); ?></h3>
                        <div class="task-info">
                            <i class="fas fa-user"></i>
                            <?php echo clean($task['creator_username']); ?>
                        </div>
                    </div>
                    
                    <div class="task-body">
                        <p class="mb-3"><?php echo nl2br(clean($task['description'])); ?></p>
                        
                        <?php if (!empty($task['task_link'])): ?>
                            <a href="<?php echo clean($task['task_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-3">
                                <i class="fas fa-external-link-alt me-2"></i>
                                Göreve Git
                            </a>
                        <?php endif; ?>
                        
                        <div class="d-flex flex-wrap">
                            <div class="task-info">
                                <i class="fas fa-users"></i>
                                <?php echo $task['current_participants']; ?>/<?php echo $task['max_participants']; ?> Katılımcı
                            </div>
                            <div class="task-info">
                                <i class="fas fa-star"></i>
                                <?php echo number_format($task['points_reward']); ?> Puan Ödül
                            </div>
                            <div class="task-info">
                                <i class="fas fa-clock"></i>
                                <?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="task-footer">
                        <?php if (in_array($task['id'], $participated_tasks)): ?>
                            <button class="btn btn-join" disabled>
                                <i class="fas fa-check me-2"></i>
                                Katıldınız
                            </button>
                        <?php elseif ($task['creator_id'] == $_SESSION['user_id']): ?>
                            <button class="btn btn-join" disabled>
                                <i class="fas fa-info-circle me-2"></i>
                                Sizin Göreviniz
                            </button>
                        <?php elseif ($task['current_participants'] >= $task['max_participants']): ?>
                            <button class="btn btn-join" disabled>
                                <i class="fas fa-users-slash me-2"></i>
                                Görev Dolu
                            </button>
                        <?php else: ?>
                            <button class="btn btn-join" onclick="joinTask(<?php echo $task['id']; ?>)">
                                <i class="fas fa-plus me-2"></i>
                                Göreve Katıl
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function joinTask(taskId) {
            if (confirm('Bu göreve katılmak istediğinize emin misiniz?')) {
                fetch('join_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'task_id=' + taskId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                });
            }
        }
    </script>
</body>
</html> 