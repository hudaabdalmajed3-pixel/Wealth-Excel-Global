<?php
// certificates.php - النسخة المحدثة مع زر مصغر موسط
require 'header.php'; 

$page_txt = [
    'en' => [
        'title' => 'Licenses & Official Certificates',
        'sub' => 'Verified documentation for our global financial operations',
        'btn_view' => 'View Full Certificate',
        'no_data' => 'No certificates found currently.'
    ],
    'ar' => [
        'title' => 'التراخيص والشهادات الرسمية',
        'sub' => 'وثائق معتمدة لعملياتنا المالية العالمية',
        'btn_view' => 'عرض الشهادة كاملة',
        'no_data' => 'لا توجد شهادات حالياً.'
    ]
];
$t = $page_txt[$lang];

$manual_certs_data = [
    '1' => ['en' => 'Official Compliance & Registration Certificate', 'ar' => 'شهادة المطابقة والتسجيل الرسمية'],
    '2' => ['en' => 'Financial Entity Verification Certificate', 'ar' => 'شهادة التحقق من الكيان المالي'],
    '3' => ['en' => 'Certificate of Compliance', 'ar' => 'شهادة المطابقة'],
    '4' => ['en' => 'Certificate of Authorization', 'ar' => 'شهادة ترخيص']
];

$certificates = [];
foreach ($manual_certs_data as $file_id => $titles) {
    $path = '';
    if (file_exists("uploads/certs/$file_id.jpg")) $path = "uploads/certs/$file_id.jpg";
    elseif (file_exists("uploads/certs/$file_id.png")) $path = "uploads/certs/$file_id.png";
    elseif (file_exists("uploads/certs/$file_id.jpeg")) $path = "uploads/certs/$file_id.jpeg";

    if ($path != '') {
        $certificates[] = [
            'title' => ($lang == 'ar') ? $titles['ar'] : $titles['en'],
            'file' => $path
        ];
    }
}
?>

<style>
    .page-head { text-align: center; margin-bottom: 50px; margin-top: 30px; }
    .page-head h1 { color: var(--gold); font-size: 2.2rem; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
    .page-head p { color: var(--text-muted); font-size: 1.1rem; }

    .cert-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
        gap: 30px; 
    }
    
    .cert-card { 
        background: var(--bg-card); 
        border: 1px solid var(--border); 
        border-radius: 16px; 
        padding: 20px; 
        text-align: center; 
        transition: all 0.4s ease;
        display: flex; flex-direction: column;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .cert-card:hover { 
        border-color: var(--gold); 
        transform: translateY(-5px); 
        box-shadow: 0 15px 40px rgba(212, 175, 55, 0.1);
    }
    
    .cert-img-container {
        width: 100%; height: 350px; 
        background-color: #080808; 
        border-radius: 10px; margin-bottom: 20px;
        border: 1px solid #222; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
    }

    .cert-img {
        max-width: 100%; max-height: 100%;
        object-fit: contain; 
        transition: transform 0.5s ease;
    }

    .cert-card:hover .cert-img { transform: scale(1.05); }

    .cert-name { 
        font-weight: 700; font-size: 1.1rem; margin-bottom: 15px; 
        display: block; color: #fff; min-height: 50px; 
        display: flex; align-items: center; justify-content: center;
    }
    
    /* 🔥 تعديل الزر ليكون مصغراً وموسطاً 🔥 */
    .btn-view {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        width: fit-content; /* جعل العرض مناسباً للنص فقط */
        margin: 0 auto;    /* توسيط الزر في منتصف البطاقة */
        padding: 8px 20px;  /* تقليل الحشو (Padding) */
        background: linear-gradient(135deg, #d4af37, #b8860b);
        color: #000; font-weight: 700; border: none; border-radius: 50px; /* جعل الحواف دائرية أكثر */
        text-decoration: none; 
        font-size: 12px;    /* تصغير حجم الخط */
        text-transform: uppercase;
        transition: 0.3s;
    }
    .btn-view:hover { filter: brightness(1.2); transform: scale(1.05); }
    
    .empty-msg { text-align: center; color: var(--text-muted); padding: 50px; grid-column: 1 / -1; border: 1px dashed var(--border); border-radius: 10px; }
</style>

<div class="container">
    <div class="page-head">
        <h1><?php echo $t['title']; ?></h1>
        <p><?php echo $t['sub']; ?></p>
    </div>

    <div class="cert-grid">
        <?php if (!empty($certificates)): ?>
            <?php foreach ($certificates as $cert): ?>
                <div class="cert-card">
                    <div class="cert-img-container">
                        <img src="<?php echo htmlspecialchars($cert['file']); ?>" class="cert-img" alt="Certificate">
                    </div>

                    <span class="cert-name">
                        <?php echo $cert['title']; ?>
                    </span>

                    <a href="<?php echo htmlspecialchars($cert['file']); ?>" target="_blank" class="btn-view">
                        <i class="fa-solid fa-expand"></i> <?php echo $t['btn_view']; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-msg">
                <?php echo $t['no_data']; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>