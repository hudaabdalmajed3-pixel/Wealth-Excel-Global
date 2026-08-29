<?php
// login.php - Modern design matching register page with PWA Gate
session_start();
require 'config.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = "empty_fields";
    } else {
        // 1. جلب المستخدم بناءً على البريد الإلكتروني فقط لمعرفة الخطأ بدقة
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. التحقق من صحة كلمة المرور
        if ($user && password_verify($password, $user['password'])) {
            
            // 3. التحقق من حالة الحساب بشكل منفصل لإعطاء رسالة دقيقة للمستخدم
            if (isset($user['status']) && $user['status'] !== 'active') {
                $message = "inactive_account";
            } elseif (isset($user['email_verified']) && $user['email_verified'] == 0) {
                $message = "unverified_email";
            } else {
                // 4. الدخول ناجح 100%
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'investor';

                // التوجيه حسب الدور
                if (isset($user['role']) && $user['role'] === 'admin') {
                    header("Location: admin.php");
                } elseif (isset($user['role']) && $user['role'] === 'agent') {
                    header("Location: agent_full.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }
        } else {
            // كلمة المرور خطأ فعلياً أو البريد غير موجود
            $message = "invalid_auth";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>تسجيل الدخول - Wealth Excel</title>
    
    <!-- إعدادات التثبيت الإجباري PWA (لمنع الدخول من المتصفح) -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="/assets/icon-192x192.png?v=2.0">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/service-worker.js');
        });
      }
    </script>
    <script src="/pwa-gate.js?v=2.0"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #d4af37;
            --gold-dark: #b8860b;
            --dark-bg: #0f172a;
            --card-bg: rgba(15, 23, 42, 0.95);
            --border-glow: rgba(212, 175, 55, 0.3);
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: radial-gradient(circle at 30% 10%, #111827 0%, #020617 90%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            transition: direction 0.3s ease;
        }

        /* Language Switcher */
        .lang-switch {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 100;
        }
        html[dir="rtl"] .lang-switch { right: auto; left: 20px; }
        
        .lang-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(212,175,55,0.3);
            color: #aaa;
            padding: 6px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: 0.3s;
        }
        .lang-btn.active {
            border-color: var(--gold);
            color: var(--gold);
            background: rgba(212,175,55,0.1);
        }
        .lang-btn:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Login Card */
        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid var(--border-glow);
            padding: 40px 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            transition: transform 0.2s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
        }

        .logo-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-icon i {
            font-size: 48px;
            color: var(--gold);
            background: rgba(212,175,55,0.1);
            padding: 15px;
            border-radius: 60px;
        }

        h2 {
            text-align: center;
            color: var(--gold);
            font-weight: 500;
            font-size: 28px;
            margin-bottom: 30px;
            letter-spacing: 0.5px;
        }

        .input-group {
            margin-bottom: 24px;
            text-align: inherit;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .input-group input {
            width: 100%;
            background: #0a0f1f;
            border: 1px solid #1e293b;
            border-radius: 14px;
            padding: 14px 16px;
            color: white;
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
            font-family: monospace;
            text-align: inherit;
        }
        .input-group input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 2px rgba(212,175,55,0.2);
        }
        
        html[dir="rtl"] .input-group input { text-align: right; }
        html[dir="ltr"] .input-group input { text-align: left; direction: ltr; }

        .forgot-link {
            text-align: right;
            margin-top: 5px;
        }
        html[dir="rtl"] .forgot-link { text-align: left; }
        
        .forgot-link a {
            font-size: 12px;
            color: var(--text-muted);
            text-decoration: none;
            transition: 0.2s;
        }
        .forgot-link a:hover {
            color: var(--gold);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #000;
            font-weight: 700;
            padding: 14px;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 15px;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212,175,55,0.4);
            filter: brightness(1.05);
        }

        .register-link {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: var(--text-muted);
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 25px;
        }
        .register-link a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
            margin-right: 5px;
        }
        html[dir="ltr"] .register-link a { margin-right: 0; margin-left: 5px; }
        .register-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 25px;
            font-size: 13px;
            text-align: center;
            border: 1px solid;
            line-height: 1.5;
        }
        .alert.error { background: rgba(239,68,68,0.1); border-color: var(--danger); color: #f87171; }
        .alert.warning { background: rgba(245,158,11,0.1); border-color: var(--warning); color: var(--warning); }

        @media (max-width: 480px) {
            .login-card { padding: 30px 20px; }
            h2 { font-size: 24px; }
        }
    </style>
</head>
<body>

<div class="lang-switch">
    <button class="lang-btn" id="btn-en" onclick="setLang('en')">EN</button>
    <button class="lang-btn" id="btn-ar" onclick="setLang('ar')">AR</button>
</div>

<div class="login-card">
    <div class="logo-icon">
        <i class="fa-solid fa-chart-line"></i>
    </div>
    <h2 id="loginTitle">تسجيل الدخول</h2>

    <?php if (!empty($message)): ?>
        <div class="alert <?php echo ($message == 'inactive_account' || $message == 'unverified_email') ? 'warning' : 'error'; ?>" id="alertBox" data-msg="<?php echo $message; ?>">
            <!-- سيتم حقن النص عبر الجافاسكربت -->
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="input-group">
            <label id="emailLabel">البريد الإلكتروني</label>
            <input type="email" name="email" id="emailInput" placeholder="example@mail.com" required autocomplete="email">
        </div>
        <div class="input-group">
            <label id="passLabel">كلمة المرور</label>
            <input type="password" name="password" id="passInput" placeholder="********" required autocomplete="current-password">
            <div class="forgot-link">
                <a href="forgot_password.php" id="forgotLink">هل نسيت كلمة المرور؟</a>
            </div>
        </div>
        <button type="submit" class="btn-login" id="loginBtn">تسجيل الدخول</button>
    </form>

    <div class="register-link">
        <span id="noAccount">ليس لديك حساب؟</span>
        <a href="Register.php" id="createAccount">إنشاء حساب جديد</a>
    </div>
</div>

<script>
    const translations = {
        ar: {
            dir: 'rtl',
            title: 'تسجيل الدخول',
            email: 'البريد الإلكتروني',
            password: 'كلمة المرور',
            forgot: 'هل نسيت كلمة المرور؟',
            login: 'تسجيل الدخول',
            noAcc: 'ليس لديك حساب؟',
            create: 'إنشاء حساب جديد',
            msgs: {
                empty_fields: 'يرجى تعبئة البريد الإلكتروني وكلمة المرور.',
                invalid_auth: 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
                inactive_account: 'عذراً، حسابك غير نشط (موقوف أو قيد المراجعة).',
                unverified_email: 'عذراً، يرجى تفعيل بريدك الإلكتروني أولاً لتتمكن من الدخول.'
            }
        },
        en: {
            dir: 'ltr',
            title: 'Login',
            email: 'Email Address',
            password: 'Password',
            forgot: 'Forgot Password?',
            login: 'Sign In',
            noAcc: "Don't have an account?",
            create: 'Create an Account',
            msgs: {
                empty_fields: 'Please enter your email and password.',
                invalid_auth: 'Invalid email or password.',
                inactive_account: 'Your account is currently inactive (suspended or pending).',
                unverified_email: 'Please verify your email address to log in.'
            }
        }
    };

    let currentLang = localStorage.getItem('pref_lang') || 'ar';

    function setLang(lang) {
        currentLang = lang;
        const t = translations[lang];
        document.documentElement.dir = t.dir;
        document.documentElement.lang = lang;

        document.getElementById('loginTitle').innerText = t.title;
        document.getElementById('emailLabel').innerText = t.email;
        document.getElementById('passLabel').innerText = t.password;
        document.getElementById('forgotLink').innerText = t.forgot;
        document.getElementById('loginBtn').innerText = t.login;
        document.getElementById('noAccount').innerText = t.noAcc;
        document.getElementById('createAccount').innerText = t.create;

        // تحديث رسالة التنبيه إن وجدت
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            const msgType = alertBox.getAttribute('data-msg');
            if (t.msgs[msgType]) {
                alertBox.innerText = t.msgs[msgType];
            }
        }

        // تمييز الزر النشط
        document.getElementById('btn-ar').classList.toggle('active', lang === 'ar');
        document.getElementById('btn-en').classList.toggle('active', lang === 'en');

        localStorage.setItem('pref_lang', lang);
    }

    // التفعيل عند التحميل
    window.addEventListener('DOMContentLoaded', () => {
        setLang(currentLang);
    });
</script>
</body>
</html>