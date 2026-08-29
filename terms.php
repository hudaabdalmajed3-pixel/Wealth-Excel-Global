<?php
// terms.php - شروط الخدمة
session_start();
$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

$trans = [
    'en' => [
        'title' => 'Terms of Service',
        'last_update' => 'Effective Date:',
        'term1_title' => '1. Acceptance of Terms',
        'term1_text' => 'By using the Wealth Excel platform, you agree to be bound by the Terms of Service, Privacy Policy, and all applicable regulations within the platform.', // تعديل الاسم هنا
        
        'term2_title' => '2. Eligibility',
        'term2_text' => 'The user must be of legal age and legally capable of creating an account and using the platform\'s services, complying with the laws and regulations applicable in their country of residence.',
        
        'term3_title' => '3. Account Registration',
        'term3_text' => 'The user is responsible for the accuracy of the information provided during registration, maintaining the confidentiality of login credentials, and not sharing them with any third party.',
        
        'term4_title' => '4. Platform Usage',
        'term4_text' => 'Wealth Excel provides a professional digital environment featuring advanced tools, services, and modern technologies to help the user leverage the platform\'s capabilities according to approved regulations.', // تعديل الاسم هنا
        
        'term5_title' => '5. Fees & Services',
        'term5_text' => 'Some services or features may be subject to operational fees or updates, which will be clarified within the platform as needed. Wealth Excel commits to transparency and notifying the user of any substantial updates.', // تعديل الاسم هنا
        
        'term6_title' => '6. Account Protection',
        'term6_text' => 'The user is obligated to maintain the confidentiality of their account details and is responsible for all activities conducted through it. It is necessary to contact support immediately upon suspecting any unauthorized use.',
        
        'term7_title' => '7. Permitted Use',
        'term7_text' => 'The platform must be used in accordance with regulations and laws. Misuse, attempts to breach systems, affecting platform stability, or harming users is strictly prohibited.',
        
        'term8_title' => '8. Account Suspension or Termination',
        'term8_text' => 'Wealth Excel reserves the right to suspend, restrict, or terminate any account proven to violate the terms of use or approved regulations, to maintain platform security and the rights of all users.', // تعديل الاسم هنا
        
        'back_home' => 'Back to Home'
    ],
    'ar' => [
        'title' => 'شروط الخدمة',
        'last_update' => 'تاريخ السريان:',
        'term1_title' => '1. قبول الشروط',
        'term1_text' => 'باستخدامك لمنصة Wealth Excel فإنك توافق على الالتزام بشروط الخدمة وسياسة الخصوصية وجميع الأنظمة المعمول بها داخل المنصة.', // تعديل الاسم هنا
        
        'term2_title' => '2. أهلية الاستخدام',
        'term2_text' => 'يجب أن يكون المستخدم بالغًا وقادرًا قانونيًا على إنشاء الحساب واستخدام خدمات المنصة، مع الالتزام بالقوانين والأنظمة السارية في بلد الإقامة.',
        
        'term3_title' => '3. إنشاء الحساب',
        'term3_text' => 'يتحمل المستخدم مسؤولية صحة المعلومات المقدمة عند التسجيل، والمحافظة على سرية بيانات الدخول وعدم مشاركتها مع أي طرف آخر.',
        
        'term4_title' => '4. استخدام المنصة',
        'term4_text' => 'توفر Wealth Excel بيئة رقمية احترافية تضم أدوات متقدمة وخدمات وتقنيات حديثة تساعد المستخدم على الاستفادة من إمكانيات المنصة وفق الأنظمة المعتمدة.', // تعديل الاسم هنا
        
        'term5_title' => '5. الرسوم والخدمات',
        'term5_text' => 'قد تخضع بعض الخدمات أو المزايا لرسوم تشغيل أو تحديثات يتم توضيحها داخل المنصة عند الحاجة، مع التزام Wealth Excel بالشفافية وإشعار المستخدم بأي تحديثات جوهرية.', // تعديل الاسم هنا
        
        'term6_title' => '6. حماية الحساب',
        'term6_text' => 'يلتزم المستخدم بالحفاظ على سرية بيانات حسابه، ويعد مسؤولًا عن جميع الأنشطة التي تتم من خلاله، مع ضرورة التواصل مع الدعم فور الاشتباه بأي استخدام غير مصرح به.',
        
        'term7_title' => '7. الاستخدام المسموح',
        'term7_text' => 'يجب استخدام المنصة بما يتوافق مع الأنظمة والقوانين، ويمنع إساءة الاستخدام أو محاولة اختراق الأنظمة أو التأثير على استقرار المنصة أو الإضرار بالمستخدمين.',
        
        'term8_title' => '8. تعليق أو إيقاف الحساب',
        'term8_text' => 'تحتفظ Wealth Excel بحق تعليق أو تقييد أو إيقاف أي حساب يثبت مخالفته لشروط الاستخدام أو الأنظمة المعتمدة، وذلك حفاظًا على أمن المنصة وحقوق جميع المستخدمين.', // تعديل الاسم هنا
        
        'back_home' => 'العودة للرئيسية'
    ]
];
$txt = $trans[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $txt['title']; ?> - Wealth Excel Global</title> <!-- تعديل الاسم هنا -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --gold-main: #d4af37; --bg-dark: #0f172a; --card-bg: #1e293b; --text-muted: #94a3b8; }
        body { background: var(--bg-dark); color: #fff; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; line-height: 1.6; }
        .terms-container { max-width: 1000px; margin: 30px auto; padding: 20px; background: var(--card-bg); border-radius: 16px; border: 1px solid rgba(212,175,55,0.2); }
        h1 { color: var(--gold-main); border-bottom: 1px solid rgba(212,175,55,0.3); padding-bottom: 15px; }
        h2 { color: var(--gold-main); font-size: 1.3rem; margin-top: 25px; }
        .date { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-top: 30px; color: var(--gold-main); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 768px) { .terms-container { margin: 15px; padding: 15px; } }
    </style>
</head>
<body>
    <div class="terms-container">
        <h1><?php echo $txt['title']; ?></h1>
        <div class="date"><?php echo $txt['last_update']; ?> <?php echo date('d/m/Y'); ?></div>
        
        <h2><?php echo $txt['term1_title']; ?></h2>
        <p><?php echo $txt['term1_text']; ?></p>
        
        <h2><?php echo $txt['term2_title']; ?></h2>
        <p><?php echo $txt['term2_text']; ?></p>
        
        <h2><?php echo $txt['term3_title']; ?></h2>
        <p><?php echo $txt['term3_text']; ?></p>
        
        <h2><?php echo $txt['term4_title']; ?></h2>
        <p><?php echo $txt['term4_text']; ?></p>
        
        <h2><?php echo $txt['term5_title']; ?></h2>
        <p><?php echo $txt['term5_text']; ?></p>
        
        <h2><?php echo $txt['term6_title']; ?></h2>
        <p><?php echo $txt['term6_text']; ?></p>
        
        <h2><?php echo $txt['term7_title']; ?></h2>
        <p><?php echo $txt['term7_text']; ?></p>
        
        <h2><?php echo $txt['term8_title']; ?></h2>
        <p><?php echo $txt['term8_text']; ?></p>
        
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> <?php echo $txt['back_home']; ?></a>
    </div>
</body>
</html>