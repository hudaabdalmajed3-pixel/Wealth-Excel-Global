<?php
// api-deposit.php - Handles Instant Deposit & Auto-Upgrade Logic
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_deposit') {
    
    if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
    
    $user_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']);
    $tx_id = trim($_POST['transaction_id']);
    
    if ($amount <= 0 || empty($tx_id)) {
        die("يرجى إدخال مبلغ صحيح ورقم معاملة.");
    }

    try {
        $pdo->beginTransaction();

        // 1. تسجيل الإيداع
        $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount, transaction_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->execute([$user_id, $amount, $tx_id]);

        // 2. 🔥 التفعيل الفوري: إضافة المبلغ للرصيد
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $user_id]);

        // 3. 🔥 إعادة حساب الباقة تلقائياً (Auto-Upgrade)
        // أ. جلب الرصيد الجديد
        $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmtBal->execute([$user_id]);
        $new_balance = $stmtBal->fetchColumn();

        // ب. اختيار أغلى باقة يغطيها الرصيد
        $stmtPlan = $pdo->prepare("SELECT * FROM plans WHERE price <= ? ORDER BY price DESC LIMIT 1");
        $stmtPlan->execute([$new_balance]);
        $newPlan = $stmtPlan->fetch();

        if ($newPlan) {
            // إغلاق الاستثمار السابق
            $pdo->prepare("UPDATE investments SET status = 'completed' WHERE user_id = ? AND status = 'active'")->execute([$user_id]);

            // فتح الاستثمار الجديد
            $pdo->prepare("INSERT INTO investments (user_id, plan_id, plan_name, amount, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())")
                ->execute([$user_id, $newPlan['id'], $newPlan['name'], $new_balance]);
            
            // تحديث هوية الباقة في جدول المستخدم
            $pdo->prepare("UPDATE users SET plan_id = ? WHERE id = ?")->execute([$newPlan['id'], $user_id]);
        }

        // 4. إشعار
        $msgUser = "تم إضافة $amount$ بنجاح. رصيدك الحالي $new_balance$.";
        $pdo->prepare("INSERT INTO messages (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())")->execute([$user_id, $msgUser]);

        $pdo->commit();

        echo "<script>alert('تم الإيداع وتحديث الباقة بنجاح!'); window.location.href='dashboard.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("خطأ: " . $e->getMessage());
    }
}
?>