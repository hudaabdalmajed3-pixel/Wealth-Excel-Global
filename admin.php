<?php
// admin.php - النسخة النهائية الشاملة 🌟 + التجاوب مع الهواتف + عرض بيانات التسجيل (KYC) + خاصية التحديد
require 'config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// منع الكاش نهائياً لضمان تحديث اللغة فوراً
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// القاموس الشامل للأدمن
$admin_trans = [
    'en' => [
        'panel_title' => 'Admin Panel',
        'dashboard'=>'Dashboard', 'deposits'=>'Investments / Deposits',
        'withdrawals'=>'Withdrawals', 'investors'=>'Investors', 'referrals'=>'Commissions',
        'profit'=>'Profit Report', 'plans'=>'Plans', 'settings'=>'Settings', 'loading'=>'Loading...',

        'media_learning' => 'Media & Learning',
        'academy' => 'Academy (PDFs)', 'videos' => 'Videos',
        'pdf_title' => 'PDF Title', 'pdf_desc' => 'Description', 'add_pdf' => 'Upload PDF File',
        'vid_title' => 'Video Title', 'vid_link' => 'Link', 'vid_file' => 'File', 'vid_desc' => 'Description',
        'add_vid' => 'Add Video',
        'type_link'=>'YouTube Link', 'type_file'=>'Upload File',

        'inv_log' => 'Deposit & Investment Logs',
        'inv_user' => 'Investor', 'inv_plan' => 'Plan', 'inv_amount' => 'Amount',
        'inv_wallet' => 'Wallet', 'inv_time' => 'Date', 'inv_status' => 'Status',

        'l1_ratio'=>'L1 Reward %', 'l2_ratio'=>'L2 Reward %',
        'investor'=>'Source', 'l1_ref'=>'Direct (L1)', 'l2_ref'=>'Indirect (L2)',
        'capital'=>'Capital', 'weekly_profit'=>'Weekly ROI', 'commission'=>'Comm.',
        'distribute'=>'Pay Reward', 'skip'=>'Skip',

        'refresh'=>'Refresh', 'pay'=>'PAY', 'reset'=>'Reset', 'cancel'=>'Cancel', 'fee'=>'Fee',
        'approve' => 'Approve', 'reject' => 'Reject', 'txid' => 'TXID',
        'no_data'=>'No Data Available',
        'id' => 'ID', 'net_fee' => 'Net (Fee)',
        'date'=>'Date', 'user'=>'User', 'amount'=>'Amount', 'status'=>'Status', 'action'=>'Action',
        'save'=>'Save', 'edit'=>'Edit', 'delete'=>'Delete', 'add'=>'Add',
        'wallet'=>'Wallet', 'notes'=>'Notes',
        'active_users' => 'Active Users', 'total_dep' => 'Total Investments',
        'referrer' => 'Referrer',
        'from_date' => 'From Date', 'to_date' => 'To Date',

        'col_plan' => 'Package Name', 'col_range' => 'Deposit Range (USD)',
        'col_mon' => 'Monthly %', 'col_simp' => 'Annual (Simple)', 'col_comp' => 'Annual (Compounded)',
        'min_deposit' => 'Min Deposit ($)', 'max_deposit' => 'Max Deposit (-1 = unlimited)',
        'max_deposit_modal' => 'Max Deposit ($) [-1 = unlimited]',

        'manage_inv' => 'Manage Investor', 'admin_notes' => 'Admin Notes:',
        'send_notif' => 'Send Notification:', 'send_msg' => 'Send Message',
        'write_msg_here' => 'Write message to investor here...',
        'danger_zone' => 'Danger Zone', 'freeze_acc' => 'Freeze Account', 'unfreeze_acc' => 'Unfreeze Account',
        'delete_acc' => 'Delete Account', 'save_changes' => 'Save Changes',
        
        'search_inv' => 'Search by Name, Email, or Invite Code (ID)...',
        'total_inv_capital' => 'Total Investors Capital:',

        // KYC Translations
        'reg_details' => 'Registration Details (KYC)',
        'selfie_img' => 'Selfie Image',
        'id_front' => 'ID Front',
        'id_back' => 'ID Back',
        'region' => 'Country / Region',
        'no_image' => 'No Image Available',

        // إعدادات الأدمن والمحفظة
        'central_wallet' => 'Central Deposit Wallet (TRC20)',
        'admin_profile' => 'Admin Security Profile',
        'email' => 'Email',
        'new_password' => 'New Password (leave blank to keep current)',
        'confirm_password' => 'Confirm New Password',
        'update_profile' => 'Update Security Details',
        'pwd_mismatch' => 'Passwords do not match!',

        'copied' => 'Copied!', 'confirm_action' => 'Confirm action?', 'done' => 'Done successfully',
        'empty_msg' => 'Message is empty!', 'msg_sent' => 'Message Sent',
        'confirm_freeze' => 'Freeze this account?', 'acc_frozen' => 'Account Frozen', 'acc_unfrozen' => 'Account Unfrozen',
        'confirm_delete_inv' => 'Delete this investor?', 'acc_deleted' => 'Account Deleted', 
        'confirm_pay' => 'Confirm payment?', 'paid' => 'Processed',
        'saved' => 'Saved', 'saved_successfully' => 'Saved Successfully',
        'added' => 'Added', 'added_successfully' => 'Added Successfully',
        'updated' => 'Updated', 'confirm_delete' => 'Confirm delete?', 'deleted' => 'Deleted',
        'uploading' => 'Uploading...'
    ],
    'ar' => [
        'panel_title' => 'لوحة الإدارة',
        'dashboard'=>'الرئيسية', 'deposits'=>'الإيداعات والاستثمارات',
        'withdrawals'=>'السحوبات', 'investors'=>'المستثمرين', 'referrals'=>'العمولات',
        'profit'=>'تقارير الأرباح', 'plans'=>'الباقات', 'settings'=>'الإعدادات', 'loading'=>'جاري التحميل...',

        'media_learning' => 'الميديا والتعليم',
        'academy' => 'الأكاديمية', 'videos' => 'الفيديوهات',
        'pdf_title' => 'عنوان الملف', 'pdf_desc' => 'الوصف', 'add_pdf' => 'رفع ملف PDF',
        'vid_title' => 'العنوان', 'vid_link' => 'رابط', 'vid_file' => 'ملف', 'vid_desc' => 'الوصف',
        'add_vid' => 'إضافة فيديو',
        'type_link'=>'رابط يوتيوب', 'type_file'=>'رفع من الجهاز',

        'inv_log' => 'سجل الإيداعات والاستثمارات',
        'inv_user' => 'المستثمر', 'inv_plan' => 'الباقة', 'inv_amount' => 'المبلغ',
        'inv_wallet' => 'المحفظة', 'inv_time' => 'التاريخ', 'inv_status' => 'الحالة',

        'l1_ratio'=>'نسبة L1 %', 'l2_ratio'=>'نسبة L2 %',
        'investor'=>'المستثمر', 'l1_ref'=>'مباشر (L1)', 'l2_ref'=>'غير مباشر (L2)',
        'capital'=>'رأس المال', 'weekly_profit'=>'ربح أسبوعي', 'commission'=>'العمولة',
        'distribute'=>'صرف', 'skip'=>'تخطـي',

        'refresh'=>'تحديث', 'pay'=>'دفع', 'reset'=>'إرجاع', 'cancel'=>'إلغاء', 'fee'=>'رسوم',
        'approve' => 'موافقة', 'reject' => 'رفض', 'txid' => 'معرف المعاملة (TXID)',
        'no_data'=>'لا توجد بيانات',
        'id' => 'المعرف', 'net_fee' => 'الصافي (الرسوم)',
        'date'=>'التاريخ', 'user'=>'المستخدم', 'amount'=>'المبلغ', 'status'=>'الحالة', 'action'=>'إجراء',
        'save'=>'حفظ', 'edit'=>'تعديل', 'delete'=>'حذف', 'add'=>'إضافة',
        'wallet'=>'المحفظة', 'notes'=>'ملاحظات',
        'active_users' => 'الأعضاء النشطين', 'total_dep' => 'إجمالي الاستثمارات',
        'referrer' => 'المحيل',
        'from_date' => 'من تاريخ', 'to_date' => 'إلى تاريخ',

        'col_plan' => 'اسم الباقة', 'col_range' => 'نطاق الإيداع ($)',
        'col_mon' => 'شهرياً %', 'col_simp' => 'سنوياً (بسيط)', 'col_comp' => 'سنوياً (تراكمي)',
        'min_deposit' => 'الحد الأدنى للإيداع ($)', 'max_deposit' => 'الحد الأقصى (-1 = غير محدود)',
        'max_deposit_modal' => 'الحد الأقصى للإيداع ($) [-1 = غير محدود]',

        'manage_inv' => 'إدارة المستثمر', 'admin_notes' => 'ملاحظات الإدارة:',
        'send_notif' => 'إرسال إشعار:', 'send_msg' => 'إرسال الرسالة',
        'write_msg_here' => 'اكتب رسالتك للمستثمر هنا...',
        'danger_zone' => 'منطقة الخطر', 'freeze_acc' => 'تجميد الحساب', 'unfreeze_acc' => 'فك التجميد',
        'delete_acc' => 'حذف الحساب نهائياً', 'save_changes' => 'حفظ التغييرات',
        
        'search_inv' => 'بحث بالاسم، البريد الإلكتروني، أو كود الدعوة...',
        'total_inv_capital' => 'إجمالي استثمارات الأعضاء:',

        // KYC Translations
        'reg_details' => 'بيانات التسجيل (KYC)',
        'selfie_img' => 'صورة السيلفي',
        'id_front' => 'البطاقة (أمام)',
        'id_back' => 'البطاقة (خلف)',
        'region' => 'الدولة',
        'email' => 'البريد الإلكتروني',
        'no_image' => 'لا توجد صورة',

        // إعدادات الأدمن والمحفظة
        'central_wallet' => 'محفظة الإيداع المركزية (TRC20)',
        'admin_profile' => 'بيانات الدخول للإدارة',
        'new_password' => 'كلمة المرور الجديدة (اتركه فارغاً لعدم التغيير)',
        'confirm_password' => 'تأكيد كلمة المرور الجديدة',
        'update_profile' => 'تحديث بيانات الدخول',
        'pwd_mismatch' => 'كلمات المرور غير متطابقة!',

        'copied' => 'تم النسخ!', 'confirm_action' => 'هل أنت متأكد من هذا الإجراء؟', 'done' => 'تمت العملية بنجاح',
        'empty_msg' => 'الرسالة فارغة!', 'msg_sent' => 'تم إرسال الرسالة',
        'confirm_freeze' => 'هل تريد تجميد هذا الحساب؟', 'acc_frozen' => 'تم تجميد الحساب', 'acc_unfrozen' => 'تم فك التجميد',
        'confirm_delete_inv' => 'تحذير: هل أنت متأكد من حذف هذا المستثمر نهائياً؟', 'acc_deleted' => 'تم حذف الحساب', 
        'confirm_pay' => 'تأكيد العملية؟', 'paid' => 'تمت المعالجة',
        'saved' => 'تم الحفظ', 'saved_successfully' => 'تم الحفظ بنجاح',
        'added' => 'تمت الإضافة', 'added_successfully' => 'تمت الإضافة بنجاح',
        'updated' => 'تم التحديث', 'confirm_delete' => 'هل أنت متأكد من الحذف؟', 'deleted' => 'تم الحذف',
        'uploading' => 'جاري الرفع...'
    ]
];

// دالة الترجمة تقرأ اللغة مباشرة من الجلسة
function admin_t($key) {
    global $admin_trans;
    $l = isset($_SESSION['lang']) ? strtolower($_SESSION['lang']) : 'en';
    return $admin_trans[$l][$key] ?? $key;
}

$depositAddr = ''; $l1_pct = 15; $l2_pct = 5;
try {
    $depositAddr = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='deposit_wallet'")->fetchColumn();
    $l1_db = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ref_l1_pct'")->fetchColumn();
    $l2_db = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ref_l2_pct'")->fetchColumn();
    if($l1_db) $l1_pct = $l1_db; if($l2_db) $l2_pct = $l2_db;
} catch(Exception $e){}

include 'header.php';

// نحدد اللغة الحالية للملف لتغيير الاتجاه
$current_lang = $_SESSION['lang'] ?? 'en';
?>

<style>
:root { --gold: #d4af37; --bg: #020617; --card: #1e293b; --text: #fff; --nav-width: 260px; --danger: #ef4444; }

/* 🔴 تفعيل خاصية التحديد بشكل كامل لجميع العناصر 🔴 */
body { margin: 0; background: var(--bg); color: var(--text); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; 
    -webkit-user-select: text !important;
    -moz-user-select: text !important;
    -ms-user-select: text !important;
    user-select: text !important;
}

* {
    -webkit-user-select: text !important;
    -moz-user-select: text !important;
    -ms-user-select: text !important;
    user-select: text !important;
}

.sidebar {
    width: var(--nav-width); background: #0f172a; border-right: 1px solid #334155;
    display: flex; flex-direction: column;
    position: fixed; top: 0; bottom: 0;
    <?php echo ($current_lang == 'ar') ? 'right: 0; border-left: 1px solid #334155; border-right: none;' : 'left: 0;'; ?>
    z-index: 1000; overflow-y: auto; padding-top: 10px;
}

.main-content {
    padding: 30px;
    <?php echo ($current_lang == 'ar') ? 'margin-right: var(--nav-width); margin-left: 0;' : 'margin-left: var(--nav-width); margin-right: 0;'; ?>
    min-height: 100vh;
}

.nav-item { padding: 12px 20px; color: #94a3b8; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: 0.2s; font-size: 14px; border-left: 3px solid transparent; border-bottom: 1px solid rgba(255,255,255,0.03); }
.nav-item:hover, .nav-item.active { background: rgba(212,175,55,0.08); color: var(--gold); border-left-color: var(--gold); }
.nav-title { font-size: 11px; text-transform: uppercase; color: #64748b; padding: 15px 20px 5px; font-weight: bold; background: #0b1120; }
body[dir="rtl"] .nav-item { border-left: none; border-right: 3px solid transparent; }
body[dir="rtl"] .nav-item:hover, body[dir="rtl"] .nav-item.active { border-right-color: var(--gold); }

.section { display: none; }
.section.active { display: block; animation: fadeIn 0.3s; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.card { background: var(--card); border: 1px solid #334155; border-radius: 12px; padding: 20px; margin-bottom: 20px; overflow: hidden;}
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #334155; font-size: 13px; color: #fff; }
body[dir="rtl"] th, body[dir="rtl"] td { text-align: right; }
th { color: var(--gold); font-size: 11px; text-transform: uppercase; }

input, select, textarea { background: #020617; border: 1px solid #334155; color: white; padding: 10px; border-radius: 6px; width: 100%; outline: none; margin-bottom: 8px; box-sizing: border-box; }
input:focus { border-color: var(--gold); }

.btn { padding: 8px 15px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer; font-size: 12px; display:inline-flex; align-items:center; justify-content:center; gap:5px; transition: 0.2s; }
.btn:hover { opacity: 0.8; }
.btn-gold { background: var(--gold); color: black; }
.btn-action { background: transparent; border: 1px solid #555; color: #ccc; }
.btn-danger { background: rgba(239,68,68,0.2); color: var(--danger); border: 1px solid var(--danger); }
.btn-success { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
.btn-reset { background: transparent; border: 1px solid var(--gold); color: var(--gold); }

.live-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
.live-card { background: var(--card); padding: 15px; border-radius: 10px; border: 1px solid #334155; text-align: center; }
.live-val { font-size: 24px; font-weight: bold; margin-top: 5px; color: white; }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-box { background: var(--card); border: 1px solid var(--gold); width: 95%; max-width: 500px; padding: 25px; border-radius: 12px; position: relative; max-height:90vh; overflow-y:auto; }
.close-modal { position: absolute; top: 15px; right: 20px; font-size: 20px; cursor: pointer; color: #aaa; }
body[dir="rtl"] .close-modal { right: auto; left: 20px; }

.wallet-copy { display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; cursor: pointer; color: var(--gold); border: 1px solid rgba(212,175,55,0.3); transition: 0.2s; }
.wallet-copy:hover { background: rgba(212,175,55,0.2); }

/* ستايلات مخصصة للموبايل */
.mobile-toggle { display: none; background: linear-gradient(135deg, var(--gold), #b8860b); color: #000; border: none; width: 40px; height: 40px; border-radius: 8px; font-size: 18px; cursor: pointer; box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3); margin-bottom: 20px; }
.sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999; backdrop-filter: blur(2px); }

@media (max-width: 768px) {
    .mobile-toggle { display: inline-flex; align-items: center; justify-content: center; }
    
    .sidebar { 
        transition: transform 0.3s ease-in-out; 
        transform: translateX(<?php echo ($current_lang == 'ar') ? '100%' : '-100%'; ?>);
    }
    
    .sidebar.show-mobile { transform: translateX(0); }
    
    .main-content { 
        margin-left: 0 !important; 
        margin-right: 0 !important; 
        padding: 15px; 
    }
}
</style>

<div id="notification-area" style="position:fixed; bottom:20px; right:20px; z-index:9999;"></div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<aside class="sidebar">
    <div style="text-align:center; padding:20px; color:var(--gold); font-weight:bold; border-bottom:1px solid #333;">
        <?php echo admin_t('panel_title'); ?>
    </div>

    <div class="nav-item active" onclick="show('dashboard', this)"><i class="fa-solid fa-chart-pie"></i> <?php echo admin_t('dashboard'); ?></div>
    <div class="nav-item" onclick="show('deposits', this)"><i class="fa-solid fa-list-check"></i> <?php echo admin_t('deposits'); ?></div>
    <div class="nav-item" onclick="show('withdrawals', this)"><i class="fa-solid fa-money-bill-transfer"></i> <?php echo admin_t('withdrawals'); ?></div>
    <div class="nav-item" onclick="show('investors', this)"><i class="fa-solid fa-users"></i> <?php echo admin_t('investors'); ?></div>
    <div class="nav-item" onclick="show('referrals', this)"><i class="fa-solid fa-network-wired"></i> <?php echo admin_t('referrals'); ?></div>
    <div class="nav-item" onclick="show('profit', this)"><i class="fa-solid fa-sack-dollar"></i> <?php echo admin_t('profit'); ?></div>
    <div class="nav-item" onclick="show('plans', this)"><i class="fa-solid fa-list"></i> <?php echo admin_t('plans'); ?></div>

    <div class="nav-title"><?php echo admin_t('media_learning'); ?></div>
    <div class="nav-item" onclick="show('academy_section', this)"><i class="fa-solid fa-graduation-cap"></i> <?php echo admin_t('academy'); ?></div>
    <div class="nav-item" onclick="show('videos', this)"><i class="fa-brands fa-youtube"></i> <?php echo admin_t('videos'); ?></div>

    <div class="nav-item" onclick="show('settings', this)"><i class="fa-solid fa-gear"></i> <?php echo admin_t('settings'); ?></div>
</aside>

<main class="main-content">
    
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fa-solid fa-bars"></i>
    </button>

    <section id="dashboard" class="section active">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('dashboard'); ?></h2>
        <div class="live-grid">
            <div class="live-card">
                <div style="color:#aaa; font-size:12px;"><?php echo admin_t('active_users'); ?></div>
                <div class="live-val" style="color:var(--gold);" id="d-users">0</div>
            </div>
            <div class="live-card">
                <div style="color:#aaa; font-size:12px;"><?php echo admin_t('total_dep'); ?></div>
                <div class="live-val" style="color:#10b981;" id="d-money">$0</div>
            </div>
        </div>
    </section>

    <section id="deposits" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('inv_log'); ?></h2>
        <div class="card">
            <button onclick="loadInvestments()" class="btn btn-gold" style="margin-bottom:15px;"><i class="fa-solid fa-rotate-right"></i> <?php echo admin_t('refresh'); ?></button>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo admin_t('date'); ?></th>
                            <th><?php echo admin_t('inv_user'); ?></th>
                            <th><?php echo admin_t('inv_plan'); ?></th>
                            <th><?php echo admin_t('txid'); ?></th>
                            <th><?php echo admin_t('inv_amount'); ?></th>
                            <th><?php echo admin_t('inv_status'); ?></th>
                            <th><?php echo admin_t('action'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="inv-rows"></tbody>
                </table>
            </div>
        </div>
    </section>

    <section id="withdrawals" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('withdrawals'); ?></h2>
        <div class="card">
            <button onclick="loadWithdrawals()" class="btn btn-gold" style="margin-bottom:15px;"><i class="fa-solid fa-rotate-right"></i> <?php echo admin_t('refresh'); ?></button>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo admin_t('date'); ?></th>
                            <th><?php echo admin_t('user'); ?></th>
                            <th><?php echo admin_t('amount'); ?></th>
                            <th><?php echo admin_t('net_fee'); ?></th>
                            <th><?php echo admin_t('wallet'); ?></th>
                            <th><?php echo admin_t('status'); ?></th>
                            <th><?php echo admin_t('action'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wd-rows"></tbody>
                </table>
            </div>
        </div>
    </section>

    <section id="investors" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('investors'); ?></h2>
        <div class="card">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <div style="color:var(--gold); font-weight:bold; font-size:16px;">
                    <?php echo admin_t('total_inv_capital'); ?> <span id="total-investors-capital" style="color:#10b981;">$0.00</span>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                <input type="text" id="search-investor" placeholder="<?php echo admin_t('search_inv'); ?>" style="flex:1; margin:0;" onkeyup="if(event.key==='Enter') filterInvestors()">
                <button onclick="filterInvestors()" class="btn btn-gold" style="padding:0 20px;"><i class="fa-solid fa-magnifying-glass"></i></button>
                <button onclick="document.getElementById('search-investor').value=''; loadInvestors();" class="btn btn-action" style="padding:0 20px;"><i class="fa-solid fa-rotate-right"></i></button>
            </div>

            <div style="overflow-x:auto;">
                <table><thead><tr><th><?php echo admin_t('id'); ?></th><th><?php echo admin_t('user'); ?></th><th><?php echo admin_t('referrer'); ?></th><th><?php echo admin_t('capital'); ?></th><th><?php echo admin_t('action'); ?></th></tr></thead><tbody id="investors-rows"></tbody></table>
            </div>
        </div>
    </section>

    <section id="referrals" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('referrals'); ?></h2>
        <div class="card">
            <div style="display:flex; gap:15px; margin-bottom:20px; align-items:flex-end;">
                <div style="flex:1;"><label style="color:#aaa;"><?php echo admin_t('l1_ratio'); ?></label><input type="number" id="l1-pct" value="<?php echo $l1_pct; ?>"></div>
                <div style="flex:1;"><label style="color:#aaa;"><?php echo admin_t('l2_ratio'); ?></label><input type="number" id="l2-pct" value="<?php echo $l2_pct; ?>"></div>
                <button onclick="saveRefSettings()" class="btn btn-gold" style="height:42px;"><?php echo admin_t('save'); ?></button>
            </div>
            <button onclick="loadReferrals()" class="btn btn-action" style="margin-bottom:10px;"><?php echo admin_t('refresh'); ?></button>
            <div style="overflow-x:auto;"><table><thead><tr><th><?php echo admin_t('investor'); ?></th><th><?php echo admin_t('l1_ref'); ?></th><th><?php echo admin_t('l2_ref'); ?></th><th><?php echo admin_t('action'); ?></th></tr></thead><tbody id="ref-rows"></tbody></table></div>
        </div>
    </section>

    <section id="profit" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('profit'); ?></h2>
        <div class="card">
            <div style="display:flex; gap:10px; margin-bottom:15px; align-items:flex-end;">
                <div><label><?php echo admin_t('from_date'); ?></label><input type="date" id="p-start" value="<?php echo date('Y-m-01'); ?>"></div>
                <div><label><?php echo admin_t('to_date'); ?></label><input type="date" id="p-end" value="<?php echo date('Y-m-d'); ?>"></div>
                <button onclick="loadProfit()" class="btn btn-gold" style="height:42px;"><?php echo admin_t('refresh'); ?></button>
            </div>
            <div style="overflow-x:auto;"><table><thead><tr><th><?php echo admin_t('user'); ?></th><th><?php echo admin_t('inv_plan'); ?></th><th>ROI %</th><th>Profit Balance</th><th>Withdrawals</th></tr></thead><tbody id="prof-rows"></tbody></table></div>
        </div>
    </section>

    <section id="plans" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('plans'); ?></h2>
        <div class="card">
            <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                <input id="np-name" placeholder="<?php echo admin_t('col_plan'); ?>" style="flex:1;">
                <input id="np-min" type="number" placeholder="<?php echo admin_t('min_deposit'); ?>" style="flex:1;">
                <input id="np-max" type="number" placeholder="<?php echo admin_t('max_deposit'); ?>" style="flex:1;">
                <input id="np-roi" type="number" placeholder="<?php echo admin_t('col_mon'); ?>" style="flex:1;">
                <button onclick="addPlan()" class="btn btn-gold"><?php echo admin_t('add'); ?></button>
            </div>
            <div id="plans-list"></div>
        </div>
    </section>

    <section id="academy_section" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('academy'); ?></h2>
        <div class="card">
            <h4 style="color:var(--gold); margin-top:0;"><?php echo admin_t('add_pdf'); ?></h4>
            <form id="academyForm" style="margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px;">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <input name="title" placeholder="<?php echo admin_t('pdf_title'); ?>" required style="flex:1;">
                    <input name="desc" placeholder="<?php echo admin_t('pdf_desc'); ?>" style="flex:2;">
                    <input type="file" name="pdf_file" accept=".pdf" style="flex:1; padding:5px;" required>
                </div>
                <button type="button" onclick="uploadAcademy()" class="btn btn-gold" style="width:100%; margin-top:10px;"><?php echo admin_t('add'); ?></button>
            </form>
            <div id="academy-list"></div>
        </div>
    </section>

    <section id="videos" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('videos'); ?></h2>
        <div class="card">
            <h4 style="color:var(--gold); margin-top:0;"><?php echo admin_t('add_vid'); ?></h4>
            <form id="videoForm" style="margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px;">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <input name="title" placeholder="<?php echo admin_t('vid_title'); ?>" required style="flex:1;">
                    <input name="desc" placeholder="<?php echo admin_t('vid_desc'); ?>" style="flex:1;">
                    <select name="type" onchange="toggleVid(this.value)" style="width:140px;">
                        <option value="link"><?php echo admin_t('type_link'); ?></option>
                        <option value="file"><?php echo admin_t('type_file'); ?></option>
                    </select>
                </div>
                <div id="vid-link-box" style="margin-top:10px;"><input name="video_link" placeholder="YouTube Link..."></div>
                <div id="vid-file-box" style="margin-top:10px; display:none;"><input type="file" name="video_file" accept="video/*" style="padding:5px;"></div>
                <button type="button" onclick="uploadVideo()" class="btn btn-gold" style="width:100%; margin-top:10px;"><?php echo admin_t('add'); ?></button>
            </form>
            <div id="video-list"></div>
        </div>
    </section>

    <section id="settings" class="section">
        <h2 style="color:white; margin-top:0;"><?php echo admin_t('settings'); ?></h2>
        
        <div class="card" style="max-width:500px; margin-bottom: 25px; border: 1px solid var(--gold);">
            <h4 style="color:var(--gold); margin-top:0;"><i class="fa-solid fa-wallet"></i> <?php echo admin_t('central_wallet'); ?></h4>
            <input id="s-wallet" value="<?php echo htmlspecialchars($depositAddr); ?>" style="border-color:var(--gold); font-family:monospace; font-size:14px; color:#10b981;">
            <button onclick="saveSettings()" class="btn btn-gold" style="width:100%; margin-top:10px;"><i class="fa-solid fa-floppy-disk"></i> <?php echo admin_t('save'); ?></button>
        </div>

        <div class="card" style="max-width:500px; border: 1px solid #3b82f6;">
            <h4 style="color:#3b82f6; margin-top:0;"><i class="fa-solid fa-user-shield"></i> <?php echo admin_t('admin_profile'); ?></h4>
            
            <label style="color:#aaa; font-size:12px;"><?php echo admin_t('email'); ?></label>
            <input id="admin-email" type="email" placeholder="admin@domain.com">

            <label style="color:#aaa; font-size:12px; margin-top:10px; display:block;"><?php echo admin_t('new_password'); ?></label>
            <input id="admin-pwd" type="password" placeholder="********">

            <label style="color:#aaa; font-size:12px; margin-top:10px; display:block;"><?php echo admin_t('confirm_password'); ?></label>
            <input id="admin-pwd-conf" type="password" placeholder="********">

            <button onclick="updateAdminProfile()" class="btn" style="background:#3b82f6; color:#fff; width:100%; margin-top:15px;"><i class="fa-solid fa-shield-halved"></i> <?php echo admin_t('update_profile'); ?></button>
        </div>
    </section>

</main>

<div class="modal-overlay" id="plan-modal">
    <div class="modal-box">
        <span class="close-modal" onclick="closeM('plan-modal')">&times;</span>
        <h3 style="color:var(--gold); margin-top:0;"><?php echo admin_t('edit'); ?></h3>
        <input type="hidden" id="ep-id">
        <label style="color:#aaa; font-size:12px;"><?php echo admin_t('col_plan'); ?></label>
        <input id="ep-name">
        <label style="color:#aaa; font-size:12px;"><?php echo admin_t('min_deposit'); ?></label>
        <input id="ep-min" type="number">
        <label style="color:#aaa; font-size:12px;"><?php echo admin_t('max_deposit_modal'); ?></label>
        <input id="ep-max" type="number">
        <label style="color:#aaa; font-size:12px;"><?php echo admin_t('col_mon'); ?></label>
        <input id="ep-roi" type="number">
        <div style="display:flex; gap:10px; margin-top:15px;">
            <button onclick="savePlan()" class="btn btn-gold" style="flex:1;"><?php echo admin_t('save'); ?></button>
            <button onclick="delPlan()" class="btn btn-danger" style="flex:1;"><?php echo admin_t('delete'); ?></button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-investor-control">
    <div class="modal-box" style="max-width:600px;">
        <span class="close-modal" onclick="closeM('modal-investor-control')">&times;</span>
        <h3 style="color:var(--gold); margin-top:0;"><i class="fa-solid fa-user-gear"></i> <?php echo admin_t('manage_inv'); ?></h3>
        <input type="hidden" id="inv-id">

        <h4 style="color:var(--gold); border-bottom:1px solid #333; padding-bottom:5px; margin-top:10px; font-size:13px;">
            <i class="fa-solid fa-address-card"></i> <?php echo admin_t('reg_details'); ?>
        </h4>
        
        <div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:15px; background:rgba(255,255,255,0.02); padding:15px; border-radius:8px; border:1px solid #334155;">
            
            <div style="flex:1; min-width:120px;">
                <label style="color:#aaa; font-size:11px; margin-top:0;"><?php echo admin_t('user'); ?>:</label>
                <div id="inv-name" style="font-weight:bold; font-size:13px; color:#fff;">-</div>
            </div>
            <div style="flex:1; min-width:140px;">
                <label style="color:#aaa; font-size:11px; margin-top:0;"><?php echo admin_t('email'); ?>:</label>
                <div id="inv-email" style="font-weight:bold; font-size:13px; color:#fff;">-</div>
            </div>
            <div style="flex:1; min-width:120px;">
                <label style="color:#aaa; font-size:11px; margin-top:0;"><?php echo admin_t('region'); ?>:</label>
                <div id="inv-region" style="font-weight:bold; font-size:13px; color:#fff;">-</div>
            </div>
            <div style="flex:1; min-width:120px;">
                <label style="color:#aaa; font-size:11px; margin-top:0;"><?php echo admin_t('referrer'); ?>:</label>
                <div id="inv-referrer-name" style="font-weight:bold; font-size:13px; color:#fff;">-</div>
            </div>

            <div style="width:100%; display:flex; gap:15px; margin-top:15px; overflow-x:auto; padding-bottom:5px; justify-content: center;">
                
                <div style="text-align:center;">
                    <label style="color:#aaa; font-size:11px; display:block; margin-bottom:5px; margin-top:0;"><?php echo admin_t('selfie_img'); ?></label>
                    <img id="inv-selfie" src="" style="width:90px; height:90px; object-fit:cover; border-radius:8px; border:1px solid var(--gold); display:none; cursor:pointer;" onclick="window.open(this.src)">
                    <span id="no-selfie" style="font-size:11px; color:#666;"><?php echo admin_t('no_image'); ?></span>
                </div>

                <div style="text-align:center;">
                    <label style="color:#aaa; font-size:11px; display:block; margin-bottom:5px; margin-top:0;"><?php echo admin_t('id_front'); ?></label>
                    <img id="inv-id-front" src="" style="width:140px; height:90px; object-fit:cover; border-radius:8px; border:1px solid #3b82f6; display:none; cursor:pointer;" onclick="window.open(this.src)">
                    <span id="no-id-front" style="font-size:11px; color:#666;"><?php echo admin_t('no_image'); ?></span>
                </div>

                <div style="text-align:center;">
                    <label style="color:#aaa; font-size:11px; display:block; margin-bottom:5px; margin-top:0;"><?php echo admin_t('id_back'); ?></label>
                    <img id="inv-id-back" src="" style="width:140px; height:90px; object-fit:cover; border-radius:8px; border:1px solid #3b82f6; display:none; cursor:pointer;" onclick="window.open(this.src)">
                    <span id="no-id-back" style="font-size:11px; color:#666;"><?php echo admin_t('no_image'); ?></span>
                </div>

            </div>
        </div>
        <label style="color:#aaa; font-size:12px; margin-top:10px; display:block;"><?php echo admin_t('admin_notes'); ?></label>
        <textarea id="inv-notes" rows="2" style="width:100%; background:#020617; border:1px solid #334155; color:#fff; border-radius:6px; padding:10px; outline:none; resize:vertical;"></textarea>

        <hr style="border-color:#333; margin:15px 0;">

        <label style="color:var(--gold); font-size:12px; display:block; margin-top:0;"><?php echo admin_t('send_notif'); ?></label>
        <textarea id="inv-msg" rows="2" placeholder="<?php echo admin_t('write_msg_here'); ?>" style="width:100%; background:#020617; border:1px solid var(--gold); color:#fff; border-radius:6px; padding:10px; outline:none; margin-bottom:10px; resize:vertical;"></textarea>
        <button onclick="sendInvMsg()" class="btn btn-action" style="width:100%; margin-bottom:10px; border-color:var(--gold); color:var(--gold);"><i class="fa-regular fa-paper-plane"></i> <?php echo admin_t('send_msg'); ?></button>

        <h4 style="color:var(--danger); border-top:1px solid #333; padding-top:15px; margin-top:5px; font-size:13px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo admin_t('danger_zone'); ?></h4>
        <div style="display:flex; gap:10px; margin-bottom: 10px;">
            <button onclick="freezeInv()" class="btn btn-action" style="flex:1; color:orange; border-color:orange;"><i class="fa-regular fa-snowflake"></i> <?php echo admin_t('freeze_acc'); ?></button>
            <button onclick="unfreezeInv()" class="btn btn-success" style="flex:1;"><i class="fa-solid fa-sun"></i> <?php echo admin_t('unfreeze_acc'); ?></button>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="deleteInvestor()" class="btn btn-danger" style="flex:1;"><i class="fa-solid fa-trash-can"></i> <?php echo admin_t('delete_acc'); ?></button>
        </div>

        <button onclick="saveInvData()" class="btn btn-gold" style="width:100%; margin-top:20px; font-size:14px; padding:10px;"><?php echo admin_t('save_changes'); ?></button>
    </div>
</div>

<script>
// --- دالة التحكم بالقائمة الجانبية للموبايل ---
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('show-mobile');
    
    if(sidebar.classList.contains('show-mobile')) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
}

// --- دالة تنسيق الأرقام ---
const IS_AR = ("<?php echo $current_lang; ?>" === 'ar');

function formatNumber(num, isCurrency = false) {
    if (num === null || num === undefined) return '';
    if (typeof num === 'string') num = num.replace(/,/g, '');
    if (isNaN(num)) return '';
    
    let str = Number(num).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2});
    if (isCurrency) str = '$' + str;
    if (IS_AR) {
        const eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        str = str.replace(/\d/g, d => eastern[d]);
    }
    return str;
}

function show(id, el) {
    document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
    el.classList.add('active');

    // إغلاق القائمة في الموبايل بعد اختيار قسم
    if(window.innerWidth <= 768) {
        toggleSidebar();
    }

    if(id=='dashboard') loadDash();
    if(id=='deposits') loadInvestments();
    if(id=='withdrawals') loadWithdrawals();
    if(id=='investors') loadInvestors();
    if(id=='referrals') loadReferrals();
    if(id=='profit') loadProfit();
    if(id=='plans') loadPlans();
    if(id=='academy_section') loadAcademy();
    if(id=='videos') loadVideos();
    if(id=='settings') loadAdminProfile(); 
}

function closeM(id) { document.getElementById(id).style.display='none'; }
function notify(msg) {
    const d = document.getElementById('notification-area');
    d.innerHTML = `<div style="background:#1e293b; color:#fff; padding:15px 20px; border-left:4px solid var(--gold); border-radius:5px; box-shadow:0 5px 15px rgba(0,0,0,0.5);">${msg}</div>`;
    setTimeout(()=>d.innerHTML='', 3000);
}

async function api(act, data={}) {
    const fd = new FormData(); for(let k in data) fd.append(k, data[k]);
    const r = await fetch(`api-admin.php?action=${act}`, {method:'POST', body:fd});
    return r.json();
}

// Dashboard
async function loadDash() {
    const r = await api('get_dashboard_stats');
    if(r.status == 'success') {
        document.getElementById('d-users').innerText = formatNumber(r.data.online_users);
        document.getElementById('d-money').innerText = formatNumber(r.data.total_deposits, true);
    }
}

// 🔵 الاستثمارات / الإيداعات
async function loadInvestments() {
    const tb = document.getElementById('inv-rows');
    tb.innerHTML=`<tr><td colspan="7" style="text-align:center;">${'<?php echo admin_t("loading"); ?>'}</td></tr>`;
    const r = await api('get_investment_logs');
    if(r.status=='success') {
        if(r.data.length === 0) { tb.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#777;"><?php echo admin_t("no_data"); ?></td></tr>`; return; }
        tb.innerHTML = r.data.map(i => {
            const txid = i.txn_id || i.txid || i.transaction_id || '-';
            const shortTxid = txid !== '-' ? txid.substring(0,8) + '...' : '-';
            const copyTxidBtn = txid !== '-' ? `<span class="wallet-copy" onclick="navigator.clipboard.writeText('${txid}'); notify('<?php echo admin_t("copied"); ?>')" title="${txid}">${shortTxid} <i class="fa-regular fa-copy"></i></span>` : `<span style="color:#555;">-</span>`;

            let btns = '';
            if(i.status === 'pending') {
                btns = `<div style="display:flex; gap:5px;">
                    <button onclick="procDep(${i.id}, 'completed')" class="btn btn-success"><i class="fa-solid fa-check"></i> ${'<?php echo admin_t("approve"); ?>'}</button>
                    <button onclick="procDep(${i.id}, 'rejected')" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> ${'<?php echo admin_t("reject"); ?>'}</button>
                </div>`;
            } else {
                btns = `<span style="color:#aaa; font-size:12px;">-</span>`;
            }

           return `<tr>
                <td style="color:#aaa;">${i.created_at ? i.created_at.split(' ')[0] : '-'}</td>
                <td style="font-weight:bold;">${i.username}<br><span style="font-size:10px; color:#888;">رأس ماله: $${formatNumber(i.current_capital)}</span></td>
                <td style="color:var(--gold)">${i.plan_name || '-'}</td>
                <td>${copyTxidBtn}</td>
                <td style="color:#10b981;">${formatNumber(i.amount, true)}</td>
                <td>${i.status}</td>
                <td>${btns}</td>
            </tr>`;
        }).join('');
    }
}
async function procDep(id, st) { 
    if(confirm('<?php echo admin_t("confirm_action"); ?>')) { 
        await api('process_deposit', {id:id, status:st}); 
        notify('<?php echo admin_t("done"); ?>'); 
        loadInvestments(); 
    } 
}

// 🔵 السحوبات
async function loadWithdrawals() {
    const tb = document.getElementById('wd-rows'); tb.innerHTML=`<tr><td colspan="7" style="text-align:center;">...</td></tr>`;
    const r = await api('get_withdrawals');
    if(r.status=='success') {
        tb.innerHTML = r.data.map(w => {
            const fullWallet = w.wallet_address || '';
            const shortWallet = fullWallet ? fullWallet.substring(0,8) + '...' : '-';
            const copyWalletBtn = fullWallet ? `<span class="wallet-copy" onclick="navigator.clipboard.writeText('${fullWallet}'); notify('<?php echo admin_t("copied"); ?>')" title="${fullWallet}">${shortWallet} <i class="fa-regular fa-copy"></i></span>` : `<span style="color:#555;">-</span>`;

            let btns = '';
            if(w.status === 'pending') {
                btns = `<div style="display:flex; gap:5px;">
                    <button onclick="procW(${w.id}, 'approved')" class="btn btn-success"><i class="fa-solid fa-check"></i> ${'<?php echo admin_t("approve"); ?>'}</button>
                    <button onclick="procW(${w.id}, 'rejected')" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> ${'<?php echo admin_t("reject"); ?>'}</button>
                </div>`;
            } else {
                btns = `<span style="color:#aaa; font-size:12px;">-</span>`;
            }

            return `<tr>
                <td style="color:#aaa;">${w.created_at ? w.created_at.split(' ')[0] : '-'}</td>
                <td style="font-weight:bold;">${w.username}</td>
                <td style="color:var(--gold);">${formatNumber(w.amount, true)}</td>
                <td style="color:#10b981">${formatNumber(w.net_amount, true)}</td>
                <td>${copyWalletBtn}</td>
                <td>${w.status}</td>
                <td>${btns}</td>
            </tr>`;
        }).join('');
    }
}
async function procW(id, st) { if(confirm('<?php echo admin_t("confirm_action"); ?>')) { await api('process_withdrawal', {id:id, status:st}); notify('<?php echo admin_t("done"); ?>'); loadWithdrawals(); } }

// 🌟 Investors Management (KYC Data included)
let allInvestors = [];

async function loadInvestors() {
    const tb = document.getElementById('investors-rows');
    tb.innerHTML=`<tr><td colspan="5" style="text-align:center;">${'<?php echo admin_t("loading"); ?>'}</td></tr>`;
    const r = await api('get_investors');
    if(r.status == 'success') {
        allInvestors = r.data;
        renderInvestors(allInvestors);
    }
}

function filterInvestors() {
    const q = document.getElementById('search-investor').value.toLowerCase().trim();
    if(!q) { renderInvestors(allInvestors); return; }
    
    const filtered = allInvestors.filter(u => 
        (u.username && u.username.toLowerCase().includes(q)) || 
        (u.email && u.email.toLowerCase().includes(q)) || 
        (u.id && u.id.toString() === q)
    );
    renderInvestors(filtered);
}

function renderInvestors(data) {
    const tb = document.getElementById('investors-rows');
    let totalCap = 0;
    
    if(data.length === 0) {
        tb.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#777;">${'<?php echo admin_t("no_data"); ?>'}</td></tr>`;
        document.getElementById('total-investors-capital').innerText = '$0.00';
        return;
    }
    
    tb.innerHTML = data.map(u => {
        totalCap += parseFloat(u.capital || 0);
        return `<tr>
            <td>#${formatNumber(u.id)}</td>
            <td><b style="font-weight:bold">${u.username}</b><br><small style="color:#aaa">${u.email || ''}</small></td>
            <td style="color:#888">${u.referrer_name||'-'}</td>
            <td style="color:var(--gold);">${formatNumber(u.capital, true)}</td>
            <td>
                <button onclick="openInvModal(${u.id})" class="btn btn-action" style="padding:5px 10px; border-color:var(--gold); color:var(--gold);">
                    <i class="fa-solid fa-gear"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
    
    document.getElementById('total-investors-capital').innerText = formatNumber(totalCap, true);
}

function openInvModal(id) {
    const u = allInvestors.find(inv => inv.id == id);
    if(!u) return;

    document.getElementById('inv-id').value = u.id;
    document.getElementById('inv-notes').value = (u.admin_notes !== 'null' && u.admin_notes) ? u.admin_notes : '';
    document.getElementById('inv-msg').value = '';

    // تعبئة البيانات الجديدة
    document.getElementById('inv-name').innerText = u.username || '-';
    document.getElementById('inv-email').innerText = u.email || '-';
    document.getElementById('inv-region').innerText = u.region || '-';
    document.getElementById('inv-referrer-name').innerText = u.referrer_name || '-';

    // إعداد مسارات الصور من قاعدة البيانات (kyc_front, kyc_back, kyc_selfie)
    const setImg = (imgId, spanId, filename) => {
        const img = document.getElementById(imgId);
        const span = document.getElementById(spanId);
        if(filename && filename !== 'null' && filename.trim() !== '') {
            img.src = 'uploads/kyc/' + filename;
            img.style.display = 'inline-block';
            span.style.display = 'none';
        } else {
            img.src = '';
            img.style.display = 'none';
            span.style.display = 'inline-block';
        }
    };

    setImg('inv-selfie', 'no-selfie', u.kyc_selfie);
    setImg('inv-id-front', 'no-id-front', u.kyc_front);
    setImg('inv-id-back', 'no-id-back', u.kyc_back);

    document.getElementById('modal-investor-control').style.display = 'flex';
}

async function saveInvData() { await api('update_user_details', { id: document.getElementById('inv-id').value, notes: document.getElementById('inv-notes').value }); closeM('modal-investor-control'); loadInvestors(); notify('<?php echo admin_t("saved_successfully"); ?>'); }
async function sendInvMsg() { const msg = document.getElementById('inv-msg').value; if(!msg) return alert('<?php echo admin_t("empty_msg"); ?>'); await api('send_message', {user_id: document.getElementById('inv-id').value, message: msg}); document.getElementById('inv-msg').value = ''; notify('<?php echo admin_t("msg_sent"); ?>'); }
async function freezeInv() { if(confirm('<?php echo admin_t("confirm_freeze"); ?>')) { await api('freeze_user', {id: document.getElementById('inv-id').value}); closeM('modal-investor-control'); loadInvestors(); notify('<?php echo admin_t("acc_frozen"); ?>'); } }
async function unfreezeInv() { if(confirm('<?php echo admin_t("confirm_action"); ?>')) { await api('unfreeze_user', {id: document.getElementById('inv-id').value}); closeM('modal-investor-control'); loadInvestors(); notify('<?php echo admin_t("acc_unfrozen"); ?>'); } }
async function deleteInvestor() { if(confirm('<?php echo admin_t("confirm_delete_inv"); ?>')) { await api('delete_user', {id: document.getElementById('inv-id').value}); closeM('modal-investor-control'); loadInvestors(); notify('<?php echo admin_t("acc_deleted"); ?>'); } }

// Referrals & Profit
async function loadReferrals() { const r = await api('get_referral_grid'); if(r.status=='success') document.getElementById('ref-rows').innerHTML = r.data.map(d => `<tr><td><b style="color:var(--gold)">${d.inv_name}</b><br><small>${d.plan_name}</small></td><td>${d.l1_name}<br><small style="color:#10b981">${formatNumber(d.l1_comm, true)}</small></td><td>${d.l2_name||'-'}</td><td><button onclick="distributeReward(${d.inv_id}, ${d.l1_id}, ${d.l2_id||0}, ${d.l1_comm}, ${d.l2_comm})" class="btn btn-success">${'<?php echo admin_t("distribute"); ?>'}</button></td></tr>`).join(''); }
async function distributeReward(inv, l1, l2, l1a, l2a) { if(confirm('<?php echo admin_t("confirm_pay"); ?>')) { await api('distribute_referral_reward', {l1_id:l1, l2_id:l2, l1_amount:l1a, l2_amount:l2a}); notify('<?php echo admin_t("paid"); ?>'); loadReferrals(); } }
async function saveRefSettings() { await api('save_ref_settings', {l1: document.getElementById('l1-pct').value, l2: document.getElementById('l2-pct').value}); notify('<?php echo admin_t("saved"); ?>'); }
async function loadProfit() { const r = await api('get_profit_tracking', {from_date:document.getElementById('p-start').value, to_date:document.getElementById('p-end').value}); if(r.status=='success') document.getElementById('prof-rows').innerHTML = r.data.map(p => `<tr><td>${p.username}</td><td>${p.plan_name||'-'}</td><td style="color:#aaa;">${formatNumber(p.roi_percentage)}%</td><td style="color:#10b981; font-weight:bold;">${formatNumber(p.profit_amount, true)}</td><td style="color:#ef4444">${formatNumber(p.withdrawals, true)}</td></tr>`).join(''); }

// Plans
async function loadPlans() {
    const r = await api('get_plans');
    let html = `<div style="overflow-x:auto; border:1px solid #334155; border-radius:8px;"><table style="width:100%; text-align:center;"><thead style="background:rgba(212,175,55,0.1);"><tr><th style="padding:15px; text-align:left;">${'<?php echo admin_t("col_plan"); ?>'}</th><th>${'<?php echo admin_t("col_range"); ?>'}</th><th>${'<?php echo admin_t("col_mon"); ?>'}</th><th>${'<?php echo admin_t("col_simp"); ?>'}</th><th>${'<?php echo admin_t("col_comp"); ?>'}</th><th>${'<?php echo admin_t("action"); ?>'}</th></tr></thead><tbody>`;
    let sortedPlans = r.data.sort((a, b) => parseFloat(a.min_price) - parseFloat(b.min_price));
    sortedPlans.forEach((p, i) => {
        let min = parseFloat(p.min_price || 0), max = parseFloat(p.max_price || 0);
        if (!p.max_price || p.max_price == 0) max = (i < sortedPlans.length - 1) ? parseFloat(sortedPlans[i+1].min_price) - 1 : -1;
        let mon = parseFloat(p.roi_percentage || 0);
        let displayMonth = formatNumber(mon) + '%', displaySimp = formatNumber(mon * 12) + '%', displayComp = formatNumber(((Math.pow(1 + (mon/100), 12) - 1) * 100).toFixed(1)) + '%';
        let rawName = p.name.toLowerCase();
        if (rawName.includes('basic') || rawName.includes('أساسي')) { displayMonth = formatNumber(17) + '%'; displaySimp = formatNumber(102, true); displayComp = formatNumber(279, true); } 
        else if (rawName.includes('premium') || rawName.includes('متميز')) { displayMonth = formatNumber(20) + '%'; displaySimp = formatNumber(240, true); displayComp = formatNumber(791.61, true); }
        let rangeStr = (min === max || (max === -1 && min <= 100)) ? formatNumber(min, true) : formatNumber(min, true) + ' - ' + (max === -1 ? '+' : formatNumber(max, true));
        html += `<tr><td style="color:var(--gold); font-weight:bold; text-align:left; padding:12px;">${p.name}</td><td style="font-family:monospace;">${rangeStr}</td><td style="color:#d4af37;">${displayMonth}</td><td style="color:#e2e8f0;">${displaySimp}</td><td style="color:#10b981; font-weight:bold;">${displayComp}</td><td><button onclick="editPlan(${p.id}, '${p.name}', ${min}, ${max}, ${mon})" class="btn btn-action" style="padding:4px 10px; font-size:11px;">${'<?php echo admin_t("edit"); ?>'}</button></td></tr>`;
    });
    html += `</tbody></table></div>`;
    document.getElementById('plans-list').innerHTML = html;
}
async function addPlan() { await api('add_plan', { name: document.getElementById('np-name').value, min_price: document.getElementById('np-min').value, max_price: document.getElementById('np-max').value || 0, roi: document.getElementById('np-roi').value }); notify('<?php echo admin_t("added"); ?>'); loadPlans(); }
function editPlan(id, name, min, max, roi) { document.getElementById('ep-id').value = id; document.getElementById('ep-name').value = name; document.getElementById('ep-min').value = min; document.getElementById('ep-max').value = max; document.getElementById('ep-roi').value = roi; document.getElementById('plan-modal').style.display = 'flex'; }
async function savePlan() { await api('update_plan', { id: document.getElementById('ep-id').value, name: document.getElementById('ep-name').value, min_price: document.getElementById('ep-min').value, max_price: document.getElementById('ep-max').value || 0, roi: document.getElementById('ep-roi').value }); closeM('plan-modal'); loadPlans(); notify('<?php echo admin_t("updated"); ?>'); }
async function delPlan() { if(confirm('<?php echo admin_t("confirm_delete"); ?>')) { await api('delete_plan', {id: document.getElementById('ep-id').value}); closeM('plan-modal'); loadPlans(); } }

// Academy, Videos
async function uploadAcademy() { const f = document.getElementById('academyForm'); const fd = new FormData(f); notify('<?php echo admin_t("uploading"); ?>'); const r = await fetch('api-admin.php?action=add_academy', {method:'POST', body:fd}).then(res=>res.json()); if(r.status == 'success') { notify('<?php echo admin_t("added_successfully"); ?>'); f.reset(); loadAcademy(); } else { alert(r.message); } }
async function loadAcademy() { const r = await api('get_academy'); if(r.status == 'success') { document.getElementById('academy-list').innerHTML = r.data.map(a => `<div style="background:#111; padding:15px; border-radius:8px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; border:1px solid #333;"><div><i class="fa-solid fa-file-pdf" style="color:#ef4444; font-size:20px; margin-right:10px; vertical-align:middle;"></i><a href="${a.filename}" target="_blank" style="color:var(--gold); text-decoration:none; font-weight:bold; font-size:14px;">${a.title}</a><div style="font-size:12px; color:#888; margin-top:5px;">${a.description || ''}</div></div><button onclick="delItem('academy', ${a.id})" class="btn btn-danger" style="padding:8px 12px;"><i class="fa-solid fa-trash"></i></button></div>`).join(''); } }

function toggleVid(v) { document.getElementById('vid-link-box').style.display = (v=='link')?'block':'none'; document.getElementById('vid-file-box').style.display = (v=='file')?'block':'none'; }
async function uploadVideo() { const f = document.getElementById('videoForm'); const fd = new FormData(f); notify('<?php echo admin_t("uploading"); ?>'); const r = await fetch('api-admin.php?action=add_video', {method:'POST', body:fd}).then(res=>res.json()); if(r.status=='success') { notify('<?php echo admin_t("added_successfully"); ?>'); f.reset(); loadVideos(); } else alert(r.message); }
async function loadVideos() { const r = await api('get_videos'); document.getElementById('video-list').innerHTML = r.data.map(v => `<div style="background:#111; padding:10px; border-radius:5px; margin-bottom:5px; display:flex; justify-content:space-between; align-items:center;"><span>${v.title}</span><button onclick="delItem('video', ${v.id})" class="btn btn-danger">X</button></div>`).join(''); }

async function delItem(type, id) { if(confirm('<?php echo admin_t("confirm_delete"); ?>')) { await api(`delete_${type}`, {id:id}); if(type=='video') loadVideos(); else loadAcademy(); notify('<?php echo admin_t("deleted"); ?>'); } }

// ==========================================
// 🌟 دوال الإعدادات والملف الشخصي للإدارة 
// ==========================================
async function saveSettings() { 
    await api('save_deposit_settings', {address: document.getElementById('s-wallet').value}); 
    notify('<?php echo admin_t("saved_successfully"); ?>'); 
}

async function loadAdminProfile() {
    const r = await api('get_admin_profile');
    if(r.status === 'success') {
        document.getElementById('admin-email').value = r.data.email;
    }
}

async function updateAdminProfile() {
    const email = document.getElementById('admin-email').value;
    const pwd = document.getElementById('admin-pwd').value;
    const pwdConf = document.getElementById('admin-pwd-conf').value;

    if (!email) return alert('<?php echo admin_t("empty_msg"); ?>');
    if (pwd !== pwdConf) return alert('<?php echo admin_t("pwd_mismatch"); ?>');

    if(confirm('<?php echo admin_t("confirm_action"); ?>')) {
        const r = await api('update_admin_profile', {email: email, password: pwd});
        if(r.status === 'success') {
            notify('<?php echo admin_t("saved_successfully"); ?>');
            document.getElementById('admin-pwd').value = '';
            document.getElementById('admin-pwd-conf').value = '';
        } else {
            alert(r.message || 'Error updating profile');
        }
    }
}

// Init
loadDash();
</script>

<?php include 'footer.php'; ?>