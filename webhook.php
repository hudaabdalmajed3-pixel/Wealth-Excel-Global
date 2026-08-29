<?php
// webhook.php - نسخة التتبع واكتشاف الأخطاء (Debug Version)
require 'config.php';

// 🔴 ضع مفتاح الـ IPN Secret الخاص بك هنا بين علامتي التنصيص 🔴
$ipn_secret ="SMpyzP+4KZ2yDOklyoOyoZZi8/9DgA8i"; 

// دالة لكتابة تقرير عن الخطأ في ملف نصي
function write_log($msg) {
    file_put_contents('ipn_debug.txt', date('Y-m-d H:i:s') . " - " . $msg . PHP_EOL, FILE_APPEND);
}

write_log("=== استلام إشعار جديد ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    write_log("خطأ: الطلب ليس POST");
    http_response_code(405);
    die('Method Not Allowed');
}

$request_json = file_get_contents('php://input');
write_log("البيانات المستلمة: " . $request_json);

$headers = getallheaders();
$x_signature = $headers['x-nowpayments-sig'] ?? $headers['X-Nowpayments-Sig'] ?? '';

if (empty($x_signature)) {
    write_log("خطأ: التوقيع مفقود (لا يوجد توقيع من NOWPayments)");
    http_response_code(403);
    die('No Signature Provided');
}

$hmac = hash_hmac("sha512", $request_json, $ipn_secret);
if ($hmac !== $x_signature) {
    write_log("خطأ: التوقيع غير متطابق! المفتاح السري الذي أدخلته غير صحيح.");
    http_response_code(403);
    die('Invalid Signature');
}

write_log("نجاح: التوقيع متطابق والمفتاح صحيح.");

$data = json_decode($request_json, true);
$status = $data['payment_status'] ?? '';

if ($status === 'finished' || $status === 'partially_paid') {
    $actually_paid = floatval($data['actually_paid'] ?? 0);
    $txn_id = $data['payment_id'] ?? '';
    $order_id = $data['order_id'] ?? '';
    
    $parts = explode('_', $order_id);
    $user_id = isset($parts[1]) ? (int)$parts[1] : 0;
    
    write_log("تحليل البيانات: مستخدم رقم $user_id | مبلغ $actually_paid | مرجع $txn_id");

    if ($user_id > 0 && $actually_paid > 0) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM auto_deposits WHERE txn_id = ?");
            $stmt->execute([$txn_id]);
            
            if ($stmt->rowCount() == 0) {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO auto_deposits (user_id, txn_id, amount, currency, status, created_at) VALUES (?, ?, ?, 'USDT', 'completed', NOW())")
                    ->execute([$user_id, $txn_id, $actually_paid]);
                
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                    ->execute([$actually_paid, $user_id]);
                
                $msg = "✅ تم استلام إيداع ناجح بقيمة $" . number_format($actually_paid, 2) . " وإضافته لرصيدك.";
                $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")
                    ->execute([$user_id, $msg]);
                
                $pdo->commit();
                write_log("✅ ممتاز: تم تحديث الرصيد للمستخدم بنجاح!");
                echo "Success: Balance Updated.";
            } else {
                write_log("تنبيه: هذه العملية تم تسجيلها مسبقاً في قاعدة البيانات.");
                echo "Already Processed.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            write_log("خطأ في قاعدة البيانات: " . $e->getMessage());
            http_response_code(500);
            die("Database Error");
        }
    } else {
        write_log("خطأ: رقم المستخدم غير صحيح أو المبلغ صفر.");
        echo "Invalid User or Amount.";
    }
} else {
    write_log("تجاهل: حالة الدفعة حالياً هي " . $status);
    echo "Status ignored: " . $status;
}
write_log("=======================\n");
?>