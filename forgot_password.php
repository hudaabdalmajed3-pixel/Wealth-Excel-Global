<?php
// forgot_password.php - Fixed Email Sending Logic
require 'config.php';

$msg_type = ""; // لتحديد نوع الرسالة (نجاح أو خطأ) لترجمتها
$msg_data = ""; // البيانات الإضافية مثل رابط الاستعادة

// دالة إرسال بريد استعادة كلمة المرور
function sendPasswordResetEmail($email, $resetLink) {
    $domain = $_SERVER['HTTP_HOST'];
    $subject = "Password Reset - Wealth Xcel";
    
    // إعدادات بريد قوية لتجنب حظر الاستضافة (Spam)
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Wealth Xcel <noreply@$domain>\r\n";
    $headers .= "Reply-To: support@$domain\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $msg = "
    <div style='background:#0f172a; padding:30px; text-align:center; border-radius:10px; border:1px solid #d4af37; color:#fff; font-family:Arial, sans-serif;'>
        <h2 style='color:#d4af37; margin-top:0;'>Wealth Xcel</h2>
        <p style='color:#ccc;'>You requested to reset your password. Click the button below to set a new password:</p>
        <a href='$resetLink' style='display:inline-block; margin-top:20px; padding:12px 25px; background:#d4af37; color:#000; text-decoration:none; font-weight:bold; border-radius:8px;'>Reset Password</a>
        <p style='font-size:11px; color:#888; margin-top:30px;'>If you did not request this, please ignore this email.</p>
    </div>";
    
    return mail($email, $subject, $msg, $headers);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $token = bin2hex(random_bytes(50));
        $sql = "INSERT INTO password_resets (email, token) VALUES (?, ?)";
        $pdo->prepare($sql)->execute([$email, $token]);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        $resetLink = $protocol . "://" . $domain . $path . "/reset_password.php?token=" . $token;

        $is_localhost = ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1');

        if ($is_localhost) {
            $msg_type = "localhost_test";
            $msg_data = $resetLink;
        } else {
            // 🔴 إرسال الإيميل الحقيقي
            $mail_sent = sendPasswordResetEmail($email, $resetLink);
            if ($mail_sent) {
                $msg_type = "email_sent";
            } else {
                $msg_type = "email_error"; // في حال رفضت الاستضافة إرسال البريد
            }
        }
    } else {
        $msg_type = "user_not_found";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wealth Xcel | Forgot Password</title>
    <style>
        :root { --gold: #d4af37; --dark: #020617; --dark-soft: #0f172a; --white: #ffffff; --muted: #9ca3af; --radius: 14px; --shadow: 0 18px 45px rgba(0,0,0,0.6); }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: radial-gradient(circle at top, #111827 0, #020617 55%); color: var(--white); min-height: 100vh; margin: 0; display: flex; flex-direction: column; transition: direction 0.3s ease; }

        /* الهيدر المطور لمنع التداخل */
        header { 
            width: 100%; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; 
            position: absolute; top: 0; left: 0; z-index: 1000; 
        }
        .logo { display: flex; align-items: center; gap: 8px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; text-decoration: none; color: white; }
        .logo-mark { width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid var(--gold); display: flex; align-items: center; justify-content: center; color: var(--gold); background: rgba(212, 175, 55, 0.1); font-size: 14px; }
        
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .btn-lang { background: rgba(255,255,255,0.05); border: 1px solid #555; color: #ccc; padding: 6px 12px; cursor: pointer; border-radius: 6px; font-size: 11px; font-weight: bold; transition: 0.3s; }
        .btn-lang:hover, .btn-lang.active { border-color: var(--gold); color: var(--gold); background: rgba(212, 175, 55, 0.1); }

        .main-container { flex: 1; display: flex; justify-content: center; align-items: center; padding: 100px 20px 40px; }
        .auth-card { background: radial-gradient(circle at top, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.95)); border-radius: 20px; padding: 40px; border: 1px solid rgba(212, 175, 55, 0.2); box-shadow: var(--shadow); width: 100%; max-width: 450px; text-align: center; }
        .auth-card h2 { margin-top: 0; color: var(--white); margin-bottom: 10px; font-size: 24px; }
        .auth-card p { color: var(--muted); font-size: 13px; margin-bottom: 30px; line-height: 1.5; }

        .form-group { margin-bottom: 20px; text-align: inherit; }
        input { width: 100%; background: #0f172a; border: 1px solid rgba(148,163,184,0.3); border-radius: 10px; padding: 12px 15px; color: var(--white); font-size: 14px; outline: none; transition: 0.3s; text-align: inherit; }
        input:focus { border-color: var(--gold); }

        .btn-primary { width: 100%; background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; font-weight: 700; padding: 14px; border: none; border-radius: 10px; cursor: pointer; font-size: 16px; margin-top: 10px; transition: 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(212, 175, 55, 0.3); }

        .back-link { margin-top: 25px; font-size: 13px; color: var(--muted); display: block; text-decoration: none; }
        .back-link:hover { color: var(--gold); }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; border: 1px solid; text-align: inherit; line-height: 1.6; }
        .success { background: rgba(16, 185, 129, 0.1); border-color: #10b981; color: #10b981; }
        .warning { background: rgba(239, 68, 68, 0.1); border-color: #ef4444; color: #ef4444; }
    </style>
</head>
<body>
    
    <header>
        <a href="index.php" class="logo">
            <div class="logo-mark">WX</div>
            <span>Wealth Xcel</span>
        </a>
        <div class="header-actions">
            <button class="btn-lang" id="btn-en" onclick="setLang('en')">EN</button>
            <button class="btn-lang" id="btn-ar" onclick="setLang('ar')">AR</button>
        </div>
    </header>

    <div class="main-container">
        <div class="auth-card">
            <h2 id="txt-title">استعادة كلمة المرور</h2>
            <p id="txt-desc">أدخل بريدك الإلكتروني المسجل، وسنرسل لك رابطاً لتعيين كلمة مرور جديدة.</p>

            <div id="alert-box">
                <?php if($msg_type == "localhost_test"): ?>
                    <div class='alert success' 
                         data-ar='✅ (وضع الاختبار)<br>تم إنشاء الرابط بنجاح. اضغط أدناه:<br><a href="<?=$msg_data?>" style="color:white; font-weight:bold;">رابط الاستعادة</a>' 
                         data-en='✅ (Test Mode)<br>Link created successfully. Click below:<br><a href="<?=$msg_data?>" style="color:white; font-weight:bold;">Reset Link</a>'>
                    </div>
                <?php elseif($msg_type == "email_sent"): ?>
                    <div class='alert success' 
                         data-ar='✅ تم إرسال رابط الاستعادة إلى بريدك الإلكتروني.' 
                         data-en='✅ Reset link has been sent to your email.'>
                    </div>
                <?php elseif($msg_type == "user_not_found"): ?>
                    <div class='alert warning' 
                         data-ar='⚠️ هذا البريد الإلكتروني غير مسجل لدينا!' 
                         data-en='⚠️ This email is not registered!'>
                    </div>
                <?php elseif($msg_type == "email_error"): ?>
                    <div class='alert warning' 
                         data-ar='⚠️ فشل إرسال البريد الإلكتروني. يرجى المحاولة لاحقاً أو التواصل مع الدعم.' 
                         data-en='⚠️ Failed to send email. Please try again later or contact support.'>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <input type="email" name="email" class="inp-email" placeholder="example@gmail.com" required>
                </div>
                <button type="submit" class="btn-primary" id="txt-btn">إرسال الرابط</button>
            </form>

            <a href="login.php" class="back-link" id="txt-back">⟵ عودة لتسجيل الدخول</a>
        </div>
    </div>

    <script>
        const translations = {
            ar: { dir: 'rtl', title: 'استعادة كلمة المرور', desc: 'أدخل بريدك الإلكتروني المسجل، وسنرسل لك رابطاً لتعيين كلمة مرور جديدة.', btn: 'إرسال الرابط', back: '⟵ عودة لتسجيل الدخول' },
            en: { dir: 'ltr', title: 'Reset Password', desc: 'Enter your registered email, and we will send you a link to set a new password.', btn: 'Send Reset Link', back: '⟶ Back to Login' }
        };

        function translateNumbers(str, lang) {
            const arNums = ["٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩"];
            if (lang === 'ar') return str.toString().replace(/[0-9]/g, d => arNums[d]);
            return str.toString().replace(/[٠-٩]/g, d => "0123456789"["٠١٢٣٤٥٦٧٨٩".indexOf(d)]);
        }

        function setLang(lang) {
            const data = translations[lang];
            document.body.dir = data.dir;
            document.documentElement.lang = lang;

            document.getElementById('btn-en').classList.toggle('active', lang === 'en');
            document.getElementById('btn-ar').classList.toggle('active', lang === 'ar');

            document.getElementById('txt-title').innerText = data.title;
            document.getElementById('txt-desc').innerText = data.desc;
            document.getElementById('txt-btn').innerText = data.btn;
            document.getElementById('txt-back').innerText = data.back;

            // تحديث التنبيهات
            document.querySelectorAll('.alert').forEach(al => {
                if(al.getAttribute('data-' + lang)) {
                    al.innerHTML = translateNumbers(al.getAttribute('data-' + lang), lang);
                }
            });

            localStorage.setItem('pref_lang', lang);
        }

        window.onload = () => {
            const saved = localStorage.getItem('pref_lang') || 'ar';
            setLang(saved);
        };
    </script>
</body>
</html>