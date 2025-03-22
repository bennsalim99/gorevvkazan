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

// Filtreleme
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$where_clause = "";
$params = [];

if ($status_filter !== 'all') {
    $where_clause = "WHERE st.status = ?";
    $params[] = $status_filter;
}

// Destek taleplerini getir
$query = "
    SELECT st.*, u.username,
    (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id) as message_count
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    $where_clause
    ORDER BY 
        CASE st.status 
            WHEN 'open' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'closed' THEN 3
        END,
        st.updated_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Talepleri Yönetimi - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Destek Talepleri Yönetimi</h2>
                    <div class="btn-group">
                        <a href="?status=all" class="btn btn-outline-primary <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                            Tümü
                        </a>
                        <a href="?status=open" class="btn btn-outline-success <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                            Açık
                        </a>
                        <a href="?status=in_progress" class="btn btn-outline-warning <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                            İşlemde
                        </a>
                        <a href="?status=closed" class="btn btn-outline-secondary <?php echo $status_filter === 'closed' ? 'active' : ''; ?>">
                            Kapalı
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Kullanıcı</th>
                                <th>Konu</th>
                                <th>Durum</th>
                                <th>Mesaj Sayısı</th>
                                <th>Son Güncelleme</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo $ticket['id']; ?></td>
                                    <td><?php echo clean($ticket['username']); ?></td>
                                    <td><?php echo clean($ticket['subject']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $ticket['status'] === 'open' ? 'success' : 
                                                ($ticket['status'] === 'in_progress' ? 'warning' : 'secondary');
                                        ?>">
                                            <?php
                                            echo $ticket['status'] === 'open' ? 'Açık' : 
                                                ($ticket['status'] === 'in_progress' ? 'İşlemde' : 'Kapalı');
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $ticket['message_count']; ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?></td>
                                    <td>
                                        <a href="ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Görüntüle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Destek talebi bulunamadı.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 