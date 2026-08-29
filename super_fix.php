<?php
// super_fix.php - كشف التضارب وإصلاح الجدول
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

echo "<body style='background:#000; color:#0f0; font-family:monospace; padding:20px;'>";
echo "<h1>🚀 عملية الإصلاح الجذري (Super Fix)</h1>";

try {
    // 1. الاتصال وكشف الهوية
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "<p>1. الاتصال بقاعدة البيانات: <span style='color:yellow; font-size:1.2em;'>[ $db ]</span> ... <span style='color:green'>نجح ✅</span></p>";
    echo "<p style='color:#ccc'>⚠️ تأكد أن هذا هو نفس الاسم الذي تفتحه في phpMyAdmin!</p>";

    // 2. فحص الجدول الحالي (قبل الحذف)
    echo "<hr><h3>2. فحص الجدول الحالي (قبل الإصلاح):</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE deposits");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "الأعمدة الموجودة الآن هي: <span style='color:white'>" . implode(", ", $cols) . "</span><br>";
        
        if(!in_array('amount', $cols)) {
            echo "<span style='color:red'>❌ كارثة: العمود 'amount' غير موجود فعلياً في هذه القاعدة!</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>❌ الجدول 'deposits' غير موجود أصلاً في هذه القاعدة!</span><br>";
    }

    // 3. الحذف وإعادة الإنشاء
    echo "<hr><h3>3. إعادة بناء الجدول:</h3>";
    
    $pdo->exec("DROP TABLE IF EXISTS deposits");
    echo "حذف الجدول القديم ... <span style='color:green'>تم ✅</span><br>";

    $sql = "CREATE TABLE deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        transaction_id VARCHAR(255) NULL,
        proof VARCHAR(255) NULL,
        status VARCHAR(50) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "إنشاء الجدول الجديد مع عمود amount ... <span style='color:green'>تم ✅</span><br>";

    // 4. اختبار عملي (Insert Test)
    echo "<hr><h3>4. اختبار الإدخال (Test Insert):</h3>";
    
    $testStmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, proof, transaction_id, status) VALUES (1, 50.00, 'TEST_PROOF', 'TEST_TXID', 'pending')");
    $testStmt->execute();
    
    echo "محاولة إدخال صف تجريبي ... <span style='color:green; font-size:1.5em;'>نجحت العملية! 🎉</span><br>";
    echo "تم التأكد أن عمود amount يعمل.";

    // تنظيف
    $pdo->exec("TRUNCATE TABLE deposits");

    echo "<hr><h2 style='color:white; border:2px solid green; padding:10px; display:inline-block;'>✅ انتهى الإصلاح. اذهب لصفحة الإيداع الآن.</h2>";
    echo "<br><br><a href='deposit_auto.php' style='background:gold; color:black; padding:10px; text-decoration:none;'>الذهاب للإيداع</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ فشلت العملية:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>راجع بيانات ملف config.php وتأكد من صلاحيات المستخدم.</p>";
}
echo "</body>";
?>