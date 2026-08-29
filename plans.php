<?php
// plans.php - Service Plans (Non-promotional, realistic, with risk disclaimer)
// MODIFIED: Updated Arabic text strings for a more interactive and clear user experience based on the reference images.
require 'config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. إعدادات اللغة
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = in_array($_GET['lang'], ['ar', 'en']) ? $_GET['lang'] : 'ar';
    $qs = $_GET; unset($qs['lang']);
    $url = strtok($_SERVER["REQUEST_URI"], '?') . (empty($qs) ? '' : '?' . http_build_query($qs));
    header("Location: " . $url);
    exit();
}
$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

require 'header.php'; 

// 2. جلب البيانات من قاعدة البيانات
$plans_db = [];
$db_error = null;
try {
    $stmt = $pdo->query("SELECT * FROM plans ORDER BY min_price ASC");
    $plans_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { 
    $db_error = $e->getMessage();
}

// --- دالة الترجمة وتنظيف الأسماء ---
function getCleanTranslatedName($dbName, $lang) {
    $name = trim($dbName);
    $dbMap = [
        'L-Basic'        => ['ar' => 'أساسي', 'en' => 'Basic'],
        'Basic'          => ['ar' => 'أساسي', 'en' => 'Basic'],
        'Premium'        => ['ar' => 'متميز', 'en' => 'Premium'],
        'L1 - Starter'   => ['ar' => 'مبتدئ', 'en' => 'Starter'],
        'L2 - Bronze'    => ['ar' => 'برونزي', 'en' => 'Bronze'],
        'L3 - Silver'    => ['ar' => 'فضي', 'en' => 'Silver'],
        'L4 - Gold'      => ['ar' => 'ذهبي', 'en' => 'Gold'],
        'L5 - Platinum'  => ['ar' => 'بلاتيني', 'en' => 'Platinum'],
        'L6 - Diamond'   => ['ar' => 'ماسي', 'en' => 'Diamond'],
        'L7 - Elite'     => ['ar' => 'إيليت', 'en' => 'Elite'],
        'L8 - VIP'       => ['ar' => 'VIP', 'en' => 'VIP']
    ];

    if (isset($dbMap[$name])) return ($lang == 'ar') ? $dbMap[$name]['ar'] : $dbMap[$name]['en'];
    return $name; 
}

// دالة تحويل الأرقام حسب اللغة
function n($num) {
    global $lang;
    $formatted = number_format((float)$num, 0, '.', ','); 
    if($lang == 'ar') return str_replace(range(0,9), ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], $formatted);
    return $formatted;
}

$processed_plans = [];
if (!empty($plans_db)) {
    $count = count($plans_db);
    for($i = 0; $i < $count; $i++) {
        $p = $plans_db[$i];
        
        $min = (float)$p['min_price'];
        
        if (isset($p['max_price']) && $p['max_price'] > 0) {
             $max = (float)$p['max_price'];
        } else {
             $max = ($i < $count - 1) ? (float)$plans_db[$i+1]['min_price'] - 1 : -1;
        }

        $monthly_pct = (float)$p['roi_percentage'];

        $processed_plans[] = [
            'id'            => $p['id'],
            'name'          => getCleanTranslatedName($p['name'], $lang),
            'min'           => $min,
            'max'           => $max,
            'month'         => $monthly_pct,
            'daily'         => $monthly_pct / 30,
            'annual_comp'   => (pow(1 + ($monthly_pct / 100), 12) - 1) * 100
        ];
    }
}

// 3. القاموس (تم التحديث للتطابق مع الصور 22, 23, 24, 26)
$txt = [
    'en' => [
        'main_title' => 'Service Options',
        'sub_title' => 'Flexible services designed to suit different needs and experiences.',
        'top_note' => 'Explore the available options and discover the features and benefits of each service.',
        'risk_disclaimer' => 'Please note: All projected returns are estimates and not guaranteed. Past performance does not indicate future results. Investing involves risk of loss.',
        
        'lbl_range' => 'Activation Range:',
        'lbl_duration' => 'Cycle Period:',
        'val_duration' => '30 Days',
        'lbl_model' => 'Model:',
        'val_model' => 'Performance Cycle',
        'lbl_upgrade' => 'Upgrade:',
        'val_upgrade' => 'Available',
        
        'calc_title' => 'Interactive Exploration Tool',
        'calc_sub' => 'Discover available options interactively and compare details and features based on your choices.<br><span style="font-size:11px; opacity:0.6; margin-top:5px; display:block; line-height:1.6;">The tool provides a structured view to help you explore services and understand the differences more clearly.</span>',
        'calc_cap' => 'Enter the value you wish to explore', // حذف كلمة USDT (صورة 22)
        'calc_type' => 'Select the most suitable option',
        'opt_month' => 'Month', // تعديل النص (صورة 23)
        'opt_daily' => 'Day', // تعديل النص (صورة 23)
        'opt_cum' => 'Year (Compounded)', // تعديل النص (صورة 23)
        'btn_calc' => 'View Details and Results',
        'res_title' => 'Estimated Outcome',
        'res_wait' => 'Enter the value to show report...', // تعديل النص (صورة 26)
        'err_amount' => 'Please enter a valid amount.',
        'err_plan' => 'Amount is outside any available service range.',
        'lbl_plan' => 'Service Tier:',
        'lbl_profit' => 'Estimated Net:',
        'lbl_total' => 'Estimated Total:',
        'unlimited' => '+'
    ],
    'ar' => [
        'main_title' => 'خيارات الخدمات',
        'sub_title' => 'خدمات مرنة مصممة لتناسب احتياجات وتجارب مختلفة.',
        'top_note' => 'استكشف الخيارات المتاحة وتعرّف على خصائص ومزايا كل خدمة.',
        'risk_disclaimer' => 'تنبيه: جميع العوائد المعروضة هي تقديرية وغير مضمونة. الأداء السابق لا يشير إلى النتائج المستقبلية. الاستثمار ينطوي على مخاطر الخسارة.',
        
        'lbl_range' => 'نطاق التفعيل:',
        'lbl_duration' => 'مدة الدورة:',
        'val_duration' => '30 يوم',
        'lbl_model' => 'النموذج:',
        'val_model' => 'دورة أداء',
        'lbl_upgrade' => 'الترقية:',
        'val_upgrade' => 'متاح',

        'calc_title' => 'أداة الاستكشاف التفاعلية',
        'calc_sub' => 'اكتشف الخيارات المتاحة بطريقة سهلة وتفاعلية، وقارن بين التفاصيل والخصائص وفق اختياراتك.<br><span style="font-size:11px; opacity:0.6; margin-top:5px; display:block; line-height:1.6;">تمنحك الأداة عرضًا منظمًا يساعدك على استكشاف الخدمات وفهم الفروقات بينها بصورة أوضح.</span>',
        'calc_cap' => 'أدخل القيمة التي ترغب باستعراضها', // حذف كلمة USDT (صورة 22)
        'calc_type' => 'حدد الخيار الأنسب لك',
        'opt_month' => 'شهر', // تعديل النص (صورة 23)
        'opt_daily' => 'يوم', // تعديل النص (صورة 23)
        'opt_cum' => 'سنة (مركب)', // تعديل النص (صورة 23)
        'btn_calc' => 'استعرض التفاصيل والنتائج',
        'res_title' => 'تقدير النتيجة',
        'res_wait' => 'أدخل القيمة لظهور تقرير...', // تعديل النص (صورة 26)
        'err_amount' => 'أدخل مبلغاً صالحاً.',
        'err_plan' => 'المبلغ لا يقع ضمن نطاق أي خدمة متاحة.',
        'lbl_plan' => 'مستوى الخدمة:',
        'lbl_profit' => 'التقدير الصافي:',
        'lbl_total' => 'الإجمالي التقديري:',
        'unlimited' => '+'
    ]
];
$c = $txt[$lang];
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<style>
    :root { 
        --gold: #d4af37; 
        --bg-color: #0b0f19;
        --card-bg: #131b2f;
        --header-bg: #e2e8f0;
        --header-text: #0f172a;
    }
    body { font-family: '<?php echo ($lang=='ar')?'Cairo':'Inter'; ?>', sans-serif; }
    
    section { margin-bottom: 50px; }
    h1, h2 { color: #fff; margin-bottom: 8px; text-align: center; font-size: 1.6rem; font-weight: bold; }
    p.page-desc { text-align: center; color: #cbd5e1; margin-bottom: 10px; font-size: 13px; font-weight: 300; }

    .top-disclaimer {
        text-align: center; font-size: 12px; color: #94a3b8; font-weight: 300;
        margin-bottom: 30px; max-width: 800px; margin-left: auto; margin-right: auto;
        line-height: 1.6; opacity: 0.8;
    }
    .risk-warning {
        background: rgba(239, 68, 68, 0.1);
        border-left: 3px solid #ef4444;
        padding: 10px 15px;
        border-radius: 6px;
        font-size: 11px;
        color: #fca5a5;
        text-align: center;
        max-width: 800px;
        margin: 20px auto 0;
    }

    .plans-container { max-width: 1200px; margin: 0 auto; width: 100%; padding: 0 10px; box-sizing:border-box; }
    
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px; 
        justify-content: center;
    }

    @media (min-width: 1025px) {
        .plans-grid > :nth-last-child(-n+2):nth-child(11n+1), 
        .plans-grid > :nth-last-child(-n+2):nth-child(11n+1) ~ div {
            grid-column: span 1;
        }
    }

    .plan-card {
        background: var(--card-bg);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 6px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease, border-color 0.3s ease;
        height: fit-content; 
    }

    .plan-card:hover {
        transform: translateY(-3px);
        border-color: rgba(212, 175, 55, 0.6);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .plan-card-header {
        background: var(--header-bg);
        color: var(--header-text);
        text-align: center;
        padding: 6px 8px; 
        font-weight: bold;
        font-size: 13px;
        letter-spacing: 0.5px;
    }

    .plan-card-body {
        padding: 10px 8px; 
        display: flex;
        flex-direction: column;
        gap: 6px; 
    }

    .plan-detail {
        display: flex;
        flex-direction: column;
        gap: 1px; 
        text-align: <?php echo ($lang=='ar')?'right':'left'; ?>; 
    }

    .plan-detail-label {
        font-size: 9px; 
        color: #94a3b8;
        font-weight: 300;
        line-height: 1.1;
    }

    .plan-detail-value {
        font-size: 11px; 
        color: #f1f5f9;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.1;
    }

    @media (max-width: 1024px) {
        .plans-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .plans-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
        .plans-grid { 
            grid-template-columns: repeat(2, 1fr); 
            gap: 8px;
        }
        .plan-card-header {
            font-size: 11px;
            padding: 5px;
        }
        .plan-card-body {
            padding: 8px 6px;
            gap: 5px;
        }
        .plan-detail-label {
            font-size: 8px;
        }
        .plan-detail-value {
            font-size: 10px;
            white-space: normal;
            word-break: break-word;
        }
    }

    .calc-section { max-width: 800px; margin: 40px auto 0; padding: 0 10px; }
    .calc-container { display: flex; gap: 20px; margin-top: 20px; flex-direction: row; padding: 0; box-sizing:border-box;}
    
    .card-calc { background: #1e293b; padding: 25px; border-radius: 12px; border: 1px solid #334155; flex: 1; display: flex; flex-direction: column; justify-content: center; box-sizing: border-box; }
    .card-calc label { display: block; color: #94a3b8; margin-bottom: 8px; font-size: 12px; font-weight: bold; text-align: start; }
    .card-calc input, .card-calc select { width: 100%; padding: 12px; background: #020617; border: 1px solid #334155; color: #fff; border-radius: 6px; margin-bottom: 15px; outline: none; font-size: 14px; box-sizing: border-box; font-family: inherit;}
    .card-calc input:focus, .card-calc select:focus { border-color: var(--gold); }
    
    .btn-calc { 
        width: 100%; padding: 12px; background: linear-gradient(45deg, #d4af37, #b8860b); color: #000; 
        font-weight: 800; border: none; border-radius: 6px; cursor: pointer; margin-top: auto; font-size: 14px; transition: 0.3s;
    }
    .btn-calc:hover { filter: brightness(1.1); }
    
    .result-box { margin-top: 10px; text-align: center; width: 100%; }
    .res-val { font-size: 22px; color: #10b981; font-weight: bold; margin-top: 5px; display: block; }

    @media (max-width: 768px) {
        .calc-container { flex-direction: column; gap: 15px; }
    }
</style>

<div style="padding-top: 30px; padding-bottom: 60px;">

    <?php if($db_error): ?>
        <div style="background:red; color:white; padding:10px; text-align:center; border-radius:5px; max-width:800px; margin: 0 auto 20px;">
            Database Error: <?php echo $db_error; ?>
        </div>
    <?php endif; ?>

    <section>
        <h1><?php echo $c['main_title']; ?></h1>
        <p class="page-desc"><?php echo $c['sub_title']; ?></p>
        <div class="top-disclaimer"><?php echo $c['top_note']; ?></div>

        <div class="plans-container">
            <div class="plans-grid">
                <?php if(empty($processed_plans)): ?>
                    <div style="text-align:center; color:#aaa; grid-column: 1 / -1;">No service levels available.</div>
                <?php else: ?>
                    <?php foreach($processed_plans as $p): ?>
                        <?php
                            // 🔴 حذفنا رمز الـ $ أو كلمة USDT من الأرقام المعروضة لتبدو أرقاماً نقية (صورة 24)
                            if ($p['min'] == $p['max'] || $p['max'] == 0 || ($p['max'] == -1 && $p['min'] <= 100)) {
                                $rangeStr = n($p['min']); 
                            } else {
                                $maxStr = ($p['max'] == -1) ? $c['unlimited'] : n($p['max']);
                                $rangeStr = n($p['min']) . ' - ' . $maxStr;
                            }
                        ?>
                        <div class="plan-card">
                            <div class="plan-card-header">
                                <?php echo $p['name']; ?>
                            </div>
                            <div class="plan-card-body">
                                <div class="plan-detail">
                                    <span class="plan-detail-label"><?php echo $c['lbl_range']; ?></span>
                                    <span class="plan-detail-value" style="color: #cbd5e1; font-family: monospace;"><?php echo $rangeStr; ?></span>
                                </div>
                                <div class="plan-detail">
                                    <span class="plan-detail-label"><?php echo $c['lbl_duration']; ?></span>
                                    <span class="plan-detail-value"><?php echo $c['val_duration']; ?></span>
                                </div>
                                <div class="plan-detail">
                                    <span class="plan-detail-label"><?php echo $c['lbl_model']; ?></span>
                                    <span class="plan-detail-value"><?php echo $c['val_model']; ?></span>
                                </div>
                                <div class="plan-detail">
                                    <span class="plan-detail-label"><?php echo $c['lbl_upgrade']; ?></span>
                                    <span class="plan-detail-value"><?php echo $c['val_upgrade']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="risk-warning">
            <?php echo $c['risk_disclaimer']; ?>
        </div>
    </section>

    <section class="calc-section">
        <h2><?php echo $c['calc_title']; ?></h2>
        <p class="page-desc" style="max-width: 800px; margin: 0 auto 25px;"><?php echo $c['calc_sub']; ?></p>
        
        <div class="calc-container">
            <div class="card-calc">
                <label><?php echo $c['calc_cap']; ?></label>
                <input type="number" id="inp-cap" placeholder="1000">
                
                <label><?php echo $c['calc_type']; ?></label>
                <select id="inp-mode">
                    <option value="monthly"><?php echo $c['opt_month']; ?></option>
                    <option value="daily"><?php echo $c['opt_daily']; ?></option>
                    <option value="cumulative"><?php echo $c['opt_cum']; ?></option>
                </select>
                
                <button type="button" class="btn-calc" onclick="calculateProfit()"><?php echo $c['btn_calc']; ?></button>
            </div>
            
            <div class="card-calc">
                <h3 style="margin-top:0; color:var(--gold); text-align:center; font-size:15px; margin-bottom:15px;"><?php echo $c['res_title']; ?></h3>
                <div id="out-res" class="result-box">
                    <span style="color:#aaa; font-size:12px;"><?php echo $c['res_wait']; ?></span>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    const isAr = "<?php echo $lang; ?>" === "ar";
    const texts = <?php echo json_encode($c); ?>;
    const plansData = <?php echo json_encode($processed_plans); ?>;

    function formatNum(n) { return Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function toArabicDigits(str) { 
        if(!isAr) return str;
        return str.toString().replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]).replace(/\./g, '٫'); 
    }

    function calculateProfit() {
        let amountVal = document.getElementById('inp-cap').value;
        const amount = parseFloat(amountVal);
        const mode = document.getElementById('inp-mode').value;
        const out = document.getElementById('out-res');

        if(!amountVal || isNaN(amount) || amount <= 0) {
            out.innerHTML = `<span style="color:#ef4444; font-size:14px;">${texts.err_amount}</span>`;
            return;
        }

        let plan = null;
        for(let p of plansData) {
            let min = parseFloat(p.min);
            let max = parseFloat(p.max);
            if(max === -1) { if (amount >= min) { plan = p; break; } } 
            else { if (amount >= min && amount <= max) { plan = p; break; } }
        }

        if(!plan) {
            out.innerHTML = `<span style="color:#ef4444; font-size:14px;">${texts.err_plan}</span>`;
            return;
        }

        let profit = 0;
        let percentageUsed = 0;

        if(mode === 'monthly') {
            percentageUsed = parseFloat(plan.month);
            profit = amount * (percentageUsed / 100);
        } else if (mode === 'daily') {
            percentageUsed = parseFloat(plan.daily);
            profit = amount * (percentageUsed / 100);
        } else { // cumulative
            percentageUsed = parseFloat(plan.annual_comp);
            profit = amount * (percentageUsed / 100);
        }

        profit = parseFloat(profit.toFixed(2));
        let total = parseFloat((amount + profit).toFixed(2));

        // 🔴 تم حذف USDT من النتيجة هنا أيضاً لتكون الأرقام صافية
        out.innerHTML = `
            <div style="margin-bottom:15px; border-bottom:1px solid #444; padding-bottom:10px;">
                <span style="color:#aaa; font-size:12px;">${texts.lbl_plan}</span> 
                <b style="color:var(--gold); margin-left:5px;">${plan.name}</b>
            </div>
            <div style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px;">
                <div style="font-size:11px; color:#aaa;">${texts.lbl_profit} (${toArabicDigits(formatNum(percentageUsed))}%)</div>
                <span class="res-val">+${toArabicDigits(formatNum(profit))}</span>
            </div>
            <div style="margin-top:15px; font-size:13px; color:#fff;">
                <span>${texts.lbl_total}</span> 
                <b style="color:#fff; margin-left:5px;">${toArabicDigits(formatNum(total))}</b>
            </div>
        `;
    }
</script>

<?php include 'footer.php'; ?>