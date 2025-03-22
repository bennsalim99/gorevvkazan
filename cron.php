<?php
require_once 'config.php';

// Hata raporlamayı devre dışı bırak
error_reporting(0);

// Cron işleminin çalıştığını logla
$log_file = __DIR__ . '/cron.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Cron başladı\n", FILE_APPEND);

try {
    // auto_approve.php dosyasını çalıştır
    require_once 'auto_approve.php';
    
    // Başarılı çalışmayı logla
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Cron başarıyla tamamlandı\n", FILE_APPEND);
} catch (Exception $e) {
    // Hatayı logla
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Hata: " . $e->getMessage() . "\n", FILE_APPEND);
}
?> 