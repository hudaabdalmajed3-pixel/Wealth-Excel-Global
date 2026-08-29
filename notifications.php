<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';
$user_id = $_SESSION['user_id'];
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // 2. تحديث الحالة إلى "مقروء"
    $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $updateStmt->execute([$user_id]);

    // 3. جلب الإشعارات
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? OR user_id IS NULL 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wealth Xcel | Notifications</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* تنسيقات الصفحة */
        body {
            background-color: var(--bg-black);
            color: var(--text-white);
            font-family: var(--font-main);
            margin: 0;
        }

        .notify-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            min-height: 60vh;
        }

        .header-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .page-title {
            color: var(--gold);
            font-size: 1.8rem;
            margin: 0;
        }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: var(--text-white);
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: 0.3s;
            border: 1px solid var(--border-color);
        }
        .btn-back:hover {
            background: var(--gold);
            color: black;
            border-color: var(--gold);
        }
        
        .msg-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--gold);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            transition: all 0.3s ease;
        }

        .msg-card:hover {
            transform: translateX(5px);
            border-color: var(--gold);
            background: rgba(255, 255, 255, 0.02);
        }
        
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 8px;
        }

        .msg-title {
            color: var(--text-white);
            font-weight: bold;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .msg-time {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-family: monospace;
        }

        .msg-body {
            color: #ddd;
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-line;
        }
        
        .icon-bell { color: var(--gold); }

        .empty-state {
            text-align: center;
            color: var(--text-muted);
            padding: 60px 20px;
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            background: rgba(255,255,255,0.01);
        }
        .empty-icon { font-size: 40px; color: #333; margin-bottom: 15px; display: block; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?> 

    <div class="notify-container">
        
        <div class="header-area">
            <h1 class="page-title" data-i18n="title">System Notifications</h1>
            <a href="dashboard.php" class="btn-back" data-i18n="back">← Dashboard</a>
        </div>

        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $msg): ?>
                <div class="msg-card">
                    <div class="msg-header">
                        <h3 class="msg-title">
                            <i class="fa-solid fa-bell icon-bell"></i> 
                            <?php echo htmlspecialchars($msg['title']); ?>
                        </h3>
                        <span class="msg-time num-conv"><?php echo date('Y-m-d h:i A', strtotime($msg['created_at'])); ?></span>
                    </div>
                    <div class="msg-body">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-regular fa-bell-slash empty-icon"></i>
                <span data-i18n="empty">No new notifications at the moment.</span>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const i18nNotify = {
            en: { title: "System Notifications", empty: "No new notifications at the moment.", back: "← Dashboard" },
            ar: { title: "إشعارات النظام", empty: "لا توجد إشعارات جديدة حالياً.", back: "عودة للوحة التحكم →" }
        };

        function translateNumbers(str, lang) {
            const arNums = ["٠","١","٢","٣","٤","٥","٦","٧","٨","٩"];
            if(lang === 'ar') return str.toString().replace(/[0-9]/g, d => arNums[d]);
            return str;
        }

        // دالة التحديث التي يستدعيها الهيدر الموحد عند تغيير اللغة
        function updatePageContent(lang) {
            // ترجمة النصوص
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if(i18nNotify[lang][key]) el.innerText = i18nNotify[lang][key];
            });

            // تعديل اتجاه زر العودة
            const backBtn = document.querySelector('.btn-back');
            if(backBtn) {
                backBtn.style.float = (lang === 'ar') ? 'left' : 'right';
                // إعادة ضبط الطفو بعد الهيدر
                backBtn.parentElement.style.direction = (lang === 'ar') ? 'rtl' : 'ltr';
            }

            // ترجمة الأرقام
            document.querySelectorAll('.num-conv').forEach(el => {
                if(!el.dataset.orig) el.dataset.orig = el.innerText;
                el.innerText = translateNumbers(el.dataset.orig, lang);
            });
        }
    </script>

</body>
</html>