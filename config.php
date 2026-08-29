<?php
// ملف config.php

// 1. إزالة أي مسافات قد تكون أرسلت بالخطأ قبل هذا الملف
// (اختياري ولكنه مفيد جداً لحل مشاكل التوجيه)
if (ob_get_level() == 0) ob_start();

// ========================================================
// 🛡️ إعدادات الأمان المضافة (تأمين الجلسات والكوكيز)
// ========================================================
header_remove("X-Powered-By"); // إخفاء إصدار PHP من استجابات الخادم
ini_set('session.cookie_httponly', 1); // منع الجافاسكربت من قراءة الكوكيز (حماية من XSS)
ini_set('session.cookie_secure', 1);   // إرسال الكوكيز عبر الاتصال المشفر HTTPS فقط
ini_set('session.cookie_samesite', 'Strict'); // منع إرسال الكوكيز لطلبات خارجية (حماية من CSRF)

// 2. بدء الجلسة بأمان
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = '//////';
$user = '/////';
$pass = '////';$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // في حال فشل الاتصال
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed: ' . $e->getMessage()]);
    exit;
}

// 3. إعدادات اللغة (Language Logic)
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
// اللغة الافتراضية عربية
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'ar'; 
$dir  = ($lang == 'ar') ? 'rtl' : 'ltr';

// 4. دالة تحويل الأرقام الموحدة (Global Number Converter)
function translate_num($str, $current_lang = null) {
    // إذا لم نحدد اللغة، نستخدم لغة الجلسة الحالية
    if ($current_lang === null) {
        global $lang;
        $current_lang = $lang;
    }

    if ($current_lang == 'en') return $str;

    $western = ['0','1','2','3','4','5','6','7','8','9'];
    $eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    
    return str_replace($western, $eastern, strval($str));
}

// 5. إعدادات مفاتيح الذكاء الاصطناعي (Face++ KYC API)
define('FACEPP_API_SECRET', '9208d150b2564f6b8cfb95827948a8b6');

// 6. إعدادات واجهة برمجة تطبيقات ترون (TronGrid API)
// قم بوضع مفتاحك الحقيقي هنا (احصل عليه مجاناً من موقع trongrid.io)
$trongrid_api_key = 'c505578e-7937-45dd-8216-84af57615642';

// ⚠️ هام جداً: لا تضع وسم إغلاق PHP هنا. اتركه مفتوحاً.
// هذا يمنع إرسال أي مسافات بيضاء بالخطأ تكسر عملية تسجيل الدخول.