<?php
// privacy.php - سياسة الخصوصية لموقع Wealth Excel Global
session_start();
$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

// ترجمات الصفحة (تم تحديث الاسم إلى Wealth Excel Global)
$trans = [
    'en' => [
        'title' => 'Privacy Policy',
        'last_update' => 'Last Updated:',
        'intro' => 'At Wealth Excel Global, we value your privacy. This policy explains how we collect, use, and protect your personal information when you use our platform.',
        'collect_title' => '1. Information We Collect',
        'collect_text' => 'When you register, we may collect: email address, username, country of residence. We also automatically collect login times, IP addresses, and transaction history (deposits/withdrawals) for security and operational purposes.',
        'use_title' => '2. How We Use Your Information',
        'use_text' => 'We use your information to manage your account, process transactions, improve our services, communicate important updates, and prevent fraud or illegal activities.',
        'security_title' => '3. Data Security',
        'security_text' => 'We implement SSL encryption, hashed passwords, and regular security audits. However, no online system is 100% secure. You are responsible for keeping your login credentials confidential.',
        'sharing_title' => '4. Sharing with Third Parties',
        'sharing_text' => 'We do not sell or rent your personal data. We may share data only when required by law, to protect our legal rights, or with trusted service providers who assist in platform operations (e.g., hosting, email) under strict confidentiality agreements.',
        'cookies_title' => '5. Cookies',
        'cookies_text' => 'We use cookies to enhance user experience and analyze site traffic. You can disable cookies in your browser settings, but some features may not function properly.',
        'changes_title' => '6. Changes to This Policy',
        'changes_text' => 'We may update this policy from time to time. Continued use of the platform after changes constitutes acceptance of the new policy.',
        'contact_title' => '7. Contact Us',
        'contact_text' => 'If you have questions about this policy, please contact us at: supportwealthexcelglobal@gmail.com', 
        'back_home' => 'Back to Home'
    ],
    'ar' => [
        'title' => 'سياسة الخصوصية',
        'last_update' => 'آخر تحديث:',
        'intro' => 'في Wealth Excel Global، نحن نقدر خصوصيتك. تشرح هذه السياسة كيفية جمع معلوماتك الشخصية واستخدامها وحمايتها عند استخدام منصتنا.',
        'collect_title' => '1. المعلومات التي نجمعها',
        'collect_text' => 'عند التسجيل، قد نجمع: البريد الإلكتروني، اسم المستخدم، بلد الإقامة. نقوم أيضاً بجمع أوقات تسجيل الدخول وعناوين IP وسجل العمليات (الإيداعات والسحوبات) لأغراض الأمن والتشغيل.',
        'use_title' => '2. كيفية استخدام معلوماتك',
        'use_text' => 'نستخدم معلوماتك لإدارة حسابك، ومعالجة المعاملات، وتحسين خدماتنا، والتواصل بشأن التحديثات الهامة، ومنع الاحتيال أو الأنشطة غير القانونية.',
        'security_title' => '3. أمان البيانات',
        'security_text' => 'نحن نطبق تشفير SSL، وكلمات مرور مشفرة، ومراجعات أمنية دورية. ومع ذلك، لا يوجد نظام على الإنترنت آمن بنسبة 100%. أنت مسؤول عن الحفاظ على سرية بيانات دخولك.',
        'sharing_title' => '4. المشاركة مع أطراف ثالثة',
        'sharing_text' => 'نحن لا نبيع أو نؤجر بياناتك الشخصية. قد نشارك البيانات فقط عندما يطلب القانون ذلك، أو لحماية حقوقنا القانونية، أو مع مقدمي خدمات موثوقين يساعدون في تشغيل المنصة (مثل الاستضافة، البريد الإلكتروني) بموجب اتفاقيات سرية صارمة.',
        'cookies_title' => '5. ملفات تعريف الارتباط (كوكيز)',
        'cookies_text' => 'نستخدم الكوكيز لتحسين تجربة المستخدم وتحليل حركة المرور على الموقع. يمكنك تعطيل الكوكيز في إعدادات المتصفح، ولكن قد لا تعمل بعض الميزات بشكل صحيح.',
        'changes_title' => '6. التغييرات على هذه السياسة',
        'changes_text' => 'قد نقوم بتحديث هذه السياسة من وقت لآخر. استمرار استخدام المنصة بعد التغييرات يعني قبولك للسياسة الجديدة.',
        'contact_title' => '7. اتصل بنا',
        'contact_text' => 'إذا كان لديك أسئلة حول هذه السياسة، يرجى الاتصال بنا على: supportwealthexcelglobal@gmail.com',
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
    <!-- تم تعديل اسم المنصة في عنوان التبويب -->
    <title><?php echo $txt['title']; ?> - Wealth Excel Global</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --gold-main: #d4af37; --bg-dark: #0f172a; --card-bg: #1e293b; --text-muted: #94a3b8; }
        body { background: var(--bg-dark); color: #fff; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; line-height: 1.6; }
        .policy-container { max-width: 1000px; margin: 30px auto; padding: 20px; background: var(--card-bg); border-radius: 16px; border: 1px solid rgba(212,175,55,0.2); }
        h1 { color: var(--gold-main); border-bottom: 1px solid rgba(212,175,55,0.3); padding-bottom: 15px; }
        h2 { color: var(--gold-main); font-size: 1.3rem; margin-top: 25px; }
        .date { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-top: 30px; color: var(--gold-main); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 768px) { .policy-container { margin: 15px; padding: 15px; } }
    </style>
</head>
<body>
    <div class="policy-container">
        <h1><?php echo $txt['title']; ?></h1>
        <div class="date"><?php echo $txt['last_update']; ?> <?php echo date('d/m/Y'); ?></div>
        <p><?php echo $txt['intro']; ?></p>
        <h2><?php echo $txt['collect_title']; ?></h2>
        <p><?php echo $txt['collect_text']; ?></p>
        <h2><?php echo $txt['use_title']; ?></h2>
        <p><?php echo $txt['use_text']; ?></p>
        <h2><?php echo $txt['security_title']; ?></h2>
        <p><?php echo $txt['security_text']; ?></p>
        <h2><?php echo $txt['sharing_title']; ?></h2>
        <p><?php echo $txt['sharing_text']; ?></p>
        <h2><?php echo $txt['cookies_title']; ?></h2>
        <p><?php echo $txt['cookies_text']; ?></p>
        <h2><?php echo $txt['changes_title']; ?></h2>
        <p><?php echo $txt['changes_text']; ?></p>
        <h2><?php echo $txt['contact_title']; ?></h2>
        <p><?php echo $txt['contact_text']; ?></p>
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> <?php echo $txt['back_home']; ?></a>
    </div>
</body>
</html>