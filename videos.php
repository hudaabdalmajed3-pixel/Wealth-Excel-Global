<?php
// videos.php - النسخة النهائية المصلحة لتشغيل الفيديو
require 'header.php'; 

// 1. نصوص الصفحة الثابتة
// تم التعديل هنا: تغيير Wealth Xcel إلى Wealth Excel في الوصف
$page_txt = [
    'en' => [
        'hero_t' => 'Video Library',
        'hero_d' => 'Comprehensive guides to master the Wealth Excel platform.',
        'watch'  => 'Must Watch',
        'ep_num' => '#'
    ],
    'ar' => [
        'hero_t' => 'مكتبة الفيديو',
        'hero_d' => 'أدلة شاملة لاحتراف استخدام منصة Wealth Excel.',
        'watch'  => 'مهم جداً',
        'ep_num' => 'حلقة '
    ]
];
$t = $page_txt[$lang];

// 2. بيانات الفيديوهات
// تم حذف الحلقات 3، 4، 5، و 6 من المصفوفة.
// تم تعديل الحلقة 8 والحلقة 9 وتحديث النصوص بناءً على طلبك.
$videos_data = [
    // الحلقة 9 - تم تعديل العنوان والوصف
    ['id'=>9, 'file'=>'9', 'featured'=>true, 
     'en'=>['t'=>'How to Register in Wealth Excel', 'd'=>'Step-by-step guide to open your account, explore the platform, and start your journey with Wealth Excel easily and clearly.'],
     'ar'=>['t'=>'طريقة التسجيل في Wealth Excel', 'd'=>'خطوة بخطوة لفتح حسابك والتعرّف على المنصة وبدء رحلتك مع Wealth Excel بسهولة ووضوح.']
    ],
    // الحلقة 1
    ['id'=>1, 'file'=>'1', 'featured'=>false,
     'en'=>['t'=>'Platform Basics', 'd'=>'Introduction to the platform interface.'],
     'ar'=>['t'=>'أساسيات المنصة', 'd'=>'مقدمة سريعة حول واجهة الاستخدام.']
    ],
    // الحلقة 2
    ['id'=>2, 'file'=>'2', 'featured'=>false,
     'en'=>['t'=>'Registration Guide', 'd'=>'Step-by-step account creation guide.'],
     'ar'=>['t'=>'دليل التسجيل', 'd'=>'خطوات إنشاء وتفعيل حسابك الاستثماري.']
    ],
    // الحلقة 7
    ['id'=>7, 'file'=>'7', 'featured'=>false,
     'en'=>['t'=>'Trading Tools', 'd'=>'How to use our analytical tools.'],
     'ar'=>['t'=>'أدوات التداول', 'd'=>'كيفية استخدام أدوات التحليل والمتابعة.']
    ],
    // الحلقة 8 - تم تعديل العنوان والوصف
    ['id'=>8, 'file'=>'8', 'featured'=>false,
     'en'=>['t'=>'Activating Service in Wealth Excel', 'd'=>'Learn the steps to activate the service and the available options clearly and simply.'],
     'ar'=>['t'=>'تفعيل الخدمة في Wealth Excel', 'd'=>'تعرّف على خطوات تفعيل الخدمة والخيارات المتاحة بطريقة واضحة ومبسطة.']
    ]
];

function n($num) {
    global $lang;
    if($lang == 'ar') return str_replace(range(0,9), ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], $num);
    return $num;
}
?>

<style>
    .hero { text-align: center; padding: 40px 0; border-bottom: 1px solid var(--border); margin-bottom: 30px; }
    .hero h1 { font-size: 32px; color: white; margin: 0; }
    .hero h1 span { color: var(--gold); }
    .hero p { color: var(--text-muted); font-size: 15px; margin-top: 10px; }

    .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; padding-bottom: 50px; }
    
    .video-card { 
        background: var(--bg-card); border: 1px solid var(--border); 
        border-radius: 12px; overflow: hidden; transition: 0.3s; position: relative; 
    }
    .video-card:hover { transform: translateY(-5px); border-color: var(--gold); }

    .featured-badge { 
        position: absolute; top: 10px; right: 10px; z-index: 10; 
        background: var(--gold); color: #000; padding: 4px 8px; 
        border-radius: 4px; font-size: 11px; font-weight: bold; 
    }
    body[dir="rtl"] .featured-badge { right: auto; left: 10px; }

    /* تحسين مشغل الفيديو */
    .video-wrapper { 
        width: 100%; position: relative; padding-top: 56.25%; background: #000; 
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .video-wrapper video { 
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
        object-fit: contain; /* يضمن ظهور الفيديو كاملاً دون قص */
    }

    .card-body { padding: 15px; }
    .video-num { color: var(--gold); font-size: 12px; font-weight: bold; margin-bottom: 5px; }
    .video-title { font-size: 15px; font-weight: 700; color: white; margin-bottom: 5px; }
    .video-desc { font-size: 13px; color: var(--text-muted); line-height: 1.4; }
</style>

<div class="container">

    <section class="hero">
        <!-- تم التعديل هنا: تغيير Wealth Xcel إلى Wealth Excel في العنوان الرئيسي -->
        <h1><?php echo $t['hero_t']; ?> <span>Wealth Excel</span></h1>
        <p><?php echo $t['hero_d']; ?></p>
    </section>

    <div class="video-grid">
        <?php 
        $count = 1; 
        foreach($videos_data as $v): 
            $info = ($lang == 'ar') ? $v['ar'] : $v['en'];
            
            // الحل البرمجي: استخدام _ar و _en لتجنب مشاكل الحروف العربية في الروابط
            $suffix = ($lang == 'ar') ? '_ar' : '_en';
            $filename = "uploads/" . $v['file'] . $suffix . ".mp4"; 
            
            $poster = "uploads/poster_" . $lang . ".jpg";
        ?>
            <div class="video-card">
                <?php if($v['featured']): ?>
                    <div class="featured-badge">★ <?php echo $t['watch']; ?></div>
                <?php endif; ?>
                
                <div class="video-wrapper">
                    <video controls playsinline preload="metadata" poster="<?php echo $poster; ?>">
                        <source src="<?php echo $filename; ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
                
                <div class="card-body">
                    <div class="video-num"><?php echo $t['ep_num']; ?><?php echo n($count++); ?></div>
                    <div class="video-title"><?php echo $info['t']; ?></div>
                    <div class="video-desc"><?php echo $info['d']; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

</body>
</html>