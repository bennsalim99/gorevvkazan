<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: tasks.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    $errors = [];
    
    // Validasyonlar
    if (empty($username)) {
        $errors[] = "Kullanıcı adı boş olamaz";
    }
    if (empty($email)) {
        $errors[] = "E-posta adresi boş olamaz";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir e-posta adresi giriniz";
    }
    if (empty($password)) {
        $errors[] = "Şifre boş olamaz";
    }
    if (strlen($password) < 6) {
        $errors[] = "Şifre en az 6 karakter olmalıdır";
    }
    if ($password !== $password_confirm) {
        $errors[] = "Şifreler eşleşmiyor";
    }
    
    // Kullanıcı adı ve e-posta kontrolü
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Bu kullanıcı adı zaten kullanılıyor";
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Bu e-posta adresi zaten kullanılıyor";
        }
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password]);
            
            $_SESSION['success_message'] = "Kayıt işlemi başarılı! Lütfen giriş yapın.";
            header('Location: login.php');
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
    <title>Kayıt Ol - Görev Yap Kazan</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e8eaf6 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }

        .nav-link {
            font-weight: 500;
            color: var(--secondary-color) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .register-section {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 80px 0;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--info-color));
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }

        .form-control {
            padding: 12px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: none;
        }

        .input-group-text {
            border: 2px solid #e9ecef;
            border-right: none;
            background: white;
            transition: all 0.3s ease;
        }

        .form-control:focus + .input-group-text,
        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        .btn-primary {
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 10px;
            background: var(--primary-color);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #2e59d9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .alert-danger {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }

        .alert-danger ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .form-text {
            color: var(--secondary-color);
            font-size: 0.875rem;
            margin-top: 5px;
        }

        .password-strength {
            height: 5px;
            border-radius: 2.5px;
            margin-top: 10px;
            transition: all 0.3s ease;
            background: #e9ecef;
        }

        .password-strength.weak { width: 33.33%; background: var(--danger-color); }
        .password-strength.medium { width: 66.66%; background: var(--warning-color); }
        .password-strength.strong { width: 100%; background: var(--success-color); }

        .register-benefits {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }

        .benefit-item i {
            color: var(--success-color);
            margin-right: 10px;
        }

        .social-links {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-3px);
        }

        .social-links .facebook { background: #3b5998; }
        .social-links .google { background: #dd4b39; }
        .social-links .twitter { background: #1DA1F2; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="register-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="register-card">
                        <div class="text-center mb-4">
                            <h2 class="mb-2">Hesap Oluştur</h2>
                            <p class="text-muted">Görev Yap Kazan'a hoş geldiniz! Hemen ücretsiz hesap oluşturun.</p>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? clean($_POST['username']) : ''; ?>" required>
                                </div>
                                <div class="form-text">Kullanıcı adınız herkese görünür olacaktır.</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta Adresi</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>" required>
                                </div>
                                <div class="form-text">Size önemli bildirimler göndereceğiz.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="form-text">En az 6 karakter, 1 büyük harf ve 1 rakam içermelidir.</div>
                            </div>

                            <div class="mb-4">
                                <label for="password_confirm" class="form-label">Şifre Tekrar</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i> Hesap Oluştur
                            </button>

                            <div class="text-center">
                                <p class="mb-0">Zaten hesabınız var mı? <a href="login.php" class="text-primary">Giriş Yap</a></p>
                            </div>

                            <div class="register-benefits">
                                <h5 class="text-center mb-4">Üyelik Avantajları</h5>
                                <div class="benefit-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Görevleri tamamlayarak puan kazanın</span>
                                </div>
                                <div class="benefit-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Kendi görevlerinizi oluşturun</span>
                                </div>
                                <div class="benefit-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>7/24 destek hizmetinden yararlanın</span>
                                </div>
                                <div class="benefit-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Güvenli ve hızlı ödeme sistemi</span>
                                </div>
                            </div>

                            <div class="social-links">
                                <a href="#" class="facebook" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="google" title="Google"><i class="fab fa-google"></i></a>
                                <a href="#" class="twitter" title="Twitter"><i class="fab fa-twitter"></i></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
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
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Şifre gücü kontrolü
        const passwordStrength = document.querySelector('#passwordStrength');
        password.addEventListener('input', function() {
            const value = this.value;
            let strength = 0;
            
            if (value.length >= 6) strength++;
            if (value.match(/[A-Z]/)) strength++;
            if (value.match(/[0-9]/)) strength++;
            
            passwordStrength.className = 'password-strength';
            if (strength === 1) passwordStrength.classList.add('weak');
            else if (strength === 2) passwordStrength.classList.add('medium');
            else if (strength === 3) passwordStrength.classList.add('strong');
        });
    </script>
</body>
</html> 