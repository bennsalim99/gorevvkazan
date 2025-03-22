<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı bilgilerini al
$current_user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

function isActive($page) {
    return strpos($_SERVER['REQUEST_URI'], $page) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #4f46e5;
            --background: #f8fafc;
            --text: #1e293b;
            --text-light: #64748b;
        }

        .navbar {
            padding: 1rem 0;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            transform: rotate(-5deg);
        }

        .brand-text {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text);
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--text);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--primary);
            background-color: rgba(37, 99, 235, 0.1);
        }

        .navbar-nav .nav-link.btn-primary {
            color: white;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            font-weight: 600;
        }

        .navbar-nav .nav-link.btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 0.5rem;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            color: var(--text);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        .dropdown-item.text-danger:hover {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .alert {
            border: none;
            border-radius: 12px;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
        }

        /* WhatsApp Butonu Stilleri */
        .whatsapp-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background-color: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .whatsapp-button:hover {
            transform: scale(1.1);
            color: white;
            background-color: #128C7E;
        }

        @media (max-width: 991.98px) {
            .navbar-nav {
                padding: 1rem 0;
            }

            .navbar-nav .nav-item {
                margin: 0.25rem 0;
            }

            .navbar-nav .nav-link.btn-primary {
                width: 100%;
                text-align: center;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
<!-- WhatsApp Butonu -->
<a href="https://wa.me/639649516465" target="_blank" class="whatsapp-button">
    <i class="fab fa-whatsapp"></i>
</a>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="brand-icon me-2">
                <i class="fas fa-tasks"></i>
            </div>
            <span class="brand-text">Görev Yap Kazan</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('index.php'); ?>" href="index.php">
                        <i class="fas fa-home me-1"></i>Ana Sayfa
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('tasks.php'); ?>" href="tasks.php">
                        <i class="fas fa-list-ul me-1"></i>Görevler
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('my_tasks.php'); ?>" href="my_tasks.php">
                            <i class="fas fa-clipboard-list me-1"></i>Görevlerim
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('create_task.php'); ?>" href="create_task.php">
                            <i class="fas fa-plus me-1"></i>Görev Oluştur
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-user me-1"></i>
                            <?php echo clean($current_user['username']); ?>
                            <span class="badge bg-success ms-1">
                                <i class="fas fa-coins me-1"></i><?php echo number_format($current_user['points']); ?>
                            </span>
                        </a>
                    </li>

                    <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">
                                <i class="fas fa-cog me-1"></i>Admin
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Çıkış
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-4" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Giriş Yap
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php 
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 