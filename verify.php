<?php
// verify.php - (تم التحديث ليتوافق مع الجلسات Sessions والتصميم الخاص بك)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';

$lang = $_SESSION['lang'] ?? 'ar';
$msg = "";

$upload_dir = 'uploads/kyc/';
$selfie_img = "";
$front_img = "";
$back_img = "";
$email = "";

// جلب معلومات المستخدم غير المفعل وعرض صوره من الجلسة (وليس قاعدة البيانات)
if (isset($_SESSION['pending_reg'])) {
    $reg = $_SESSION['pending_reg'];
    $email = $reg['email'];
    
    if (!empty($reg['selfie_img'])) $selfie_img = $upload_dir . htmlspecialchars($reg['selfie_img']);
    if (!empty($reg['front_img'])) $front_img = $upload_dir . htmlspecialchars($reg['front_img']);
    if (!empty($reg['back_img'])) $back_img = $upload_dir . htmlspecialchars($reg['back_img']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $msg = ($lang == 'ar') ? 'الرجاء إدخال الرمز' : 'Please enter the code';
    } elseif (!isset($_SESSION['pending_reg'])) {
        $msg = ($lang == 'ar') ? 'انتهت صلاحية الجلسة، يرجى التسجيل من جديد' : 'Session expired, please register again';
    } else {
        // التحقق من صحة الرمز من الجلسة
        if ($_SESSION['pending_reg']['otp'] == $code) {
            try {
                $pdo->beginTransaction();
                $reg = $_SESSION['pending_reg'];
                
                // إدراج البيانات في قاعدة البيانات لأول مرة كحساب مفعل
                $sql = "INSERT INTO users (username, email, password, region, referral_code, referred_by, kyc_front, kyc_back, kyc_selfie, kyc_front_hash, kyc_back_hash, kyc_selfie_hash, balance, profit_balance, status, kyc_status, verification_code, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'active', 'pending', ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $reg['username'], $reg['email'], $reg['password'], $reg['region'], 
                    $reg['referral_code'], $reg['referred_by'], $reg['front_img'], 
                    $reg['back_img'], $reg['selfie_img'], $reg['front_hash'], 
                    $reg['back_hash'], $reg['selfie_hash'], $reg['otp']
                ]);
                
                $new_user_id = $pdo->lastInsertId();
                
                // إضافة سجل الإحالة إذا كان هناك مُحيل (بشرط وجود جدول referrals)
                if (!empty($reg['referred_by'])) {
                    $checkRef = $pdo->prepare("SELECT id FROM referrals WHERE referred_id = ?");
                    $checkRef->execute([$new_user_id]);
                    if (!$checkRef->fetch()) {
                        try {
                            $stmtRef = $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, is_active, total_deposit, created_at) VALUES (?, ?, 0, 0, NOW())");
                            $stmtRef->execute([$reg['referred_by'], $new_user_id]);
                        } catch (Exception $e) {
                            // تجاهل الخطأ إذا كان جدول referrals غير موجود
                        }
                    }
                }
                
                $pdo->commit();
                
                // تسجيل الدخول تلقائياً للمستثمر
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $reg['username'];
                $_SESSION['role'] = 'investor';
                
                // تفريغ الجلسة (لأنه تم تأكيد الحساب)
                unset($_SESSION['pending_reg']);
                
                $success_msg = ($lang == 'ar') ? 'تم تفعيل حسابك بنجاح! جارٍ تحويلك إلى لوحة التحكم...' : 'Account activated successfully! Redirecting...';
                echo "<script>alert('".addslashes($success_msg)."'); window.location.href='dashboard.php';</script>";
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = ($lang == 'ar') ? 'حدث خطأ أثناء التفعيل، حاول مرة أخرى' : 'Activation error, please try again';
                error_log("Verification Error: " . $e->getMessage());
            }
        } else {
            $msg = ($lang == 'ar') ? 'رمز التحقق غير صحيح' : 'Invalid verification code';
        }
    }
}
?>
<!DOCTYPE html>
<html dir="<?php echo ($lang=='ar')?'rtl':'ltr'; ?>" lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($lang=='ar')?'التحقق من البريد':'Email Verification'; ?></title>
    <style>
        body { background: #020617; color: #fff; font-family: system-ui; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .box { background: #0f172a; padding: 30px; border-radius: 16px; border: 1px solid #d4af37; text-align: center; width: 100%; max-width: 500px; box-sizing: border-box; }
        .images-container { margin: 15px 0; padding: 10px; background: rgba(0,0,0,0.3); border-radius: 12px; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }
        .image-card { text-align: center; flex: 1; min-width: 100px; }
        .image-card img { max-width: 120px; max-height: 120px; border-radius: 12px; border: 2px solid #d4af37; object-fit: cover; }
        .image-label { font-size: 11px; color: #94a3b8; margin-top: 5px; }
        .selfie-note { font-size: 12px; color: #94a3b8; margin-top: 5px; }
        input { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 8px; text-align: center; letter-spacing: 4px; font-size: 20px; box-sizing: border-box; outline: none; }
        input:focus { border-color: #d4af37; }
        button { background: linear-gradient(135deg, #d4af37, #b8860b); color: #000; border: none; padding: 14px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 16px; transition: 0.3s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212,175,55,0.3); }
        .err { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        .resend-link { margin-top: 20px; font-size: 13px; }
        .resend-link a { color: #d4af37; text-decoration: none; font-weight: bold; cursor: pointer; }
        .email-info { background: #1e293b; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; word-break: break-all; border: 1px dashed #334155; }
    </style>
</head>
<body>
<div class="box">
    <h2 style="color: #d4af37; margin-top: 0;"><?php echo ($lang=='ar')?'تفعيل الحساب':'Activate Account'; ?></h2>
    
    <div class="images-container">
        <?php if ($selfie_img && file_exists($selfie_img)): ?>
        <div class="image-card">
            <img src="<?php echo $selfie_img; ?>" alt="Selfie">
            <div class="image-label"><?php echo ($lang=='ar')?'صورة السيلفي':'Selfie'; ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($front_img && file_exists($front_img)): ?>
        <div class="image-card">
            <img src="<?php echo $front_img; ?>" alt="ID Front">
            <div class="image-label"><?php echo ($lang=='ar')?'بطاقة الهوية (أمامي)':'ID Card (Front)'; ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($back_img && file_exists($back_img)): ?>
        <div class="image-card">
            <img src="<?php echo $back_img; ?>" alt="ID Back">
            <div class="image-label"><?php echo ($lang=='ar')?'بطاقة الهوية (خلفي)':'ID Card (Back)'; ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (!$selfie_img && !$front_img && !$back_img): ?>
    <div class="selfie-note" style="color: #ef4444; margin-bottom: 15px;">
        <?php echo ($lang=='ar')?'لا يوجد حساب بانتظار التفعيل، أو انتهت صلاحية الجلسة.' : 'No pending account found, or session expired.'; ?>
    </div>
    <a href="register.php" style="color:var(--gold); font-size: 14px; text-decoration: none; display: block; margin-bottom: 15px;"><?php echo ($lang=='ar')?'العودة لصفحة التسجيل':'Back to Register'; ?></a>
    <?php else: ?>
    <div class="selfie-note" style="margin-bottom: 15px;">
        <?php echo ($lang=='ar')?'يرجى التأكد من أن هذه الصور تخصك قبل متابعة التفعيل.' : 'Please confirm these images belong to you before proceeding.'; ?>
    </div>
    
    <div class="email-info"><?php echo htmlspecialchars($email); ?></div>
    
    <?php if($msg) echo "<div class='err'>".htmlspecialchars($msg)."</div>"; ?>
    
    <form method="POST">
        <input type="text" name="code" placeholder="<?php echo ($lang=='ar')?'أدخل الرمز السري هنا':'Enter Verification Code'; ?>" maxlength="6" required autocomplete="off">
        <button type="submit"><?php echo ($lang=='ar')?'تفعيل الحساب':'Activate'; ?></button>
    </form>
    
    <div class="resend-link">
        <a onclick="resendCode()"><?php echo ($lang=='ar')?'إعادة إرسال الرمز':'Resend code'; ?></a>
    </div>
    <?php endif; ?>
</div>

<script>
    function resendCode() {
        const email = "<?php echo addslashes($email); ?>";
        if(!email) {
            alert("<?php echo ($lang=='ar')?'البريد الإلكتروني مفقود':'Email is missing'; ?>");
            return;
        }
        
        const fd = new FormData();
        fd.append('ajax_action', 'resend_code');
        fd.append('email', email);
        
        fetch('register.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if(d.status === 'success') {
                    alert("<?php echo ($lang=='ar')?'تم إرسال رمز جديد بنجاح إلى بريدك':'New code sent successfully to your email'; ?>"); 
                } else {
                    alert("<?php echo ($lang=='ar')?'حدث خطأ: ':'Error: '; ?>" + (d.msg || '')); 
                }
            })
            .catch(err => {
                alert("<?php echo ($lang=='ar')?'فشل الاتصال بالخادم':'Server connection failed'; ?>");
            });
    }
</script>
</body>
</html>