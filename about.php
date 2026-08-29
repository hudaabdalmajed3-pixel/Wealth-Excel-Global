<?php
// about.php - About Us page (Educational, non-investment)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = in_array($_GET['lang'], ['ar', 'en']) ? $_GET['lang'] : 'en';
    $qs = $_GET; unset($qs['lang']);
    $url = strtok($_SERVER["REQUEST_URI"], '?') . (empty($qs) ? '' : '?' . http_build_query($qs));
    header("Location: " . $url);
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';
$page_title = ($lang == 'ar') ? "من نحن - Wealth Excel Global" : "About Us - Wealth Excel Global"; // تعديل الاسم هنا
require 'header.php';
?>

<style>
    .about-container {
        max-width: 1000px;
        margin: 40px auto;
        padding: 20px;
        background: rgba(15, 23, 42, 0.7);
        border-radius: 16px;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .about-container h1 {
        color: var(--gold);
        text-align: center;
        margin-bottom: 30px;
        font-size: 2rem;
    }
    .about-container h2 {
        color: #f1f5f9;
        font-size: 1.3rem;
        margin-top: 25px;
        margin-bottom: 15px;
        border-right: 3px solid var(--gold);
        padding-right: 15px;
    }
    html[dir="ltr"] .about-container h2 {
        border-right: none;
        border-left: 3px solid var(--gold);
        padding-left: 15px;
    }
    .about-container p {
        color: #cbd5e1;
        line-height: 1.7;
        margin-bottom: 15px;
        font-size: 14px;
    }
    .team-section {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 30px;
        justify-content: center;
    }
    .team-card {
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 12px;
        padding: 20px;
        width: 200px;
        text-align: center;
    }
    .team-card i {
        font-size: 40px;
        color: var(--gold);
        margin-bottom: 10px;
    }
    .team-card h4 {
        margin: 10px 0 5px;
        color: #fff;
    }
    .team-card p {
        font-size: 12px;
        color: #94a3b8;
        margin: 0;
    }
    .mission-box {
        background: rgba(212, 175, 55, 0.05);
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 12px;
        padding: 20px;
        margin: 25px 0;
        text-align: center;
    }
    .mission-box p {
        margin: 0;
    }
</style>

<div class="container about-container">
    <h1><?php echo ($lang == 'ar') ? 'من نحن' : 'About Us'; ?></h1>
    
    <div class="mission-box">
        <p><?php echo ($lang == 'ar') 
            ? 'Wealth Excel هي منصة تعليمية رقمية متخصصة في تطوير المعرفة المالية، تجمع بين المحتوى التفاعلي والأدوات الذكية والبيئة العملية لمساعدة المستخدمين على فهم الأسواق المالية واكتساب المهارات اللازمة لاتخاذ قرارات أكثر وعيًا. صُممت المنصة وفق معايير حديثة لتوفر تجربة آمنة وسهلة واحترافية، تجمع بين التعلم والتطبيق ضمن بيئة موثوقة تدعم التطور المستمر.' 
            : 'Wealth Excel is a specialized digital educational platform for developing financial knowledge, combining interactive content, smart tools, and a practical environment to help users understand financial markets and acquire the skills needed to make more informed decisions. The platform is designed according to modern standards to provide a safe, easy, and professional experience, combining learning and application within a reliable environment that supports continuous development.'; ?> 
        </p>
    </div>

    <h2><?php echo ($lang == 'ar') ? 'رؤيتنا' : 'Our Vision'; ?></h2>
    <p><?php echo ($lang == 'ar') 
        ? 'أن نكون من أبرز المنصات التعليمية في مجال المعرفة المالية، من خلال تقديم تجربة تعليمية حديثة، ومحتوى احترافي، وأدوات متطورة تساعد المستخدمين على تنمية مهاراتهم ومواكبة تطور الأسواق.' 
        : 'To be among the leading educational platforms in the field of financial knowledge by providing a modern educational experience, professional content, and advanced tools that help users develop their skills and keep pace with market developments.'; ?>
    </p>

    <h2><?php echo ($lang == 'ar') ? 'رسالتنا' : 'Our Mission'; ?></h2>
    <p><?php echo ($lang == 'ar') 
        ? 'توفير بيئة تعليمية متكاملة تجمع بين المحتوى المتخصص، والأدوات العملية، والتقنيات الحديثة، لتمكين المستخدمين من بناء معرفة مالية قوية وتطوير مهاراتهم بثقة واحترافية.' 
        : 'To provide an integrated educational environment that combines specialized content, practical tools, and modern technologies, empowering users to build strong financial knowledge and develop their skills with confidence and professionalism.'; ?>
    </p>

    <h2><?php echo ($lang == 'ar') ? 'قيمنا' : 'Our Values'; ?></h2>
    <p><?php echo ($lang == 'ar') 
        ? '• <strong>الاحترافية:</strong> تقديم محتوى وأدوات بمعايير عالية الجودة.<br>• <strong>الأمان:</strong> توفير بيئة موثوقة تحترم خصوصية المستخدمين وتحمي بياناتهم.<br>• <strong>الشفافية:</strong> الوضوح في عرض المعلومات وآلية استخدام المنصة.<br>• <strong>الابتكار:</strong> تطوير مستمر للخدمات والأدوات بما يواكب التقنيات الحديثة.<br>• <strong>التطوير المستمر:</strong> دعم رحلة التعلم وتنمية المهارات بصورة مستدامة.' 
        : '• <strong>Professionalism:</strong> Delivering high-quality content and tools.<br>• <strong>Security:</strong> Providing a reliable environment that respects user privacy and protects their data.<br>• <strong>Transparency:</strong> Clarity in presenting information and platform usage mechanisms.<br>• <strong>Innovation:</strong> Continuous development of services and tools to keep pace with modern technologies.<br>• <strong>Continuous Development:</strong> Supporting the learning journey and sustainable skill development.'; ?>
    </p>

    <h2><?php echo ($lang == 'ar') ? 'فريق العمل' : 'Our Team'; ?></h2>
    <div class="team-section">
        <div class="team-card"><i class="fa-solid fa-chart-line"></i><h4><?php echo ($lang == 'ar') ? 'محللون ماليون' : 'Financial Analysts'; ?></h4><p><?php echo ($lang == 'ar') ? 'خبراء في الأسواق' : 'Market experts'; ?></p></div>
        <div class="team-card"><i class="fa-solid fa-code"></i><h4><?php echo ($lang == 'ar') ? 'مطورو تقنية' : 'Tech Developers'; ?></h4><p><?php echo ($lang == 'ar') ? 'بناء الأدوات والتطبيقات' : 'Building tools & apps'; ?></p></div>
        <div class="team-card"><i class="fa-solid fa-graduation-cap"></i><h4><?php echo ($lang == 'ar') ? 'مدربون معتمدون' : 'Certified Trainers'; ?></h4><p><?php echo ($lang == 'ar') ? 'محتوى تعليمي' : 'Educational content'; ?></p></div>
    </div>

    <h2><?php echo ($lang == 'ar') ? 'تواصل معنا' : 'Contact Us'; ?></h2>
    <p><?php echo ($lang == 'ar') 
        ? 'للاستفسارات أو الاقتراحات، يمكنكم مراسلتنا عبر البريد الإلكتروني: <a href="mailto:supportwealthexcelglobal@gmail.com" style="color:var(--gold);">supportwealthexcelglobal@gmail.com</a> أو عبر قنوات التواصل الاجتماعي الموجودة في الصفحة الرئيسية.' 
        : 'For inquiries or suggestions, you can email us at: <a href="mailto:supportwealthexcelglobal@gmail.com" style="color:var(--gold);">supportwealthexcelglobal@gmail.com</a> or via our social media channels on the homepage.'; ?>
    </p>
</div>

<?php require 'footer.php'; ?>