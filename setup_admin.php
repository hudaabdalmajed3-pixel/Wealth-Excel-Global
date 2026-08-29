<?php
require 'config.php';

$email = "admin@wealthxcel.com";
$password = "123456";
$hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. التأكد من وجود الأدمن في جدول users برتبة admin
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->rowCount() > 0) {
        $pdo->prepare("UPDATE users SET password = ?, role = 'admin', status = 'active' WHERE email = ?")
            ->execute([$hashed, $email]);
        echo "✅ تم تحديث بيانات الأدمن في جدول users.<br>";
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')")
            ->execute(['Global Admin', $email, $hashed]);
        echo "✅ تم إنشاء حساب أدمن جديد في جدول users.<br>";
    }

    // 2. إدخال إعدادات افتراضية في جدول system_settings لكي لا تظهر لوحة التحكم فارغة
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)");
    
    $default_settings = [
        'deposit_wallet' => 'T-Example-Wallet-Address',
        'daily_profit_amount' => '0.50',
        'google_sheet_url' => '#'
    ];

    foreach ($default_settings as $key => $val) {
        $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)")
            ->execute([$key, $val]);
    }
    echo "✅ تم تجهيز جداول الإعدادات والبيانات الافتراضية.";

} catch (PDOException $e) {
    echo "❌ خطأ: " . $e->getMessage();
}
?>