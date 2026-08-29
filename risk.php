<?php
// risk.php - تحذير المخاطر (Risk Warning / Guidelines)
session_start();
$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

$trans = [
    'en' => [
        'title' => 'Platform Guidelines & Risk Warning',
        'last_update' => 'Last Updated:',
        'intro' => 'At Wealth Excel, we are committed to providing a safe environment. To ensure the best experience for our users, please review the following guidelines before using the platform.', // تعديل الاسم هنا
        'risk1_title' => '1. Educational Content',
        'risk1_text' => 'All materials and tools provided within the platform are designed for educational purposes and knowledge development. It is recommended to utilize them according to your educational and investment needs.',
        'risk2_title' => '2. User Responsibility',
        'risk2_text' => 'The user is responsible for how they apply the information or skills learned outside the platform. It is always recommended to verify any decision before implementing it.',
        'risk3_title' => '3. Service Availability',
        'risk3_text' => 'We strive to provide stable services at the highest possible level. However, maintenance operations or updates may occasionally require temporarily suspending some services to improve performance.',
        'risk4_title' => '4. Account Protection',
        'risk4_text' => 'To ensure the security of your account, keep your login credentials confidential and do not share them with anyone. We also recommend activating available security measures when needed.',
        'risk5_title' => '5. Data Protection',
        'risk5_text' => 'We are committed to applying modern security standards to protect user privacy and ensure the safety of their data within the platform.',
        'acknowledge' => 'I have read and understood these guidelines.',
        'back_home' => 'Back to Home'
    ],
    'ar' => [
        'title' => 'تحذير المخاطر',
        'last_update' => 'آخر تحديث:',
        'intro' => 'في Wealth Excel نحرص على توفير بيئة آمنة. ولضمان أفضل تجربة للمستخدمين، يرجى الاطلاع على الإرشادات التالية قبل استخدام المنصة.', // تعديل الاسم هنا
        'risk1_title' => '1. المحتوى التعليمي',
        'risk1_text' => 'جميع المواد والأدوات المتوفرة داخل المنصة أُعدت لأغراض تعليمية وتطوير المعرفة، ويُنصح بالاستفادة منها وفق احتياجاتك التعليميه والاستثماريه.',
        'risk2_title' => '2. مسؤولية المستخدم',
        'risk2_text' => 'يكون المستخدم مسؤولًا عن كيفية تطبيق ما يتعلمه من معلومات أو مهارات خارج المنصة، ويُنصح دائمًا بالتحقق من أي قرار قبل تنفيذه.',
        'risk3_title' => '3. توفر الخدمات',
        'risk3_text' => 'نعمل على تقديم خدمات مستقرة بأعلى مستوى ممكن، ومع ذلك قد تتطلب بعض عمليات الصيانة أو التحديثات إيقاف بعض الخدمات مؤقتًا لتحسين الأداء.',
        'risk4_title' => '4. حماية الحساب',
        'risk4_text' => 'لضمان أمان حسابك، حافظ على بيانات تسجيل الدخول ولا تشاركها مع أي طرف آخر، مع تفعيل وسائل الحماية المتاحة عند الحاجة.',
        'risk5_title' => '5. حماية البيانات',
        'risk5_text' => 'نلتزم بتطبيق معايير أمنية حديثة للمحافظة على خصوصية المستخدمين وسلامة بياناتهم داخل المنصة.',
        'acknowledge' => 'لقد قرأت وفهمت هذه الإرشادات.',
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
        :root { --gold-main: #d4af37; --bg-dark: #0f172a; --card-bg: #1e293b; --text-muted: #94a3b8; --warning: #f59e0b; }
        body { background: var(--bg-dark); color: #fff; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; line-height: 1.6; }
        .risk-container { max-width: 1000px; margin: 30px auto; padding: 20px; background: var(--card-bg); border-radius: 16px; border: 1px solid rgba(245,158,11,0.4); }
        h1 { color: var(--warning); border-bottom: 1px solid rgba(245,158,11,0.3); padding-bottom: 15px; }
        h2 { color: var(--warning); font-size: 1.3rem; margin-top: 25px; }
        .date { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px; }
        .ack-box { background: rgba(245,158,11,0.1); border: 1px solid var(--warning); padding: 15px; border-radius: 10px; margin: 20px 0; text-align: center; font-weight: bold; color: var(--warning); }
        .back-link { display: inline-block; margin-top: 20px; color: var(--gold-main); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        @media (max-width: 768px) { .risk-container { margin: 15px; padding: 15px; } }
    </style>
</head>
<body>
    <div class="risk-container">
        <h1><?php echo $txt['title']; ?></h1>
        <div class="date"><?php echo $txt['last_update']; ?> <?php echo date('d/m/Y'); ?></div>
        
        <p><?php echo $txt['intro']; ?></p>
        
        <h2><?php echo $txt['risk1_title']; ?></h2>
        <p><?php echo $txt['risk1_text']; ?></p>
        
        <h2><?php echo $txt['risk2_title']; ?></h2>
        <p><?php echo $txt['risk2_text']; ?></p>
        
        <h2><?php echo $txt['risk3_title']; ?></h2>
        <p><?php echo $txt['risk3_text']; ?></p>
        
        <h2><?php echo $txt['risk4_title']; ?></h2>
        <p><?php echo $txt['risk4_text']; ?></p>
        
        <h2><?php echo $txt['risk5_title']; ?></h2>
        <p><?php echo $txt['risk5_text']; ?></p>
        
        <div class="ack-box">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $txt['acknowledge']; ?>
        </div>
        
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> <?php echo $txt['back_home']; ?></a>
    </div>
</body>
</html>