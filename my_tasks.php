<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Kullanıcının oluşturduğu görevleri al
$stmt = $db->prepare("
    SELECT t.*, 
           COUNT(DISTINCT tp.id) as current_participants,
           (SELECT COUNT(*) FROM task_participants WHERE task_id = t.id AND status = 'pending') as pending_submissions
    FROM tasks t
    LEFT JOIN task_participants tp ON t.id = tp.task_id
    WHERE t.creator_id = ?
    GROUP BY t.id
    ORDER BY t.created_at DESC
");
$stmt->execute([$user_id]);
$created_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının katıldığı görevleri al
$stmt = $db->prepare("
    SELECT t.*, tp.status as participation_status, tp.screenshot_path, tp.created_at as joined_at,
           tp.completed_at, tp.auto_approved
    FROM tasks t
    JOIN task_participants tp ON t.id = tp.task_id
    WHERE tp.user_id = ?
    ORDER BY tp.created_at DESC
");
$stmt->execute([$user_id]);
$participated_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Bekleyen başvuruları al
$stmt = $db->prepare("
    SELECT tp.*, t.title, t.points_reward, u.username,
           TIMESTAMPDIFF(HOUR, tp.created_at, NOW()) as hours_passed,
           24 - TIMESTAMPDIFF(HOUR, tp.created_at, NOW()) as hours_remaining
    FROM task_participants tp
    JOIN tasks t ON tp.task_id = t.id
    JOIN users u ON tp.user_id = u.id
    WHERE t.creator_id = ? AND tp.status = 'pending'
    ORDER BY tp.created_at ASC
");
$stmt->execute([$user_id]);
$pending_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Görevlerim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .task-card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .submission-card {
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        .auto-approve-timer {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .screenshot-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            cursor: pointer;
        }
        .modal-image {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container my-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pending_submissions)): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Bekleyen Başvurular</h5>
            </div>
            <div class="card-body">
                <?php foreach ($pending_submissions as $submission): ?>
                    <div class="submission-card p-3 bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6><?php echo htmlspecialchars($submission['title']); ?></h6>
                                <p class="mb-2">
                                    <strong>Katılımcı:</strong> <?php echo htmlspecialchars($submission['username']); ?><br>
                                    <strong>Başvuru Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($submission['created_at'])); ?>
                                </p>
                                <?php if ($submission['screenshot_path']): ?>
                                    <img src="<?php echo htmlspecialchars($submission['screenshot_path']); ?>" 
                                         class="screenshot-preview mb-2" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         data-image="<?php echo htmlspecialchars($submission['screenshot_path']); ?>">
                                <?php endif; ?>
                                <div class="auto-approve-timer">
                                    <?php if ($submission['hours_remaining'] > 0): ?>
                                        <i class="fas fa-clock"></i> Otomatik onaylanmasına <?php echo $submission['hours_remaining']; ?> saat kaldı
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> Otomatik onaylanacak
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-success btn-sm approve-btn" 
                                        data-submission-id="<?php echo $submission['id']; ?>"
                                        data-action="approve">
                                    <i class="fas fa-check"></i> Onayla
                                </button>
                                <button class="btn btn-danger btn-sm reject-btn" 
                                        data-submission-id="<?php echo $submission['id']; ?>"
                                        data-action="reject">
                                    <i class="fas fa-times"></i> Reddet
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Oluşturduğum Görevler</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($created_tasks)): ?>
                            <p class="text-muted">Henüz görev oluşturmadınız.</p>
                        <?php else: ?>
                            <?php foreach ($created_tasks as $task): ?>
                                <div class="task-card card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo $task['current_participants']; ?>/<?php echo $task['max_participants']; ?> Katılımcı
                                                <?php if ($task['pending_submissions'] > 0): ?>
                                                    <span class="badge bg-warning"><?php echo $task['pending_submissions']; ?> Bekleyen Başvuru</span>
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?php echo $task['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo $task['status'] === 'active' ? 'Aktif' : 'Tamamlandı'; ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($task['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Katıldığım Görevler</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($participated_tasks)): ?>
                            <p class="text-muted">Henüz bir göreve katılmadınız.</p>
                        <?php else: ?>
                            <?php foreach ($participated_tasks as $task): ?>
                                <div class="task-card card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php if ($task['participation_status'] === 'completed'): ?>
                                                    <span class="badge bg-success">
                                                        Tamamlandı
                                                        <?php if ($task['auto_approved']): ?>
                                                            (Otomatik)
                                                        <?php endif; ?>
                                                    </span>
                                                <?php elseif ($task['participation_status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">Reddedildi</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Onay Bekliyor</span>
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-info"><?php echo $task['points_reward']; ?> Puan</span>
                                            <small class="text-muted">Katılım: <?php echo date('d.m.Y H:i', strtotime($task['joined_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resim Önizleme Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ekran Görüntüsü</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="modal-image" alt="Ekran Görüntüsü">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Resim önizleme
        $('.screenshot-preview').click(function() {
            var imageUrl = $(this).data('image');
            $('#imageModal .modal-image').attr('src', imageUrl);
        });

        // Başvuru işleme
        $('.approve-btn').click(function() {
            var submissionId = $(this).data('submission-id');
            var button = $(this);
            
            if (confirm('Bu başvuruyu onaylamak istediğinize emin misiniz?')) {
                processSubmission(submissionId, 'approve', button);
            }
        });

        $('.reject-btn').click(function() {
            var submissionId = $(this).data('submission-id');
            var button = $(this);
            
            // Red sebebi modalını göster
            var reason = prompt('Lütfen red sebebini yazın:');
            if (reason !== null) {
                if (reason.trim() === '') {
                    alert('Red sebebi boş olamaz!');
                    return;
                }
                processSubmission(submissionId, 'reject', button, reason);
            }
        });

        function processSubmission(submissionId, action, button, rejectionReason) {
            button.prop('disabled', true);

            var data = {
                submission_id: submissionId,
                action: action
            };

            if (action === 'reject' && rejectionReason) {
                data.rejection_reason = rejectionReason;
            }

            $.ajax({
                url: 'process_submission.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Hata: ' + response.error);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    button.prop('disabled', false);
                }
            });
        }
    });
    </script>
</body>
</html> 