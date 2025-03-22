<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'gorev_sistemi';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Bağlantı hatası: " . $e->getMessage();
    die();
}

session_start();

// Kullanıcı giriş kontrolü
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Admin kontrolü
function isAdmin() {
    global $db;
    if (!isLoggedIn()) return false;
    
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}

// VIP kontrolü
function isVIP() {
    global $db;
    if (!isLoggedIn()) return false;
    
    $stmt = $db->prepare("SELECT is_vip FROM users WHERE id = ? AND vip_expires_at > NOW()");
    $stmt->execute([$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}

// Kullanıcı bilgilerini getir
function getCurrentUser() {
    global $db;
    if (!isLoggedIn()) return null;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// XSS koruması
function clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?> 