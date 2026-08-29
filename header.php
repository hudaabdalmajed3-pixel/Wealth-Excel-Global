<?php
// header.php - النسخة النهائية (بدون تذييل، التذييل منفصل الآن)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php'; 

// 1. منطق الخروج
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// 2. منطق معالجة وتبديل اللغة
$queryParams = $_GET;
unset($queryParams['lang']);
unset($queryParams['logout']);
$queryString = http_build_query($queryParams);

if (isset($_GET['lang'])) {
    $newLang = $_GET['lang'];
    if (in_array($newLang, ['ar', 'en'])) { 
        $_SESSION['lang'] = $newLang; 
    }
    $redirectUrl = strtok($_SERVER["REQUEST_URI"], '?');
    if (!empty($queryString)) { $redirectUrl .= '?' . $queryString; }
    header("Location: " . $redirectUrl);
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

$prefix = empty($queryString) ? '?' : '?' . $queryString . '&';
$link_en = $prefix . 'lang=en';
$link_ar = $prefix . 'lang=ar';

// 3. قاموس الترجمة
// تم تعديل الاسم هنا ليصبح Wealth Excel Global في الترجمات
$trans = [
    'en' => [
        'home' => 'Home', 'academy' => 'Academy', 'services' => 'Services', 
        'videos' => 'Videos',
        'account' => 'Account', 'admin' => 'Admin Panel', 
        'investor' => 'Investor Dash', 'agent' => 'Agent Dash', 
        'login' => 'Login', 'logout' => 'Logout',
        'privacy' => 'Privacy Policy', 'terms' => 'Terms of Service', 'risk' => 'Risk Warning',
        'copyright' => 'All rights reserved', 'company' => 'Wealth Excel Global' // التعديل هنا
    ],
    'ar' => [
        'home' => 'الرئيسية', 'academy' => 'الأكاديمية', 'services' => 'الخدمات', 
        'videos' => 'فيديو',
        'account' => 'حسابي', 'admin' => 'لوحة الإدارة', 
        'investor' => 'لوحة المستثمر', 'agent' => 'لوحة الوكيل', 
        'login' => 'دخول', 'logout' => 'خروج',
        'privacy' => 'سياسة الخصوصية', 'terms' => 'شروط الخدمة', 'risk' => 'تحذير المخاطر',
        'copyright' => 'جميع الحقوق محفوظة', 'company' => 'ويلث إكسيل جلوبال'
    ]
];
function t($key) { global $trans, $lang; return $trans[$lang][$key] ?? $key; }

// 4. التحقق من رتبة المستخدم
$user_id = $_SESSION['user_id'] ?? null;
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : null;

$dash_link = 'dashboard.php';
if ($role === 'agent') { $dash_link = 'agent_full.php'; }
if ($role === 'admin') { $dash_link = 'admin.php'; }

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- تم تعديل عنوان الصفحة الأساسي ليصبح Wealth Excel Global -->
    <title><?php echo isset($page_title) ? $page_title : 'Wealth Excel Global'; ?></title>
    
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WX Platform">
    <!-- إضافة ?v=2.0 لكسر كاش أيقونة أبل -->
    <link rel="apple-touch-icon" href="/assets/icon-192x192.png?v=2.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Noto+Sans+Arabic:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --gold-light: #fff3a0;
            --gold-main: #d4af37;
            --gold-deep: #8a6d3b;
            --border: rgba(148, 163, 184, 0.2);
            --text-muted: #9ca3af;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #020617; 
            color: #fff; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        html[lang="ar"] body { font-family: 'Noto Sans Arabic', sans-serif; }

        /* header styles */
        header {
            position: sticky; top: 0; z-index: 1000;
            background: rgba(2, 6, 23, 0.96); backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--border); padding: 10px 0;
        }
        .container { max-width: 1300px; margin: 0 auto; padding: 0 20px; }
        .nav-container { display: flex; align-items: center; justify-content: space-between; }

        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-wrap { position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        .glow-ring { position: absolute; width: 100%; height: 100%; border: 1.2px solid var(--gold-main); border-radius: 50%; box-shadow: 0 0 10px rgba(212, 175, 55, 0.4), inset 0 0 5px rgba(212, 175, 55, 0.2); animation: logo-pulse 4s infinite ease-in-out; }
        .logo-svg { width: 26px; height: 26px; z-index: 2; overflow: visible; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5)); }
        @keyframes logo-pulse { 0%, 100% { transform: scale(1); opacity: 0.8; } 50% { transform: scale(1.05); opacity: 1; box-shadow: 0 0 15px rgba(212, 175, 55, 0.6); } }
        .ring-flare { position: absolute; width: 3px; height: 3px; background: #fff; border-radius: 50%; box-shadow: 0 0 10px 2px #fff, 0 0 18px 4px var(--gold-main); animation: rotate-flare 8s infinite linear; }
        @keyframes rotate-flare { from { transform: rotate(0deg) translateX(19px) rotate(0deg); } to { transform: rotate(360deg) translateX(19px) rotate(-360deg); } }

        .nav-links { list-style: none; display: flex; gap: 20px; margin: 0; padding: 0; }
        .nav-links a { text-decoration: none; color: var(--text-muted); font-weight: 500; transition: 0.3s; font-size: 13px; }
        .nav-links a:hover, .nav-links a.active { color: var(--gold-main); }
        
        .nav-actions { display: flex; align-items: center; gap: 15px; }

        .dropdown { position: relative; }
        .dropdown-menu { display: none; position: absolute; top: 130%; right: 0; background: #0f172a; border: 1px solid var(--border); min-width: 180px; border-radius: 12px; padding: 10px; z-index: 2000; box-shadow: 0 20px 50px rgba(0,0,0,0.7); }
        body[dir="rtl"] .dropdown-menu { right: auto; left: 0; }
        .dropdown:hover .dropdown-menu { display: block; }
        .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px; color: var(--text-muted); text-decoration: none; font-size: 13px; border-radius: 8px; transition: 0.2s; }
        .dropdown-menu a:hover { background: rgba(255,255,255,0.05); color: #fff; }

        .lang-group { display: flex; align-items: center; gap: 8px; border-left: 1px solid var(--border); padding-left: 15px; }
        body[dir="rtl"] .lang-group { border-left: none; border-right: 1px solid var(--border); padding-right: 15px; }
        .lang-link { text-decoration: none; color: var(--text-muted); font-size: 11px; font-weight: bold; }
        .lang-link.active { color: var(--gold-main); }

        .mobile-toggle { display: none; color: #fff; font-size: 24px; cursor: pointer; }

        @media (max-width: 900px) {
            .mobile-toggle { display: block; }
            .nav-links { 
                display: none; position: absolute; top: 100%; left: 0; width: 100%; 
                background: rgba(2, 6, 23, 0.98); flex-direction: column; 
                padding: 20px; border-bottom: 1px solid var(--border); text-align: center; gap: 15px; 
            }
            .nav-links.active { display: flex !important; }
            .logo-text { display: none; }
        }
        
        .btn { border-radius: 50px; padding: 7px 16px; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.3s; }
        .btn-primary { background: var(--gold-main); color: #000; }
        .btn-outline { border: 1px solid var(--border); color: var(--text-muted); }
        .btn-outline:hover { border-color: var(--gold-main); color: #fff; }

        /* محتوى الصفحة سيمتد ليدفع التذييل للأسفل */
        .page-content {
            flex: 1;
        }
    </style>

    <!-- إعدادات التثبيت الإجباري PWA -->
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
              console.log('ServiceWorker registration successful');
            })
            .catch(err => {
              console.log('ServiceWorker registration failed: ', err);
            });
        });
      }
    </script>
    <!-- تم إضافة ?v=2.0 لتخطي الذاكرة المخبأة للمتصفح -->
    <script src="/pwa-gate.js?v=2.0"></script>

</head>
<body>

<header>
    <div class="container nav-container">
        <a href="index.php" class="logo">
            <div class="logo-wrap">
                <div class="glow-ring"></div>
                <div class="ring-flare"></div>
                <svg class="logo-svg" viewBox="0 0 100 100">
                    <defs>
                        <linearGradient id="goldLuxury" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:#fff3a0;stop-opacity:1" />
                            <stop offset="50%" style="stop-color:#d4af37;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#8a6d3b;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path fill="url(#goldLuxury)" d="M5 30 L25 80 L40 50 L55 80 L75 30 H65 L55 60 L45 35 L35 60 L25 30 Z" />
                    <path fill="url(#goldLuxury)" d="M55 30 L85 80 H95 L65 30 Z" opacity="0.9"/>
                    <path fill="url(#goldLuxury)" d="M85 30 L55 80 H65 L95 30 Z" opacity="0.9"/>
                </svg>
            </div>
            <!-- تم تعديل اسم المنصة هنا ليظهر للمستخدمين -->
            <span class="logo-text" style="color:white; font-weight:900; font-size:17px; letter-spacing:-0.5px;">Wealth Excel Global</span>
        </a>

        <ul class="nav-links" id="nav-links">
            <li><a href="index.php" class="<?php echo ($current_page=='index.php')?'active':''; ?>"><?php echo t('home'); ?></a></li>
            <li><a href="academy.php" class="<?php echo ($current_page=='academy.php')?'active':''; ?>"><?php echo t('academy'); ?></a></li>
            <li><a href="plans.php" class="<?php echo ($current_page=='plans.php')?'active':''; ?>"><?php echo t('services'); ?></a></li>
            <li><a href="videos.php" class="<?php echo ($current_page=='videos.php')?'active':''; ?>"><?php echo t('videos'); ?></a></li>
        </ul>

        <div class="nav-actions">
            <?php if ($user_id): ?>
                <div class="dropdown">
                    <a href="<?php echo $dash_link; ?>" class="btn btn-outline">
                        <i class="fa-regular fa-circle-user"></i> <span><?php echo t('account'); ?></span>
                    </a>
                    <div class="dropdown-menu">
                        <?php if($role === 'admin'): ?>
                            <a href="admin.php"><i class="fa-solid fa-gauge-high"></i> <?php echo t('admin'); ?></a>
                        <?php elseif($role === 'agent'): ?>
                            <a href="agent_full.php"><i class="fa-solid fa-user-tie"></i> <?php echo t('agent'); ?></a>
                        <?php else: ?>
                            <a href="dashboard.php"><i class="fa-solid fa-chart-line" style="color:#3b82f6"></i> <?php echo t('investor'); ?></a>
                        <?php endif; ?>
                        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.05); margin:8px 0;">
                        <a href="index.php?logout=true" style="color:#ef4444;">
                            <i class="fa-solid fa-power-off"></i> <?php echo t('logout'); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary"><span><?php echo t('login'); ?></span></a>
            <?php endif; ?>

            <div class="lang-group">
                <a href="<?php echo $link_en; ?>" class="lang-link <?php echo ($lang=='en')?'active':''; ?>">EN</a>
                <span style="color:var(--border);">|</span>
                <a href="<?php echo $link_ar; ?>" class="lang-link <?php echo ($lang=='ar')?'active':''; ?>">AR</a>
            </div>

            <div class="mobile-toggle" onclick="document.getElementById('nav-links').classList.toggle('active')">
                <i class="fa-solid fa-bars-staggered"></i>
            </div>
        </div>
    </div>
</header>

<!-- بداية محتوى الصفحة -->
<main class="page-content">