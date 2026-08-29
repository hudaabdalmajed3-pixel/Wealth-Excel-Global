<?php
// إعداد وبدء الجلسة (Session) لحفظ بيانات المستخدم مثل لغة العرض
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// جلب الإعدادات الأساسية للمشروع
require_once 'config.php';

// التحقق مما إذا كان المستخدم قد طلب تغيير اللغة (عربي أو إنجليزي)
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = in_array($_GET['lang'], ['ar', 'en']) ? $_GET['lang'] : 'en';
    $qs = $_GET; unset($qs['lang']);
    $url = strtok($_SERVER["REQUEST_URI"], '?') . (empty($qs) ? '' : '?' . http_build_query($qs));
    header("Location: " . $url);
    exit();
}

// تحديد لغة الصفحة الحالية واتجاه النص (يمين لليسار أو يسار لليمين)
$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

// دالة (Function) لتحويل الأرقام الإنجليزية إلى أرقام هندية (عربية) إذا كانت اللغة هي العربية
if (!function_exists('n')) {
    function n($num, $current_lang) {
        if ($current_lang !== 'ar') return $num;
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        return str_replace($en, $ar, $num);
    }
}

// تحديد عنوان الصفحة بناءً على اللغة - تم تحديث الاسم إلى Wealth Excel
$page_title = ($lang == 'ar') ? "Wealth Excel - منصة معلومات" : "Wealth Excel - Information Platform";
require 'header.php'; 
?>

<!-- استدعاء ملفات التصميم (CSS) والخطوط -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script src="https://s3.tradingview.com/tv.js"></script>

<style>
    /* الإعدادات الأساسية لتصميم الصفحة والألوان */
    *, *::before, *::after { box-sizing: border-box; }
    :root {
        --gold: #d4af37;
        --bg-dark: #020617;
        --bg-card: #0f172a;
        --chart-bg: #131722; 
        --border: #334155;
        --text-muted: #94a3b8;
        --green: #10b981;
        --red: #ef4444;
    }

    body { 
        background-color: var(--bg-dark); 
        color: #fff; 
        margin: 0; padding: 0; 
        overflow-x: hidden; 
        width: 100%; 
        font-family: sans-serif;
        font-size: 13.5px;
    }

    /* تصميم شريط الأسعار المتحرك في الأعلى */
    .ticker-master-wrap { 
        width: 100%; height: 40px; 
        background: #000; border-bottom: 1px solid var(--gold); 
        position: relative; overflow: hidden;
    }
    .ticker-shield { 
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
        z-index: 50; background: rgba(0,0,0,0.01); 
        display: flex; align-items: center; justify-content: center; gap: 10px;
        pointer-events: auto; cursor: default;
    }
    .shield-text { font-family: sans-serif; font-weight: 900; font-size: 16px; color: var(--gold); opacity: 0.3; letter-spacing: 2px; }
    .ticker-widget { width: 100%; height: 100%; position: relative; z-index: 1; }

    /* تصميم منطقة الرسم البياني (Chart) */
    .chart-viewport { 
        position: relative; 
        width: 100%; 
        height: 450px; 
        background: var(--chart-bg); 
        border-radius: 12px; 
        border: 1px solid var(--border); 
        overflow: hidden; 
    }

    .tv-logo-blocker {
        position: absolute; bottom: 0; left: 0;
        width: 100%; height: 85px;  
        z-index: 100;
        background: #000000; 
        border-top: 1px solid var(--border); 
        pointer-events: auto; cursor: default; 
        display: flex; align-items: center; justify-content: flex-start;
        padding-left: 30px; 
    }
    .mini-wx-logo {
        font-family: sans-serif; font-weight: 900;
        color: var(--gold); font-size: 40px; letter-spacing: 5px; opacity: 1; 
    }

    /* استجابة التصميم للشاشات الصغيرة (الهواتف المحمولة) */
    @media (max-width: 768px) {
        .ticker-master-wrap { height: 50px; }
        section { padding: 25px 15px !important; }
        h1 { font-size: 1.8rem !important; }
        .chart-viewport { height: 380px; }
        .tv-logo-blocker { height: 75px; padding-left: 20px; }
        .mini-wx-logo { font-size: 30px; }
    }

    /* تنسيقات الأقسام والعناوين */
    section { padding: 40px 20px; max-width: 1200px; margin: 0 auto; border-bottom: 1px solid rgba(255,255,255,0.05); }
    h1 { font-size: 2rem; text-align: center; margin-bottom: 12px; font-weight: 700; line-height: 1.2; }
    h1 span { color: var(--gold); }
    h2 { font-size: 1.5rem; text-align: center; color: var(--gold); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 1px; }
    h3 { font-size: 1rem; text-align: center; color: #fff; margin-bottom: 8px; font-weight: 600; }
    p { font-size: 13px; color: var(--text-muted); line-height: 1.6; text-align: center; margin-bottom: 0; }

    /* تعديل مساحة العرض بعد حذف البطاقة ليأخذ الرسم البياني المساحة كاملة */
    .dashboard-grid { display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 25px; }
    
    .wx-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; transition: 0.3s; }
    
    /* تصميم رسالة التحذير من المخاطر */
    .risk-warning {
        background: rgba(16, 185, 129, 0.08);
        border-left: 3px solid var(--green);
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 11px;
        color: #a7f3d0;
        text-align: center;
        max-width: 800px;
        margin: 30px auto 0;
    }
    .risk-warning a { color: var(--gold); text-decoration: underline; }

    .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 15px; }
    
    /* تصميم الزر المخصص (ابدأ الاستكشاف) */
    .btn-calc-custom {
        display: inline-block;
        background: linear-gradient(135deg, var(--gold), #b8860b);
        color: #000;
        font-weight: bold;
        padding: 12px 28px;
        border-radius: 40px;
        text-decoration: none;
        font-size: 14px;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        border: none;
        cursor: pointer;
    }
    .btn-calc-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        filter: brightness(1.05);
    }
</style>

<!-- شريط الأسعار في أعلى الصفحة -->
<div class="ticker-master-wrap">
    <div class="ticker-shield"><span class="shield-text">WX</span></div>
    <div class="tradingview-widget-container ticker-widget">
        <div class="tradingview-widget-container__widget"></div>
        <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-ticker-tape.js" async>
        { "symbols": [{"proName":"OANDA:XAUUSD","title":"GOLD"},{"proName":"BITSTAMP:BTCUSD","title":"BTC"},{"proName":"FX_IDC:EURUSD","title":"EUR/USD"}], "colorTheme":"dark", "isTransparent":true, "displayMode":"regular", "locale":"<?php echo $lang; ?>" }
        </script>
    </div>
</div>

<div class="container-fluid" dir="<?php echo $dir; ?>">

    <!-- القسم الأول: العنوان الرئيسي والترحيب -->
    <section style="border:0; padding-top:20px;">
        <div class="hero-text">
            <?php if($lang == 'ar'): ?>
                <h1>منصة Wealth Excel <br><span>حلول متكاملة برؤية واضحة</span></h1>
                <p>تقنيات متطورة • معايير احترافية • شفافية في كل خطوة</p>
            <?php else: ?>
                <h1>Wealth Excel Platform <br><span>Integrated Solutions with Clear Vision</span></h1>
                <p>Advanced Technologies • Professional Standards • Transparency in Every Step</p>
            <?php endif; ?>
        </div>

        <!-- قسم الرسم البياني فقط بعد حذف عداد الزوار -->
        <div class="dashboard-grid">
            <div class="chart-viewport">
                <div class="tv-logo-blocker"><span class="mini-wx-logo">WX</span></div>
                <div id="main_index_chart" style="height:100%; width:100%;"></div>
                <script type="text/javascript">
                new TradingView.widget({
                    "autosize": true,
                    "symbol": "OANDA:XAUUSD",
                    "interval": "D",
                    "timezone": "Etc/UTC",
                    "theme": "dark",
                    "style": "1",
                    "locale": "<?php echo $lang; ?>",
                    "toolbar_bg": "#f1f3f6",
                    "enable_publishing": false,
                    "hide_side_toolbar": true, 
                    "allow_symbol_change": false, 
                    "save_image": false, 
                    "details": false, 
                    "hotlist": false, 
                    "calendar": false,
                    "container_id": "main_index_chart"
                });
                </script>
            </div>
        </div>
    </section>

    <!-- القسم الثاني: ما هي ويلث إكسل -->
    <section>
        <h2><?php echo ($lang=='ar')?'ما هي Wealth Excel؟':'What is Wealth Excel?'; ?></h2>
        <div class="grid-3">
            <div class="wx-card">
                <h3><?php echo ($lang=='ar')?'حلول متكاملة':'Integrated Solutions'; ?></h3>
                <p><?php echo ($lang=='ar')?'أدوات وخدمات رقمية ضمن تجربة واضحة ومنظمة.':'Digital tools and services within a clear and organized experience.'; ?></p>
            </div>
            <div class="wx-card">
                <h3><?php echo ($lang=='ar')?'معرفة وإرشاد':'Knowledge & Guidance'; ?></h3>
                <p><?php echo ($lang=='ar')?'معلومات مبسطة لفهم المنصة وآلية عملها وخدماتها خطوة بخطوة.':'Simplified information to understand the platform, its mechanisms, and services step by step.'; ?></p>
            </div>
            <div class="wx-card">
                <h3><?php echo ($lang=='ar')?'تقنيات وأدوات متطورة':'Advanced Technologies'; ?></h3>
                <p><?php echo ($lang=='ar')?'مجموعة من الأدوات المصممة لتوفير تجربة أكثر مرونة ووضوحًا':'A set of tools designed to provide a more flexible and clear experience.'; ?></p>
            </div>
        </div>
    </section>

    <!-- القسم الثالث: آلية العمل -->
    <section>
        <h2><?php echo ($lang=='ar')?'آلية العمل':'How It Works'; ?></h2>
        <div class="grid-3">
            <div class="wx-card">
                <h3 style="color:var(--gold); font-size: 1.2rem;"><?php echo n('1', $lang); ?>. <?php echo ($lang=='ar')?'بداية سهلة وواضحة':'Easy & Clear Start'; ?></h3>
                <p><?php echo ($lang=='ar')?'خطوات تسجيل مبسطة وسريعة للوصول إلى خدمات المنصة.':'Simplified and fast registration steps to access platform services.'; ?></p>
            </div>
            <div class="wx-card">
                <h3 style="color:var(--gold); font-size: 1.2rem;"><?php echo n('2', $lang); ?>. <?php echo ($lang=='ar')?'تجربة متكاملة':'Integrated Experience'; ?></h3>
                <p><?php echo ($lang=='ar')?'استكشف الخدمات والأدوات المتاحة وتعرّف على تفاصيلها بكل وضوح.':'Explore available services and tools and understand their details clearly.'; ?></p>
            </div>
            <div class="wx-card">
                <h3 style="color:var(--gold); font-size: 1.2rem;"><?php echo n('3', $lang); ?>. <?php echo ($lang=='ar')?'رؤية مستمرة للأداء':'Continuous Performance'; ?></h3>
                <p><?php echo ($lang=='ar')?'تابع البيانات والنتائج والتحديثات من مكان واحد بصورة منظمة وشفافة':'Track data, results, and updates from one place in an organized and transparent manner.'; ?></p>
            </div>
        </div>
    </section>

    <!-- القسم الرابع: نظرة عامة على الخدمات -->
    <section>
        <h2><?php echo ($lang=='ar')?'نظرة عامة على الخدمات':'Services Overview'; ?></h2>
        <div class="grid-3">
            <div class="wx-card">
                <h3><?php echo ($lang=='ar')?'الخدمات المتاحة':'Available Services'; ?></h3>
                <p><?php echo ($lang=='ar')?'استكشف مجموعة من الخدمات والأدوات المصممة لتجربة أكثر سهولة ووضوحًا.':'Explore a range of services and tools designed for an easier and clearer experience.'; ?></p>
            </div>
            <div class="wx-card">
                <h3><?php echo ($lang=='ar')?'مزايا متكاملة':'Integrated Benefits'; ?></h3>
                <p><?php echo ($lang=='ar')?'أدوات وخصائص متنوعة ضمن بيئة رقمية منظمة وسهلة الاستخدام.':'Diverse tools and features within an organized and user-friendly digital environment.'; ?></p>
            </div>
            <div class="wx-card">
                <h3><?php echo ($lang=='ar')?'مشاركة المجتمع':'Community Sharing'; ?></h3>
                <p><?php echo ($lang=='ar')?'تواصل وشارك المعرفة والتجارب ضمن مجتمع Wealth Excel':'Connect and share knowledge and experiences within the Wealth Excel community.'; ?></p>
            </div>
        </div>
        
        <!-- زر ابدأ الاستكشاف الجديد متطابق باللغتين -->
        <div style="text-align:center; margin-top:20px;">
            <a href="plans.php" class="btn-calc-custom"><?php echo ($lang=='ar')?'ابدأ الاستكشاف ➤':'Start Exploring ➤'; ?></a>
        </div>
    </section>

    <!-- قسم التحذير من المخاطر -->
    <div class="risk-warning">
        <?php echo ($lang=='ar') ? 'تنبيه: جميع النتائج المعروضة هي تقديرية وغير مضمونة. الأداء السابق لا يشير إلى النتائج المستقبلية. الاستثمار ينطوي على مخاطر قد تؤدي إلى خسارة جزء أو كل رأس المال. يرجى الاطلاع على <a href="risk.php">تحذير المخاطر الكامل</a> قبل اتخاذ أي قرار.' : 'Notice: All projected returns are estimates and not guaranteed. Past performance does not indicate future results. Investing involves risk of loss of part or all of capital. Please review the full <a href="risk.php">Risk Warning</a> before making any decision.'; ?>
    </div>

</div>

<?php require 'footer.php'; ?>
</body>
</html>