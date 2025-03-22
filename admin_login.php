<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: admin_panel.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Kullanıcı adı boş olamaz";
    }
    if (empty($password)) {
        $errors[] = "Şifre boş olamaz";
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_admin = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = true;
            
            // Son giriş zamanını güncelle
            $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            header('Location: admin_panel.php');
            exit();
        } else {
            $errors[] = "Geçersiz kullanıcı adı veya şifre";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Girişi - Görev Yap Kazan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            margin: 2rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.5rem;
            transform: rotate(-5deg);
            box-shadow: 0 10px 20px rgba(99,102,241,0.2);
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6b7280;
            margin-bottom: 0;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-floating .form-control {
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            padding: 1rem 1rem 1rem 2.5rem;
            height: calc(3.5rem + 2px);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-floating .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }

        .form-floating label {
            padding: 1rem 1rem 1rem 2.5rem;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            z-index: 2;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99,102,241,0.2);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            z-index: 2;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #6366f1;
        }

        .alert {
            border: none;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
            color: white;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: #6b7280;
            text-decoration: none;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #4f46e5;
            transform: translateX(-5px);
        }

        .back-link i {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>Admin Girişi</h1>
            <p>Yönetici paneline erişmek için giriş yapın</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="form-floating mb-3 position-relative">
                <i class="fas fa-user input-icon"></i>
                <input type="text" class="form-control" id="username" name="username" placeholder="Kullanıcı Adı" required>
                <label for="username">Kullanıcı Adı</label>
            </div>

            <div class="form-floating position-relative">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" class="form-control" id="password" name="password" placeholder="Şifre" required>
                <label for="password">Şifre</label>
                <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
            </button>
        </form>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form doğrulama
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Şifre göster/gizle
        function togglePassword() {
            const passwordInput = document.getElementById('password')
            const toggleIcon = document.querySelector('.password-toggle')
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text'
                toggleIcon.classList.remove('fa-eye')
                toggleIcon.classList.add('fa-eye-slash')
            } else {
                passwordInput.type = 'password'
                toggleIcon.classList.remove('fa-eye-slash')
                toggleIcon.classList.add('fa-eye')
            }
        }
    </script>
</body>
</html> 