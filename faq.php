<?php
// faq.php - Frequently Asked Questions
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
// تم تحديث اسم المنصة هنا إلى Wealth Excel
$page_title = ($lang == 'ar') ? "الأسئلة الشائعة - Wealth Excel" : "FAQ - Wealth Excel";
require 'header.php';
?>

<style>
    .faq-container {
        max-width: 900px;
        margin: 40px auto;
        padding: 20px;
        background: rgba(15, 23, 42, 0.7);
        border-radius: 16px;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    .faq-container h1 {
        color: var(--gold);
        text-align: center;
        margin-bottom: 30px;
        font-size: 2rem;
    }
    .faq-item {
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
    .faq-question {
        font-size: 1.1rem;
        font-weight: bold;
        color: #f1f5f9;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
    }
    .faq-question i {
        color: var(--gold);
        transition: transform 0.3s;
    }
    .faq-question.active i {
        transform: rotate(180deg);
    }
    .faq-answer {
        display: none;
        padding: 10px 0 5px;
        color: #cbd5e1;
        line-height: 1.6;
        font-size: 14px;
    }
    .faq-answer.show {
        display: block;
    }
    .commitment-box {
        background: rgba(212, 175, 55, 0.05);
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 12px;
        padding: 25px;
        margin-top: 40px;
        text-align: center;
    }
    .commitment-box h3 {
        color: var(--gold);
        margin-top: 0;
        margin-bottom: 15px;
    }
    .commitment-box p {
        color: #f1f5f9;
        margin: 0;
        line-height: 1.7;
    }
</style>

<div class="container faq-container">
    <h1><?php echo ($lang == 'ar') ? 'الأسئلة الشائعة' : 'Frequently Asked Questions'; ?></h1>

    <?php
    $faq_items = [
        'ar' => [
            ['q' => 'ما هي Wealth Excel؟', 
             'a' => 'Wealth Excel منصة رقمية متخصصة في المعرفة المالية والتقنيات الذكية، توفر بيئة احترافية تجمع بين التعليم، والأدوات المتقدمة، والتحليلات الحديثة، بهدف تمكين المستخدمين من تطوير مهاراتهم وفهم الأسواق المالية باستخدام أحدث التقنيات.'],
             
            ['q' => 'ما الخدمات التي تقدمها Wealth Excel؟', 
             'a' => 'توفر Wealth Excel مجموعة متكاملة من الأدوات الذكية، ولوحات التحليل، والتقارير، والمحتوى التعليمي، وتقنيات إدارة البيانات، لتقديم تجربة احترافية تساعد المستخدمين على تطوير معارفهم ومهاراتهم المالية.'],
             
            ['q' => 'هل Wealth Excel شركة قانونية؟', 
             'a' => 'نعم.<br>Wealth Excel تعمل وفق كيان قانوني مسجل، وتلتزم بالأنظمة والمعايير المعمول بها، مع تطبيق أعلى مستويات الشفافية والامتثال وحماية المستخدمين.'],
             
            // -- تم استبدال هذا القسم بالكامل حسب طلبك --
            ['q' => 'هل Wealth Excel مرخصة ومسجلة رسميًا؟', 
             'a' => 'نعم، WEALTH EXCEL شركة مسجلة في ولاية كاليفورنيا بالولايات المتحدة الأمريكية، وتتوفر بيانات تسجيلها ضمن السجلات الرسمية للولاية.<br><br>تعمل الشركة في مجال الاستشارات والخدمات المالية، وتقدم حلولًا تشمل التخطيط المالي، التخطيط للتقاعد، الاستراتيجيات الضريبية، وحلولًا مخصصة لأصحاب الأعمال وإدارة الثروات.<br><br>وتتوفر البيانات المؤسسية والمعلومات المتعلقة بالشركة عبر المصادر والسجلات الرسمية ذات الصلة، بما يتيح الاطلاع عليها والتحقق منها بشكل مباشر.'],
             // ------------------------------------------

            ['q' => 'هل بيانات المستخدمين آمنة؟', 
             'a' => 'بالتأكيد.<br>تعتمد Wealth Excel أحدث تقنيات التشفير وأنظمة الحماية الرقمية، مع تطبيق أعلى معايير أمن المعلومات للحفاظ على خصوصية المستخدمين وسلامة بياناتهم.'],
             
            ['q' => 'هل يمكن استخدام المنصة من أي مكان؟', 
             'a' => 'نعم.<br>يمكن الوصول إلى Wealth Excel من معظم دول العالم عبر مختلف الأجهزة، مع استمرار تطوير البنية التقنية لتوفير أفضل تجربة استخدام.'],
             
            ['q' => 'هل المنصة مناسبة للمبتدئين؟', 
             'a' => 'نعم.<br>تم تصميم Wealth Excel لتناسب جميع المستويات، حيث تجمع بين سهولة الاستخدام والمحتوى الاحترافي والأدوات المتقدمة، سواء للمبتدئين أو لأصحاب الخبرة.'],
             
            ['q' => 'هل يتم تحديث المنصة باستمرار؟', 
             'a' => 'نعم.<br>يعمل فريق Wealth Excel بشكل مستمر على تطوير المنصة، وإضافة مزايا وتقنيات جديدة، وتحسين الأداء، لضمان تجربة استخدام حديثة ومستقرة.'],
             
            ['q' => 'هل تعتمد Wealth Excel على تقنيات الذكاء الاصطناعي؟', 
             'a' => 'نعم.<br>تستفيد المنصة من أحدث تقنيات الذكاء الاصطناعي وتحليل البيانات لدعم تجربة المستخدم، وتحسين سرعة الأداء، وتطوير الأدوات والخدمات بشكل مستمر.'],
             
            ['q' => 'ما الذي يميز Wealth Excel؟', 
             'a' => 'تجمع Wealth Excel بين التقنية الحديثة، والواجهة السهلة، والأمان، وسرعة الأداء، والتطوير المستمر، لتقديم تجربة احترافية تناسب المستخدمين من مختلف المستويات.'],
             
            ['q' => 'هل توجد رسوم خفية؟', 
             'a' => 'لا.<br>تعرض جميع الرسوم والخدمات بوضوح داخل المنصة، التزامًا بمبدأ الشفافية ووضوح المعلومات.'],
             
            ['q' => 'هل يمكنني استخدام المنصة من الهاتف؟', 
             'a' => 'نعم.<br>تدعم Wealth Excel الهواتف الذكية والأجهزة اللوحية وأجهزة الكمبيوتر، مع تصميم متوافق يضمن تجربة استخدام سلسة على جميع الأجهزة.'],
             
            ['q' => 'كيف يتم حماية الحسابات؟', 
             'a' => 'تعتمد Wealth Excel أنظمة حماية متقدمة تشمل تشفير البيانات، والتحقق الأمني، والمراقبة المستمرة، بهدف المحافظة على أمان حسابات المستخدمين.'],
             
            ['q' => 'هل يتم تطوير مزايا جديدة مستقبلًا؟', 
             'a' => 'بالتأكيد.<br>تلتزم Wealth Excel بالابتكار المستمر، وإطلاق أدوات وخدمات وتقنيات جديدة تواكب تطور الأسواق والتكنولوجيا العالمية.'],
             
            ['q' => 'هل تتوافق Wealth Excel مع المعايير العالمية؟', 
             'a' => 'نعم.<br>تلتزم Wealth Excel بتطبيق أفضل الممارسات العالمية في مجالات الجودة، والأمان، وحماية البيانات، وتجربة المستخدم، والتطوير التقني.'],
             
            ['q' => 'لماذا يثق المستخدمون في Wealth Excel؟', 
             'a' => 'لأن المنصة تجمع بين التسجيل القانوني، والبنية التقنية الحديثة، والشفافية، والأمان، والتطوير المستمر، مع فريق متخصص يعمل على تقديم تجربة احترافية تلبي احتياجات المستخدمين.'],
             
            ['q' => 'هل يمكن التحقق من بيانات الشركة؟', 
             'a' => 'نعم.<br>يمكن لأي مستخدم التحقق من بيانات التسجيل القانونية الخاصة بـ Wealth Excel من خلال الجهات الرسمية باستخدام أرقام التسجيل المنشورة على الموقع.'],
        ],
        'en' => [
            ['q' => 'What is Wealth Excel?', 
             'a' => 'Wealth Excel is a digital platform specializing in financial knowledge and smart technologies. It provides a professional environment combining education, advanced tools, and modern analytics to empower users to develop their skills and understand financial markets using the latest technologies.'],
             
            ['q' => 'What services does Wealth Excel offer?', 
             'a' => 'Wealth Excel provides a comprehensive suite of smart tools, analysis dashboards, reports, educational content, and data management technologies to offer a professional experience that helps users develop their financial knowledge and skills.'],
             
            ['q' => 'Is Wealth Excel a legal company?', 
             'a' => 'Yes.<br>Wealth Excel operates under a registered legal entity and complies with applicable regulations and standards, applying the highest levels of transparency, compliance, and user protection.'],
             
            // -- تم تحديث النسخة الإنجليزية لتتطابق مع المعنى العربي الجديد --
            ['q' => 'Is Wealth Excel officially licensed and registered?', 
             'a' => 'Yes, WEALTH EXCEL is a registered company in the State of California, USA, and its registration data is available in the official state records.<br><br>The company operates in the field of financial consulting and services, offering solutions that include financial planning, retirement planning, tax strategies, and customized solutions for business owners and wealth management.<br><br>Corporate data and company-related information are available through relevant official sources and records, allowing direct access and verification.'],
             // -------------------------------------------------------------

            ['q' => 'Is user data safe?', 
             'a' => 'Absolutely.<br>Wealth Excel utilizes the latest encryption technologies and digital protection systems, applying the highest information security standards to maintain user privacy and data integrity.'],
             
            ['q' => 'Can the platform be used from anywhere?', 
             'a' => 'Yes.<br>Wealth Excel can be accessed from most countries worldwide across various devices, with continuous development of the technical infrastructure to provide the best user experience.'],
             
            ['q' => 'Is the platform suitable for beginners?', 
             'a' => 'Yes.<br>Wealth Excel is designed to suit all levels, combining ease of use, professional content, and advanced tools, whether for beginners or experienced individuals.'],
             
            ['q' => 'Is the platform continuously updated?', 
             'a' => 'Yes.<br>The Wealth Excel team continuously works on developing the platform, adding new features and technologies, and improving performance to ensure a modern and stable user experience.'],
             
            ['q' => 'Does Wealth Excel rely on Artificial Intelligence technologies?', 
             'a' => 'Yes.<br>The platform utilizes the latest AI and data analysis technologies to support the user experience, improve performance speed, and continuously develop tools and services.'],
             
            ['q' => 'What makes Wealth Excel unique?', 
             'a' => 'Wealth Excel combines modern technology, an intuitive interface, security, fast performance, and continuous development to provide a professional experience suitable for users of all levels.'],
             
            ['q' => 'Are there any hidden fees?', 
             'a' => 'No.<br>All fees and services are clearly displayed within the platform, in commitment to the principle of transparency and clarity of information.'],
             
            ['q' => 'Can I use the platform from my phone?', 
             'a' => 'Yes.<br>Wealth Excel supports smartphones, tablets, and computers, with a responsive design ensuring a seamless user experience across all devices.'],
             
            ['q' => 'How are accounts protected?', 
             'a' => 'Wealth Excel relies on advanced protection systems including data encryption, security verification, and continuous monitoring, aimed at maintaining the security of user accounts.'],
             
            ['q' => 'Will new features be developed in the future?', 
             'a' => 'Absolutely.<br>Wealth Excel is committed to continuous innovation, launching new tools, services, and technologies that keep pace with global market and technological developments.'],
             
            ['q' => 'Does Wealth Excel comply with international standards?', 
             'a' => 'Yes.<br>Wealth Excel is committed to applying global best practices in quality, security, data protection, user experience, and technical development.'],
             
            ['q' => 'Why do users trust Wealth Excel?', 
             'a' => 'Because the platform combines legal registration, modern technical infrastructure, transparency, security, and continuous development, with a specialized team working to provide a professional experience that meets user needs.'],
             
            ['q' => 'Can the company\'s data be verified?', 
             'a' => 'Yes.<br>Any user can verify Wealth Excel\'s legal registration data through official channels using the registration numbers published on the site.'],
        ],
    ];
    $items = ($lang == 'ar') ? $faq_items['ar'] : $faq_items['en'];
    ?>

    <?php foreach ($items as $index => $item): ?>
    <div class="faq-item">
        <div class="faq-question" onclick="toggleAnswer(<?php echo $index; ?>)">
            <span><?php echo $item['q']; ?></span>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="faq-answer" id="answer-<?php echo $index; ?>">
            <?php echo $item['a']; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="commitment-box">
        <!-- تم تحديث اسم المنصة هنا إلى Wealth Excel -->
        <h3><?php echo ($lang == 'ar') ? 'التزام Wealth Excel' : 'Wealth Excel\'s Commitment'; ?></h3>
        <p>
            <?php echo ($lang == 'ar') 
                ? 'نؤمن بأن الثقة تُبنى بالشفافية والجودة والالتزام، لذلك نحرص على توفير بيئة رقمية آمنة، وبنية تقنية متطورة، وخدمات احترافية، مع الالتزام المستمر بحماية بيانات المستخدمين، وتطوير المنصة، وتقديم تجربة موثوقة تواكب أعلى المعايير العالمية.' 
                : 'We believe that trust is built on transparency, quality, and commitment. Therefore, we are dedicated to providing a safe digital environment, advanced technical infrastructure, and professional services, with a continuous commitment to protecting user data, developing the platform, and delivering a reliable experience that meets the highest international standards.'; ?>
        </p>
    </div>
</div>

<script>
    function toggleAnswer(index) {
        const answerDiv = document.getElementById('answer-' + index);
        const questionDiv = answerDiv.previousElementSibling;
        answerDiv.classList.toggle('show');
        questionDiv.classList.toggle('active');
    }
</script>

<?php require 'footer.php'; ?>