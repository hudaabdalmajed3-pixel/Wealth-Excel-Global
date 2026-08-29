<?php
// register.php - نسخة معدلة للعمل بامتياز مع تطبيقات WebView وطلب إذن الكاميرا (بدون رفع صور)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php'; 
require_once 'otp_helper.php';

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'resend_code') {
    header('Content-Type: application/json');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (isset($_SESSION['pending_reg']) && $_SESSION['pending_reg']['email'] === $email) {
        $new_code = rand(100000, 999999);
        $_SESSION['pending_reg']['otp'] = $new_code; 
        
        $mail_sent = sendVerificationEmail($email, $new_code);
        
        if($mail_sent) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Mail Server Error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'انتهت صلاحية الجلسة، يرجى التسجيل من جديد']);
    }
    exit;
}

$referrer_name = ""; 
$invite_code_val = ""; 

function getReferrerInfo($pdo, $column, $value) {
    $stmt = $pdo->prepare("SELECT id, username, referral_code FROM users WHERE $column = ? LIMIT 1");
    $stmt->execute([$value]);
    return $stmt->fetch();
}

$referrer_data = false;

if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referrer_data = getReferrerInfo($pdo, 'id', intval($_GET['ref']));
} 
elseif (isset($_GET['invite']) && !empty($_GET['invite'])) {
    $referrer_data = getReferrerInfo($pdo, 'referral_code', trim($_GET['invite']));
}

if ($referrer_data) {
    $referrer_name = htmlspecialchars($referrer_data['username']);
    $invite_code_val = htmlspecialchars($referrer_data['referral_code']);
}

$message = ""; 

function saveBase64Image($base64String, $targetDir, $prefix) {
    if (empty($base64String)) return false;
    if (strpos($base64String, 'base64,') !== false) {
        $image_parts = explode(";base64,", $base64String);
        $base64String = $image_parts[1];
    }
    $image_base64 = base64_decode($base64String);
    if(!$image_base64) return false;
    $fileName = uniqid() . '_' . $prefix . '.jpg';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    if (file_put_contents($targetDir . $fileName, $image_base64)) return $fileName;
    return false;
}

function verifyRealEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "format_error";
    $domain = substr(strrchr($email, "@"), 1);
    $disposable_domains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com'];
    if (in_array(strtolower($domain), $disposable_domains)) return "fake_domain";
    return "valid";
}

function sendVerificationEmail($email, $code) {
    $msg = "
    <div style='background:#0f172a; padding:30px; text-align:center; border-radius:10px; border:1px solid #d4af37; color:#fff; font-family:Arial, sans-serif;'>
        <h2 style='color:#d4af37; margin-top:0;'>Wealth Xcel</h2>
        <p style='color:#ccc;'>Thank you for registering. Your verification code is:</p>
        <h1 style='background:#1e293b; padding:15px; border-radius:8px; color:#10b981; letter-spacing:5px;'>$code</h1>
        <p style='font-size:12px; color:#888;'>Please enter this code on the verification page.</p>
    </div>";
    
    return sendEmailSMTP($email, "Verification Code - Wealth Xcel", $msg);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_action'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $region = htmlspecialchars(trim($_POST['region'])); 
    $input_referral_code = trim($_POST['referral_code']); 
    $terms_agreed = isset($_POST['terms']) ? true : false;
    
    $idFrontBase64 = $_POST['id_front_base64'] ?? '';
    $idBackBase64 = $_POST['id_back_base64'] ?? '';
    $selfieBase64 = $_POST['selfie_base64'] ?? '';

    $emailCheck = verifyRealEmail($email);

    if (empty($username) || empty($email) || empty($password) || empty($region)) { $message = "fields_empty"; }
    elseif (!$terms_agreed) { $message = "terms_required"; }
    elseif ($emailCheck !== "valid") { $message = "email_invalid"; }
    elseif (empty($idFrontBase64) || empty($idBackBase64) || empty($selfieBase64)) { $message = "kyc_empty"; }
    else {
        $frontHash = md5($idFrontBase64);
        $backHash = md5($idBackBase64);
        $selfieHash = md5($selfieBase64);

        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE email = ? OR username = ? OR kyc_front_hash = ? OR kyc_back_hash = ? OR kyc_selfie_hash = ? LIMIT 1");
        $stmt->execute([$email, $username, $frontHash, $backHash, $selfieHash]);
        $existingUser = $stmt->fetch();

        if ($existingUser) { 
            if (strtolower($existingUser['email']) === strtolower($email)) {
                $message = "email_exists";
            } elseif (strtolower($existingUser['username']) === strtolower($username)) {
                $message = "username_exists";
            } else {
                $message = "kyc_exists";
            }
        } else {
            $referred_by_id = null;
            if (!empty($input_referral_code)) {
                $refInfo = getReferrerInfo($pdo, 'referral_code', $input_referral_code);
                if($refInfo) $referred_by_id = $refInfo['id'];
            }

            $uploadDir = 'uploads/kyc/';
            $frontImgName = saveBase64Image($idFrontBase64, $uploadDir, 'front');
            $backImgName = saveBase64Image($idBackBase64, $uploadDir, 'back');
            $selfieImgName = saveBase64Image($selfieBase64, $uploadDir, 'selfie');

            if (!$frontImgName || !$backImgName || !$selfieImgName) { 
                $message = "upload_fail"; 
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $my_new_referral_code = 'u' . bin2hex(random_bytes(4)); 
                $verification_code = rand(100000, 999999);

                $_SESSION['pending_reg'] = [
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashed_password,
                    'region' => $region,
                    'referral_code' => $my_new_referral_code,
                    'referred_by' => $referred_by_id,
                    'front_img' => $frontImgName,
                    'back_img' => $backImgName,
                    'selfie_img' => $selfieImgName,
                    'front_hash' => $frontHash,
                    'back_hash' => $backHash,
                    'selfie_hash' => $selfieHash,
                    'otp' => $verification_code
                ];

                sendVerificationEmail($email, $verification_code);

                header("Location: verify.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Register - Wealth Xcel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@2.0.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    
    <style>
        :root { --gold: #d4af37; --dark: #020617; --dark-soft: #0f172a; --white: #ffffff; --success: #10b981; --danger: #ef4444; }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: radial-gradient(circle at top, #111827 0, #020617 55%); color: var(--white); min-height: 100vh; margin: 0; display: flex; flex-direction: column; align-items: center; overflow-x: hidden;}

        header { width: 100%; padding: 15px; display: flex; justify-content: flex-end; position: absolute; top: 0; z-index: 100; }
        .lang-btn { background: rgba(255,255,255,0.1); border: 1px solid #444; color: #aaa; padding: 5px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; margin-left: 5px; }
        .lang-btn.active { border-color: var(--gold); color: var(--gold); background: rgba(212, 175, 55, 0.1); }

        .register-card { background: rgba(15, 23, 42, 0.95); border-radius: 12px; padding: 25px; border: 1px solid rgba(212, 175, 55, 0.15); box-shadow: 0 10px 30px rgba(0,0,0,0.5); width: 90%; max-width: 420px; text-align: center; margin-top: 60px; margin-bottom: 40px; }
        h2 { color: var(--gold); margin: 0 0 20px 0; font-size: 20px; }
        
        input { width: 100%; padding: 12px; margin-bottom: 12px; background: var(--dark-soft); border: 1px solid #1e293b; color: var(--white); border-radius: 8px; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--gold); }

        .referral-badge { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 8px; border-radius: 6px; font-size: 12px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        
        .modal { display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
        .modal-content { background-color: var(--dark-soft); margin: 10% auto; padding: 20px; border: 1px solid var(--gold); width: 90%; max-width: 500px; border-radius: 10px; color: #fff; max-height: 80vh; overflow-y: auto; text-align: left; }
        body[dir="rtl"] .modal-content { text-align: right; }
        .close-modal { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height:1;}
        body[dir="rtl"] .close-modal { float: left; }
        .policy-text { line-height: 1.6; font-size: 13px; color: #ddd; white-space: pre-line; margin-top: 15px;}
        
        .terms-box { display: flex; align-items: flex-start; gap: 10px; font-size: 12px; color: #ccc; margin-bottom: 20px; text-align: left; }
        body[dir="rtl"] .terms-box { text-align: right; }
        .terms-box input[type="checkbox"] { width: 18px; height: 18px; margin: 0; accent-color: var(--gold); cursor: pointer; }
        .terms-box a { color: var(--gold); text-decoration: none; border-bottom: 1px dashed var(--gold); cursor: pointer; }
        
        .custom-select-wrapper { position: relative; width: 100%; margin-bottom: 12px; }
        .custom-select { position: relative; display: flex; flex-direction: column; }
        .custom-select__trigger { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--dark-soft); border: 1px solid #1e293b; border-radius: 8px; cursor: pointer; color: #fff; height: 45px; }
        .custom-options { position: absolute; display: block; top: 100%; left: 0; right: 0; border: 1px solid var(--gold); border-top: 0; background: var(--dark-soft); opacity: 0; visibility: hidden; pointer-events: none; z-index: 50; max-height: 250px; overflow-y: auto; border-radius: 0 0 8px 8px; }
        .custom-select.open .custom-options { opacity: 1; visibility: visible; pointer-events: all; }
        .option { padding: 10px; border-bottom: 1px solid #222; cursor: pointer; color: #ccc; }
        .option:hover { background: var(--gold); color: #000; }
        .search-box { padding: 10px; background: #000; position: sticky; top: 0; z-index: 51; }
        .search-box input { margin: 0; border: 1px solid #444; padding: 8px; font-size: 14px; }
        
        .scan-trigger { border: 2px dashed rgba(212, 175, 55, 0.4); background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; margin-bottom: 15px; transition: 0.3s; }
        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; font-weight: bold; padding: 12px; border: none; border-radius: 8px; cursor: pointer; transition: 0.3s; }
        .btn-submit:active { transform: scale(0.98); }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; border: 1px solid; display: none; }
        .alert.visible { display: block; }
        .alert-warning { background: rgba(239, 68, 68, 0.1); border-color: var(--danger); color: var(--danger); }

        /* Camera UI */
        .fs-camera { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 9999; display: none; flex-direction: column; align-items: center; justify-content: center; }
        video { width: 100%; height: 100%; object-fit: cover; }
        video.mirrored { transform: scaleX(-1); }
        
        .status-pill { position: absolute; top: 6%; background: rgba(0,0,0,0.85); color: #fff; padding: 12px 20px; border-radius: 12px; font-weight: bold; border: 2px solid var(--gold); z-index: 10001; text-align: center; min-width: 280px; max-width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.8); display: flex; flex-direction: column; align-items: center; justify-content: center;}
        
        #cam-msg { font-size: 15px; color: #eab308; line-height: 1.4; text-shadow: 0 2px 4px rgba(0,0,0,0.8); text-align: center;}
        .error-msg { color: #fff; background: var(--danger); padding: 5px 10px; border-radius: 6px; font-size: 13px; display: none; margin-top: 8px; width: 100%; text-align: center;}
        .success-msg { color: var(--success); font-size: 15px; margin-top: 5px; font-weight: bold; display: none;}
        .proc-loader { display: none; font-size: 14px; color: var(--gold); margin-top: 8px; align-items: center; justify-content: center; gap: 8px; font-weight: bold; width: 100%; }
        
        .guide-box { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%); z-index: 10000; transition: 0.3s; pointer-events: none;}
        .guide-rect { width: 85vw; height: 53vw; border-radius: 12px; border: 3px dashed rgba(255,255,255,0.7); display: none; box-shadow: 0 0 0 4000px rgba(0,0,0,0.7); }
        .guide-rect.active { border-color: var(--success); border-style: solid; box-shadow: 0 0 15px var(--success), 0 0 0 4000px rgba(0,0,0,0.7); }
        
        .guide-circle { width: 75vw; height: 75vw; max-width: 320px; max-height: 320px; border-radius: 50%; border: 4px dashed rgba(255,255,255,0.5); display: none; box-shadow: 0 0 0 4000px rgba(0,0,0,0.85); }
        
        .selfie-svg { position: absolute; top: -4px; left: -4px; width: calc(100% + 8px); height: calc(100% + 8px); transform: rotate(-90deg); pointer-events: none; }
        .selfie-svg circle { fill: none; stroke: var(--success); stroke-width: 6px; stroke-dasharray: 1000; stroke-dashoffset: 1000; transition: stroke-dashoffset 0.05s linear; stroke-linecap: round; }

        #manual-capture-btn { display: none; position: absolute; bottom: 15%; left: 50%; transform: translateX(-50%); width: 72px; height: 72px; border-radius: 50%; border: 4px solid #fff; background: rgba(255,255,255,0.2); align-items: center; justify-content: center; cursor: pointer; z-index: 10004; transition: 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.6); }
        #manual-capture-btn .inner { width: 54px; height: 54px; border-radius: 50%; background: #fff; transition: 0.2s; }
        #manual-capture-btn:active .inner { transform: scale(0.85); background: var(--success); }
        #manual-capture-btn.disabled { opacity: 0.5; pointer-events: none; }

        .flip-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.95); z-index: 10005; display: none; flex-direction: column; align-items: center; justify-content: center; color: var(--gold); }
        .scene { width: 250px; height: 160px; perspective: 600px; }
        .card-3d { width: 100%; height: 100%; position: relative; transition: transform 1s; transform-style: preserve-3d; animation: flip3d 3s infinite; }
        .card-face { position: absolute; width: 100%; height: 100%; backface-visibility: hidden; border-radius: 10px; border: 2px solid var(--gold); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 20px; color: #000; }
        .card-front { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); }
        .card-back { background: linear-gradient(135deg, #cbd5e1, #94a3b8); transform: rotateY(180deg); }
        @keyframes flip3d { 0% { transform: rotateY(0); } 50% { transform: rotateY(180deg); } 100% { transform: rotateY(180deg); } }
        
        .btn-close-cam { position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.5); color: #fff; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 20px; z-index: 10003; cursor: pointer; }
        
        .loading-models { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 20000; display: none; justify-content: center; align-items: center; flex-direction: column; color: var(--gold); font-size: 18px; }
        .spinner { width: 50px; height: 50px; border: 5px solid rgba(212,175,55,0.3); border-top: 5px solid var(--gold); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .retry-btn { position: absolute; bottom: 30%; left: 50%; transform: translateX(-50%); padding: 14px 28px; background: var(--gold); color: #000; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; z-index: 10006; cursor: pointer; box-shadow: 0 4px 15px rgba(212,175,55,0.4); }
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-models">
        <div class="spinner"></div>
        <div data-i18n="loading_ai">جاري تجهيز الكاميرا الأمنية...</div>
    </div>

    <header>
        <button class="lang-btn" id="btn-en" onclick="setLang('en')">EN</button>
        <button class="lang-btn" id="btn-ar" onclick="setLang('ar')">AR</button>
    </header>

    <div class="register-card">
        
        <h2 data-i18n="create_acc">تسجيل حساب جديد</h2>
        
        <?php if($message != ""): ?>
            <div class="alert alert-warning visible">
                <?php 
                    if($message=="fields_empty") echo "<span data-i18n='err_fields'>املأ جميع الحقول</span>";
                    if($message=="kyc_empty") echo "<span data-i18n='err_kyc'>أكمل التوثيق</span>";
                    if($message=="email_exists") echo "<span data-i18n='err_email'>البريد الإلكتروني مسجل مسبقاً</span>";
                    if($message=="username_exists") echo "<span data-i18n='err_user'>اسم المستخدم محجوز مسبقاً</span>";
                    if($message=="kyc_exists") echo "<span data-i18n='err_kyc_dup'>بيانات الهوية أو الوجه مستخدمة في حساب آخر</span>";
                    if($message=="upload_fail") echo "<span data-i18n='err_upload'>فشل الرفع</span>";
                    if($message=="email_invalid") echo "<span data-i18n='err_email_fmt'>تنسيق البريد غير صحيح</span>";
                    if($message=="terms_required") echo "<span data-i18n='err_terms'>يجب الموافقة على الشروط</span>";
                    if($message=="system_error") echo "System Error";
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateForm()">
            <input type="text" name="username" data-ph="ph_user" placeholder="اسم المستخدم" required>
            <input type="email" name="email" data-ph="ph_email" placeholder="البريد الإلكتروني" required>
            <input type="password" name="password" data-ph="ph_pass" placeholder="كلمة المرور" required>
            
            <div class="custom-select-wrapper">
                <div class="custom-select">
                    <div class="custom-select__trigger">
                        <span id="select-label" data-i18n="ph_country">اختر الدولة</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="custom-options">
                        <div class="search-box"><input type="text" id="country-search" placeholder="Search / بحث..."></div>
                        <div id="country-options-list"></div>
                    </div>
                </div>
                <input type="hidden" name="region" id="region-input" required>
            </div>

            <div style="position: relative;">
                <?php if (!empty($referrer_name)): ?>
                <div class="referral-badge">
                    <i class="fa-solid fa-user-check"></i> <span data-i18n="invited_by">دعوة خاصة من:</span> <b style="margin-right:5px; margin-left:5px; color:#fff;"><?php echo $referrer_name; ?></b>
                </div>
                <?php endif; ?>
                <input type="text" name="referral_code" value="<?php echo $invite_code_val; ?>" data-ph="ph_ref" placeholder="كود الدعوة" <?php echo ($invite_code_val) ? 'readonly style="background:rgba(16, 185, 129, 0.05); border-color:#10b981;"' : ''; ?>>
            </div>

            <!-- زر التوثيق بالكاميرا فقط -->
            <div class="scan-trigger" onclick="startKYC()">
                <i class="fa-solid fa-id-card fa-2x" style="color: var(--gold);"></i>
                <div class="status-text" id="main-status" data-i18n="tap_scan" style="margin-top:10px; font-weight:bold;">اضغط لبدء التوثيق الذكي</div>
            </div>

            <div class="terms-box">
                <input type="checkbox" name="terms" id="terms_check" required>
                <label for="terms_check" data-i18n="terms_label">أوافق على <a onclick="openPolicy()">سياسة الخصوصية والشروط</a></label>
            </div>

            <input type="hidden" name="id_front_base64" id="val-front">
            <input type="hidden" name="id_back_base64" id="val-back">
            <input type="hidden" name="selfie_base64" id="val-selfie">

            <button type="submit" class="btn-submit" data-i18n="btn_submit">متابعة للتأكيد</button>
        </form>
        <p style="font-size:12px; color:#666; margin-top:15px;"><span data-i18n="have_acc">لديك حساب؟</span> <a href="login.php" style="color:var(--gold); text-decoration:none;" data-i18n="login_link">دخول</a></p>

    </div>

    <div id="policyModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closePolicy()">&times;</span>
            <h3 style="color:var(--gold); border-bottom:1px solid #444; padding-bottom:10px; margin-top:0;" data-i18n="policy_title">سياسة الخصوصية وشروط الاستخدام</h3>
            <div id="policyText" class="policy-text"></div>
        </div>
    </div>

    <div class="fs-camera" id="cam-ui">
        <button type="button" class="btn-close-cam" onclick="closeCamera()"><i class="fa-solid fa-xmark"></i></button>
        
        <div class="status-pill">
            <span id="cam-msg">جاري التحميل...</span>
            <div id="proc-loader" class="proc-loader"><i class="fa-solid fa-bolt"></i> <span id="proc-text">جاري الفحص...</span></div>
            <div id="err-msg" class="error-msg"></div>
            <div id="success-msg" class="success-msg"></div>
        </div>
        
        <video id="video" autoplay playsinline muted allow="camera; microphone"></video>
        
        <div id="guide-rect" class="guide-box guide-rect"></div>
        <div id="guide-circle" class="guide-box guide-circle">
            <svg class="selfie-svg" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="48" id="selfie-ring" />
            </svg>
        </div>

        <div class="flip-overlay" id="flip-anim">
            <div class="scene">
                <div class="card-3d">
                    <div class="card-face card-front">الأمام</div>
                    <div class="card-face card-back">الخلف</div>
                </div>
            </div>
            <h2 style="margin-top:30px; letter-spacing: 1px;" data-i18n="msg_flip">الرجاء قلب البطاقة للخلف</h2>
        </div>
        
        <div id="manual-capture-btn" onclick="manualCaptureCard()"><div class="inner"></div></div>
    </div>

    <script>
        const legalTexts = {
            ar: `تحرص منصة Wealth Xcel على تقديم تجربة استخدام آمنة...\n\nاستخدام المنصة متاح فقط للأشخاص الذين يقرون بقدرتهم القانونية.\nالمنصة ليست منصة ربح مضمون.`,
            en: `Wealth Xcel respects user privacy...\n\nAccess is limited to users who acknowledge legal capacity.\nThe platform does not provide guaranteed returns.`
        };

        const translations = {
            ar: {
                dir: 'rtl', create_acc: 'تسجيل حساب جديد', 
                err_fields: 'املأ جميع الحقول', err_kyc: 'أكمل التوثيق', err_email: 'البريد الإلكتروني مسجل مسبقاً', err_user: 'اسم المستخدم محجوز مسبقاً', err_kyc_dup: 'بيانات الهوية مسجلة في حساب آخر', err_upload: 'فشل الرفع', invited_by: 'دعوة خاصة من:',
                ph_user: 'اسم المستخدم', ph_email: 'البريد الإلكتروني', ph_pass: 'كلمة المرور', ph_country: 'اختر الدولة', ph_ref: 'كود الدعوة', tap_scan: 'اضغط لبدء التوثيق الذكي', btn_submit: 'متابعة للتأكيد', have_acc: 'لديك حساب؟', login_link: 'دخول',
                err_email_fmt: 'تنسيق البريد غير صحيح', terms_label: 'أوافق على <a onclick="openPolicy()">سياسة الخصوصية والشروط</a>', err_terms: 'يجب الموافقة على الشروط', policy_title: 'سياسة الخصوصية وشروط الاستخدام',
                msg_flip: 'الآن، اقلب البطاقة وصورها من الخلف',
                inst_front: '⚠️ ضع واجهة البطاقة داخل الإطار ثم اضغط زر التصوير', 
                inst_back: '⚠️ ضع خلفية البطاقة داخل الإطار ثم اضغط زر التصوير',
                inst_selfie: '⚠️ ضع وجهك داخل الدائرة وانظر إلى الكاميرا',
                ocr_scan: 'جاري معالجة الصورة...',
                loading_ai: 'جاري تجهيز الكاميرا الأمنية...',
                face_good: 'وجهك في المكان الصحيح، اثبت قليلاً',
                face_error: 'تأكد من أن وجهك في الدائرة وافتح عينيك',
                camera_retry: 'إعادة المحاولة'
            },
            en: {
                dir: 'ltr', create_acc: 'Create New Account', 
                err_fields: 'Fill all fields', err_kyc: 'Complete Verification', err_email: 'Email is already registered', err_user: 'Username already taken', err_kyc_dup: 'KYC data already in use', err_upload: 'Upload failed', invited_by: 'Invited by:',
                ph_user: 'Username', ph_email: 'Email Address', ph_pass: 'Password', ph_country: 'Select Country', ph_ref: 'Referral Code', tap_scan: 'Tap to start Smart Verification', btn_submit: 'Continue to Verify', have_acc: 'Have an account?', login_link: 'Login',
                err_email_fmt: 'Invalid Email Format', terms_label: 'I agree to <a onclick="openPolicy()">Privacy Policy & Terms</a>', err_terms: 'You must agree to terms', policy_title: 'Privacy Policy & Terms',
                msg_flip: 'Now, please flip the card to the back',
                inst_front: '⚠️ Place ID front in frame and press capture', 
                inst_back: '⚠️ Place ID back in frame and press capture',
                inst_selfie: '⚠️ Center your face in the circle and look at camera',
                ocr_scan: 'Processing image...',
                loading_ai: 'Loading secure camera...',
                face_good: 'Face is perfect, hold still',
                face_error: 'Make sure your face is in the circle and eyes open',
                camera_retry: 'Retry'
            }
        };

        const countries = [ "Algeria", "Bahrain", "Egypt", "Iraq", "Jordan", "Kuwait", "Lebanon", "Libya", "Morocco", "Oman", "Palestine", "Qatar", "Saudi Arabia", "Sudan", "Syria", "Tunisia", "United Arab Emirates", "Yemen" ];
        
        let currentLang = 'ar';
        let stream = null;
        let step = 0; 
        let isProcessing = false;
        let frontGrayPixels = null;
        let analysisInterval = null;
        let selfieProgress = 0;
        let faceDetectionActive = false;
        let modelsLoaded = false;
        let cameraRetryCount = 0;
        const MAX_RETRIES = 2;
        
        const els = {
            ui: document.getElementById('cam-ui'), video: document.getElementById('video'), 
            guideRect: document.getElementById('guide-rect'), guideCircle: document.getElementById('guide-circle'),
            msg: document.getElementById('cam-msg'), errMsg: document.getElementById('err-msg'), successMsg: document.getElementById('success-msg'),
            procLoader: document.getElementById('proc-loader'), procText: document.getElementById('proc-text'), flip: document.getElementById('flip-anim'),
            btnCapture: document.getElementById('manual-capture-btn'), selfieRing: document.getElementById('selfie-ring')
        };

        async function loadFaceAPIModels() {
            const loadingDiv = document.getElementById('loadingOverlay');
            loadingDiv.style.display = 'flex';
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights');
                await faceapi.nets.faceLandmark68Net.loadFromUri('https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights');
                modelsLoaded = true;
                const statusDiv = document.getElementById('main-status');
                if(statusDiv) {
                    statusDiv.innerHTML = currentLang === 'ar' ? '✅ الكاميرا جاهزة، اضغط للتوثيق' : '✅ Camera ready, tap to verify';
                    statusDiv.style.color = 'var(--success)';
                }
            } catch(err) {
                console.error("Error loading models", err);
                modelsLoaded = false;
                const statusDiv = document.getElementById('main-status');
                if(statusDiv) {
                    statusDiv.innerHTML = currentLang === 'ar' ? '⚠️ الكاميرا جاهزة بدون ذكاء اصطناعي (ضعيف)' : '⚠️ Camera ready without AI (slow)';
                    statusDiv.style.color = '#eab308';
                }
            } finally {
                loadingDiv.style.display = 'none';
            }
        }
        
        function setLang(lang) {
            currentLang = lang; const t = translations[lang]; document.documentElement.dir = t.dir;
            document.getElementById('btn-ar').classList.toggle('active', lang === 'ar'); document.getElementById('btn-en').classList.toggle('active', lang === 'en');
            document.querySelectorAll('[data-i18n]').forEach(el => {
                if(el.getAttribute('data-i18n') === 'terms_label') el.innerHTML = t['terms_label']; 
                else el.innerText = t[el.getAttribute('data-i18n')];
            });
            document.querySelectorAll('[data-ph]').forEach(el => el.placeholder = t[el.getAttribute('data-ph')]); renderCountryList();
            if(document.getElementById('main-status')) {
                let msg = modelsLoaded ? '✅ الكاميرا جاهزة، اضغط للتوثيق' : '⚠️ الكاميرا جاهزة بدون ذكاء اصطناعي';
                document.getElementById('main-status').innerHTML = currentLang === 'ar' ? msg : (modelsLoaded ? '✅ Camera ready, tap to verify' : '⚠️ Camera ready without AI');
            }
        }

        function renderCountryList() {
            const list = document.getElementById('country-options-list'); if(!list) return; list.innerHTML = '';
            countries.sort().forEach(c => {
                const div = document.createElement('div'); div.className = 'option'; div.textContent = c;
                div.onclick = () => { document.getElementById('select-label').textContent = c; document.getElementById('region-input').value = c; document.querySelector('.custom-select').classList.remove('open'); };
                list.appendChild(div);
            });
        }
        
        if(document.querySelector('.custom-select__trigger')) {
            document.querySelector('.custom-select__trigger').addEventListener('click', () => { document.querySelector('.custom-select').classList.toggle('open'); });
            document.getElementById('country-search').addEventListener('input', function(e) { const term = e.target.value.toLowerCase(); document.querySelectorAll('.option').forEach(opt => { opt.style.display = opt.textContent.toLowerCase().includes(term) ? 'block' : 'none'; }); });
        }
        window.addEventListener('click', function(e) { if (document.querySelector('.custom-select-wrapper') && !document.querySelector('.custom-select-wrapper').contains(e.target)) { if(document.querySelector('.custom-select')) document.querySelector('.custom-select').classList.remove('open'); } });
        function openPolicy() { document.getElementById('policyText').innerText = legalTexts[currentLang]; document.getElementById('policyModal').style.display = "block"; }
        function closePolicy() { document.getElementById('policyModal').style.display = "none"; }

        function updateRingProgress(percent) {
            const circumference = 2 * Math.PI * 48;
            els.selfieRing.style.strokeDasharray = `${circumference} ${circumference}`;
            const offset = circumference - (percent / 100) * circumference;
            els.selfieRing.style.strokeDashoffset = offset;
        }

        async function openCamera(facingMode) {
            document.querySelectorAll('.retry-btn').forEach(el => el.remove());

            try {
                if (stream) {
                    stream.getTracks().forEach(t => t.stop());
                    stream = null;
                }

                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    // الكود الحديث
                } else {
                    navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
                    if (!navigator.getUserMedia) {
                        throw new Error('المتصفح لا يدعم الكاميرا، تأكد من استخدام HTTPS أو تحديث التطبيق.');
                    }
                }

                let constraints = {
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }
                };

                let streamObj;
                try {
                    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                        streamObj = await navigator.mediaDevices.getUserMedia(constraints);
                    } else {
                        streamObj = await new Promise((resolve, reject) => {
                            navigator.getUserMedia(constraints, resolve, reject);
                        });
                    }
                    stream = streamObj;
                } catch (err1) {
                    console.warn('فشل بدقة محددة، نحاول بدون قيود:', err1);
                    constraints = { video: true };
                    
                    if (err1.name === 'NotAllowedError' || err1.name === 'PermissionDeniedError') {
                        throw err1;
                    }

                    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                        streamObj = await navigator.mediaDevices.getUserMedia(constraints);
                    } else {
                        streamObj = await new Promise((resolve, reject) => {
                            navigator.getUserMedia(constraints, resolve, reject);
                        });
                    }
                    stream = streamObj;
                }

                els.video.srcObject = stream;
                await new Promise((resolve, reject) => {
                    els.video.onloadedmetadata = () => {
                        els.video.play().then(resolve).catch(reject);
                    };
                    els.video.onerror = reject;
                    setTimeout(() => reject(new Error('انتهت مهلة الكاميرا')), 8000);
                });

                if (els.video.videoWidth === 0 || els.video.videoHeight === 0) {
                    throw new Error('لم تستقبل الكاميرا إشارة');
                }

                cameraRetryCount = 0;
                setupUIForStep();
                return true;

            } catch (err) {
                console.error('خطأ الكاميرا:', err);
                let userMsg = 'تعذر فتح الكاميرا: ';
                
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    userMsg = currentLang === 'ar' ? 
                    'عذراً، يجب عليك إعطاء التطبيق أو المتصفح صلاحية استخدام الكاميرا من إعدادات الهاتف لتتمكن من التوثيق.' : 
                    'Permission Denied. Please allow camera access in your app/browser settings to proceed.';
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    userMsg += 'لا توجد كاميرا متصلة بهذا الجهاز.';
                } else if (err.message && err.message.includes('HTTPS')) {
                    userMsg = 'يجب فتح الموقع عبر HTTPS لاستخدام الكاميرا.';
                } else {
                    userMsg += err.message || 'خطأ غير معروف';
                }

                showCamError(userMsg);

                if (facingMode === 'environment' && cameraRetryCount < MAX_RETRIES && err.name !== 'NotAllowedError') {
                    cameraRetryCount++;
                    els.msg.innerText = currentLang === 'ar' ? 'جاري تجربة الكاميرا الأمامية...' : 'Trying front camera...';
                    setTimeout(() => openCamera('user'), 1500);
                } else {
                    showRetryButton();
                }
                return false;
            }
        }

        function showRetryButton() {
            const btn = document.createElement('button');
            btn.className = 'retry-btn';
            btn.innerText = currentLang === 'ar' ? '🔁 إعادة المحاولة لطلب الإذن' : '🔁 Retry Permission Request';
            btn.onclick = () => {
                btn.remove();
                cameraRetryCount = 0;
                startKYC();
            };
            document.getElementById('cam-ui').appendChild(btn);
        }

        function setupUIForStep() {
            const t = translations[currentLang];
            els.errMsg.style.display = 'none'; els.successMsg.style.display = 'none';
            els.video.classList.toggle('mirrored', step === 2);
            
            if (step === 0 || step === 1) { 
                if(analysisInterval) cancelAnimationFrame(analysisInterval);
                els.msg.innerText = step === 0 ? t.inst_front : t.inst_back; 
                els.msg.style.color = "#eab308";
                els.guideRect.style.display = "block";
                els.guideCircle.style.display = "none";
                els.btnCapture.style.display = "flex"; 
                els.btnCapture.classList.remove('disabled');
            } 
            else if (step === 2) { 
                els.msg.innerText = t.inst_selfie; 
                els.msg.style.color = "#fff";
                els.guideRect.style.display = "none";
                els.guideCircle.style.display = "block";
                els.btnCapture.style.display = "none";
                startAutoSelfie();
            }
        }
        
        function checkCardObstruction(canvasFrame) {
            const ctx = canvasFrame.getContext('2d');
            const imgData = ctx.getImageData(0, 0, canvasFrame.width, canvasFrame.height);
            const data = imgData.data;
            let darkPixels = 0;
            let brightPixels = 0;
            let totalPixels = canvasFrame.width * canvasFrame.height;
            let grayValues = [];
            for (let i = 0; i < data.length; i += 4) {
                let gray = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
                grayValues.push(gray);
                if (gray < 50) darkPixels++;
                if (gray > 220) brightPixels++;
            }
            let mean = grayValues.reduce((a,b) => a+b, 0) / totalPixels;
            let variance = grayValues.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / totalPixels;
            let stdDev = Math.sqrt(variance);
            
            if ((darkPixels / totalPixels) > 0.10) {
                return { obstructed: true, reason: currentLang === 'ar' ? 'يوجد ظل أو إصبع يغطي البطاقة' : 'Shadow or finger covering the card' };
            }
            if ((brightPixels / totalPixels) > 0.05) {
                return { obstructed: true, reason: currentLang === 'ar' ? 'وميض قوي على البطاقة، حاول تعديل الإضاءة' : 'Strong glare on card, adjust lighting' };
            }
            if (stdDev < 15) {
                return { obstructed: true, reason: currentLang === 'ar' ? 'الصورة غير واضحة أو مغطاة بالكامل' : 'Image blurry or fully covered' };
            }
            return { obstructed: false, reason: '' };
        }

        function getGrayThumb() {
            const c = document.createElement('canvas'); c.width = 64; c.height = 64;
            const ctx = c.getContext('2d'); ctx.drawImage(els.video, 0, 0, 64, 64);
            const imgData = ctx.getImageData(0,0,64,64).data;
            let gray = [];
            for(let i=0; i<imgData.length; i+=4) gray.push(0.299*imgData[i] + 0.587*imgData[i+1] + 0.114*imgData[i+2]);
            return gray;
        }
        
        function compareThumbs(gray1, gray2) {
            let diff = 0; for(let i=0; i<gray1.length; i++) diff += Math.abs(gray1[i] - gray2[i]);
            return diff / gray1.length; 
        }
        
        async function manualCaptureCard() {
            if(isProcessing) return;
            isProcessing = true;
            
            els.btnCapture.classList.add('disabled');
            els.procText.innerText = translations[currentLang].ocr_scan;
            els.procLoader.style.display = 'flex';
            els.msg.style.display = 'none';
            
            if(modelsLoaded && step !== 2) {
                try {
                    const detections = await faceapi.detectAllFaces(els.video, new faceapi.TinyFaceDetectorOptions());
                    if(detections.length > 0) {
                        const box = detections[0].box;
                        const faceArea = (box.width * box.height) / (els.video.videoWidth * els.video.videoHeight);
                        if(faceArea > 0.15) {
                            showCamError(currentLang === 'ar' ? "يرجى توجيه الكاميرا للبطاقة وليس وجهك" : "Please point camera at ID card, not your face");
                            return;
                        }
                    }
                } catch(e) {}
            }
            
            const tempCanvas = document.createElement('canvas');
            const guideRect = document.getElementById('guide-rect');
            const rect = guideRect.getBoundingClientRect();
            const videoRect = els.video.getBoundingClientRect();
            const scaleX = els.video.videoWidth / videoRect.width;
            const scaleY = els.video.videoHeight / videoRect.height;
            const cropX = (rect.left - videoRect.left) * scaleX;
            const cropY = (rect.top - videoRect.top) * scaleY;
            const cropW = rect.width * scaleX;
            const cropH = rect.height * scaleY;
            tempCanvas.width = cropW;
            tempCanvas.height = cropH;
            const tempCtx = tempCanvas.getContext('2d');
            tempCtx.drawImage(els.video, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);
            
            const obstructionCheck = checkCardObstruction(tempCanvas);
            if (obstructionCheck.obstructed) {
                showCamError(obstructionCheck.reason);
                return;
            }
            
            const currentGrayThumb = getGrayThumb();
            if (step === 1 && frontGrayPixels != null) {
                const diffScore = compareThumbs(frontGrayPixels, currentGrayThumb);
                if (diffScore < 20) {
                    showCamError(currentLang === 'ar' ? "لم تقم بقلب البطاقة! الصورة مطابقة للأمام." : "Please flip the card! Image matches the front.");
                    return;
                }
            }
            
            els.procLoader.style.display = 'none';
            els.msg.style.display = 'block';
            
            if (step === 0) frontGrayPixels = currentGrayThumb;
            
            const finalImg = getBase64FromVideo(1.0);
            
            if (step === 0) {
                document.getElementById('val-front').value = finalImg;
                step = 1;
                showFlipAnimation();
            } else if (step === 1) {
                document.getElementById('val-back').value = finalImg;
                step = 2;
                els.msg.innerText = currentLang === 'ar' ? "جاري التحويل لكاميرا السيلفي..." : "Switching to Selfie...";
                await openCamera('user');
            }
        }
        
        function showFlipAnimation() {
            els.flip.style.display = 'flex'; if(stream) stream.getTracks().forEach(t => t.stop()); 
            els.btnCapture.style.display = "none";
            setTimeout(async () => { els.flip.style.display = 'none'; await openCamera('environment'); setTimeout(() => { isProcessing = false; els.btnCapture.classList.remove('disabled'); }, 500); }, 3000); 
        }
        
        function showCamError(errorText) {
            els.procLoader.style.display = 'none';
            els.msg.style.display = 'block';
            els.errMsg.innerText = errorText;
            els.errMsg.style.display = 'block';
            setTimeout(() => { 
                els.errMsg.style.display = 'none'; 
                isProcessing = false; 
                els.btnCapture.classList.remove('disabled');
            }, 3500);
        }
        
        function getBase64FromVideo(scale = 1.0) {
            const canvas = document.createElement('canvas');
            canvas.width = els.video.videoWidth * scale; canvas.height = els.video.videoHeight * scale;
            const ctx = canvas.getContext('2d');
            if(step === 2) {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(els.video, 0, 0, canvas.width, canvas.height);
            return canvas.toDataURL('image/jpeg', 0.85);
        }
        
        function eyeAspectRatio(eyePoints) {
            if(eyePoints.length !== 6) return 0.2;
            const p1 = eyePoints[0], p2 = eyePoints[1], p3 = eyePoints[2], p4 = eyePoints[3], p5 = eyePoints[4], p6 = eyePoints[5];
            const vertical1 = Math.hypot(p2.y - p6.y, p2.x - p6.x);
            const vertical2 = Math.hypot(p3.y - p5.y, p3.x - p5.x);
            const horizontal = Math.hypot(p1.y - p4.y, p1.x - p4.x);
            if(horizontal === 0) return 0.2;
            return (vertical1 + vertical2) / (2.0 * horizontal);
        }
        
        async function startAutoSelfie() {
            selfieProgress = 0;
            updateRingProgress(0);
            faceDetectionActive = true;
            
            async function analyzeSelfie() {
                if (!faceDetectionActive || !els.video || els.video.paused || els.video.ended) {
                    requestAnimationFrame(analyzeSelfie);
                    return;
                }
                
                if (!modelsLoaded) {
                    selfieProgress = Math.min(100, selfieProgress + 2);
                    updateRingProgress(selfieProgress);
                    if (selfieProgress >= 100) {
                        faceDetectionActive = false;
                        await captureSelfieNow();
                        return;
                    }
                    requestAnimationFrame(analyzeSelfie);
                    return;
                }

                try {
                    const detections = await faceapi.detectAllFaces(els.video, new faceapi.TinyFaceDetectorOptions())
                        .withFaceLandmarks();
                    
                    if (detections.length === 0) {
                        if(selfieProgress > 0) selfieProgress = 0;
                        updateRingProgress(0);
                        els.errMsg.innerText = translations[currentLang].face_error;
                        els.errMsg.style.display = 'block';
                        els.msg.innerText = translations[currentLang].inst_selfie;
                        requestAnimationFrame(analyzeSelfie);
                        return;
                    }
                    
                    const face = detections[0];
                    const box = face.detection.box;
                    const videoW = els.video.videoWidth;
                    const videoH = els.video.videoHeight;
                    if(videoW === 0) { requestAnimationFrame(analyzeSelfie); return; }
                    
                    const faceWidth = box.width;
                    const faceX = box.x + faceWidth/2;
                    const faceY = box.y + box.height/2;
                    
                    let condition = true;
                    let errorMsg = "";
                    
                    if (faceWidth < videoW * 0.22) { condition = false; errorMsg = currentLang === 'ar' ? "اقترب أكثر" : "Move closer"; }
                    else if (faceWidth > videoW * 0.6) { condition = false; errorMsg = currentLang === 'ar' ? "ابتعد قليلاً" : "Move away"; }
                    else if (Math.abs(faceX - videoW/2) > videoW * 0.15) { condition = false; errorMsg = currentLang === 'ar' ? "وسّط وجهك أفقياً" : "Center face horizontally"; }
                    else if (Math.abs(faceY - videoH/2) > videoH * 0.15) { condition = false; errorMsg = currentLang === 'ar' ? "وسّط وجهك عامودياً" : "Center face vertically"; }
                    else {
                        const landmarks = face.landmarks.positions;
                        if(landmarks && landmarks.length >= 48) {
                            const leftEye = landmarks.slice(36, 42);
                            const rightEye = landmarks.slice(42, 48);
                            const earLeft = eyeAspectRatio(leftEye);
                            const earRight = eyeAspectRatio(rightEye);
                            const ear = (earLeft + earRight) / 2;
                            if (ear < 0.18) { condition = false; errorMsg = currentLang === 'ar' ? "افتح عينيك" : "Open your eyes"; }
                        }
                    }
                    
                    if (condition) {
                        els.errMsg.style.display = 'none';
                        els.msg.innerText = translations[currentLang].face_good;
                        els.msg.style.color = "#10b981";
                        selfieProgress = Math.min(100, selfieProgress + 3.5);
                        updateRingProgress(selfieProgress);
                        if (selfieProgress >= 100) {
                            faceDetectionActive = false;
                            await captureSelfieNow();
                            return;
                        }
                    } else {
                        if(selfieProgress > 0) selfieProgress = 0;
                        updateRingProgress(0);
                        els.errMsg.innerText = errorMsg || translations[currentLang].face_error;
                        els.errMsg.style.display = 'block';
                        els.msg.innerText = translations[currentLang].inst_selfie;
                        els.msg.style.color = "#fff";
                    }
                } catch(err) {
                    console.warn(err);
                }
                requestAnimationFrame(analyzeSelfie);
            }
            
            analyzeSelfie();
        }
        
        async function captureSelfieNow() {
            isProcessing = true;
            els.successMsg.innerText = currentLang === 'ar' ? "تم التحقق بنجاح!" : "Verified!";
            els.successMsg.style.display = 'block';
            els.msg.style.display = 'none';
            els.errMsg.style.display = 'none';
            
            const finalImg = getBase64FromVideo(0.9);
            document.getElementById('val-selfie').value = finalImg;
            
            setTimeout(() => {
                closeCamera();
                const statusDiv = document.getElementById('main-status');
                if(statusDiv) {
                    statusDiv.innerHTML = currentLang === 'ar' ? "✅ اكتمل التوثيق الذكي بنجاح" : "✅ KYC Completed";
                    statusDiv.style.color = "#10b981";
                }
            }, 1000);
        }
        
        async function startKYC() {
            document.querySelectorAll('.retry-btn').forEach(el => el.remove());

            if(!modelsLoaded) {
                const statusDiv = document.getElementById('main-status');
                if(statusDiv) {
                    statusDiv.innerHTML = currentLang === 'ar' ? '⏳ جاري فتح الكاميرا (بدون ذكاء اصطناعي)' : '⏳ Opening camera (no AI)';
                    statusDiv.style.color = '#d4af37';
                }
            }
            step = 0; 
            isProcessing = false; 
            frontGrayPixels = null; 
            selfieProgress = 0;
            els.ui.style.display = 'flex';
            els.msg.innerText = currentLang === 'ar' ? "جاري تهيئة الكاميرا..." : "Initializing Camera...";
            els.errMsg.style.display = 'none'; 
            els.successMsg.style.display = 'none'; 
            els.btnCapture.style.display = 'none';
            await openCamera('environment');
        }
        
        function closeCamera() { 
            els.ui.style.display = 'none'; 
            if(stream) stream.getTracks().forEach(t => t.stop()); 
            if(analysisInterval) cancelAnimationFrame(analysisInterval);
            faceDetectionActive = false;
            stream = null; 
            isProcessing = false; 
            document.querySelectorAll('.retry-btn').forEach(el => el.remove());
        }
        
        function validateForm() { 
            if(!document.getElementById('val-selfie').value) { 
                showToast(currentLang === 'ar' ? 'أكمل التوثيق الذكي أولاً' : 'Complete Verification first');
                return false; 
            } 
            if(!document.getElementById('terms_check').checked) { 
                showToast(currentLang === 'ar' ? 'يجب الموافقة على الشروط' : 'Accept Terms');
                return false; 
            } 
            return true; 
        }
        
        function showToast(msg) {
            let toast = document.createElement('div');
            toast.innerText = msg;
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.left = '50%';
            toast.style.transform = 'translateX(-50%)';
            toast.style.backgroundColor = '#333';
            toast.style.color = '#fff';
            toast.style.padding = '10px 20px';
            toast.style.borderRadius = '8px';
            toast.style.zIndex = '99999';
            toast.style.fontSize = '14px';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        window.addEventListener('DOMContentLoaded', () => { 
            setLang('ar'); 
            loadFaceAPIModels();
        });
    </script>
</body>
</html>