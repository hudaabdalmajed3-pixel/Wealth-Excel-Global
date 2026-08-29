<?php
// scan_deposits.php - يُشغل عبر Cron Job (CLI)

// 1. استدعاء الإعدادات والاتصال بقاعدة البيانات
// هذا الملف سيوفر لنا المتغير $pdo للاتصال بالداتابيز والمتغير $trongrid_api_key
require 'config.php'; 

try {
    // جلب محفظة المنصة (Admin Wallet) من قاعدة البيانات
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'deposit_wallet'");
    $stmt->execute();
    $admin_wallet = $stmt->fetchColumn();

    if (empty($admin_wallet)) {
        die("Error: Admin wallet is not set in database.\n");
    }

    // 2. جلب جميع الطلبات المعلقة
    $pending_stmt = $pdo->query("SELECT * FROM auto_deposits WHERE status = 'pending'");
    $pending_deposits = $pending_stmt->fetchAll();

    if (empty($pending_deposits)) {
        die("No pending deposits. Exiting.\n");
    }

    // 3. الاتصال بـ TronGrid API لجلب آخر 50 تحويل USDT للمحفظة
    $contract_address = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // عقد USDT TRC20
    $apiUrl = "https://api.trongrid.io/v1/accounts/{$admin_wallet}/transactions/trc20?limit=50&contract_address={$contract_address}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    // إضافة المفتاح الخارجي القادم من ملف config.php في ترويسة الطلب
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "TRON-PRO-API-KEY: {$trongrid_api_key}",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // التحقق من نجاح الاتصال بالـ API
    if ($response === false || $http_code !== 200) {
        die("API Connection Error. HTTP Code: {$http_code}\n");
    }

    $data = json_decode($response, true);
    if (!isset($data['data']) || empty($data['data'])) {
        die("No recent transactions found.\n");
    }

    $transactions = $data['data'];

    // 4. مطابقة التحويلات مع الطلبات المعلقة
    foreach ($transactions as $tx) {
        $txid = $tx['transaction_id'];
        $to_address = $tx['to'];
        
        // تحويل القيمة (USDT له 6 أصفار عشرية)
        $actual_amount = floatval($tx['value']) / pow(10, 6);

        // التأكد من أن التحويل كان لمحفظة المنصة فعلاً
        if (strtolower($to_address) !== strtolower($admin_wallet)) continue;

        // البحث في الطلبات المعلقة
        foreach ($pending_deposits as $pending) {
            $expected_amount = floatval($pending['amount']);
            
            // المطابقة مع نسبة تسامح بسيطة جداً لفروق التقريب العشري
            if (abs($actual_amount - $expected_amount) < 0.0001) { 
                
                // التأكد من أن هذا الـ TXID لم يتم استخدامه مسبقاً (حماية من الدفع المزدوج)
                $check_txid = $pdo->prepare("SELECT id FROM auto_deposits WHERE txn_id = ?");
                $check_txid->execute([$txid]);
                
                if ($check_txid->rowCount() == 0) {
                    // تمت المطابقة بنجاح! نبدأ بتحديث البيانات
                    try {
                        $pdo->beginTransaction();
                        
                        $u_id = $pending['user_id'];
                        $dep_id = $pending['id'];
                        
                        // جلب رصيد المستخدم الحالي مع قفله برمجياً حتى انتهاء التحديث (FOR UPDATE)
                        $u_data_stmt = $pdo->query("SELECT balance FROM users WHERE id = $u_id FOR UPDATE");
                        $u_data = $u_data_stmt->fetch();
                        $new_total_balance = $u_data['balance'] + $actual_amount;
                        
                        // تحديث طلب الإيداع ليصبح مكتملاً
                        $pdo->prepare("UPDATE auto_deposits SET status = 'completed', txn_id = ? WHERE id = ?")
                            ->execute([$txid, $dep_id]);
                            
                        // تحديث رصيد المستخدم
                        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")
                            ->execute([$new_total_balance, $u_id]);
                            
                        // إرسال إشعار للمستخدم
                        $msg_txt = "✅ تم تأكيد إيداعك التلقائي بمبلغ ($actual_amount$). رأس المال الإجمالي: $new_total_balance$";
                        $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$u_id, $msg_txt]);
                        
                        $pdo->commit();
                        echo "Processed TXID: $txid for User: $u_id \n";
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo "Error processing TXID: $txid | Error: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
    }

    echo "Scan complete.\n";

} catch (PDOException $e) {
    // التقاط أي أخطاء متعلقة بقاعدة البيانات أثناء التنفيذ
    die("Database Error: " . $e->getMessage() . "\n");
}
?>