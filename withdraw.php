<?php
// withdraw.php - Sécurisé (CSRF + Prepared Statements) + OTP pour les retraits
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
if (!file_exists('config.php')) { die("Error: config.php not found."); }
require 'config.php';
require_once 'otp_helper.php';

// ========== CSRF TOKEN ==========
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Gestion de la langue
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: withdraw.php");
    exit();
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'ar';
$dir = ($lang == 'ar') ? 'rtl' : 'ltr';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$msg = "";

// Fonctions de formatage
function num($val, $decimals = 0) {
    global $lang;
    $val = number_format((float)$val, $decimals);
    if ($lang == 'ar') {
        $std = ['0','1','2','3','4','5','6','7','8','9'];
        $east = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        return str_replace($std, $east, (string)$val);
    }
    return $val;
}
function money($val) {
    global $lang;
    $val = number_format((float)$val, 2);
    if ($lang == 'ar') {
        $std = ['0','1','2','3','4','5','6','7','8','9'];
        $east = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        return str_replace($std, $east, (string)$val);
    }
    return $val;
}

// Récupération des données utilisateur
$stmt = $pdo->prepare("
    SELECT u.balance, u.profit_balance, u.personal_wallet, u.email, u.created_at as user_date,
    (SELECT created_at FROM auto_deposits WHERE user_id = u.id AND status = 'completed' ORDER BY created_at ASC LIMIT 1) as first_dep_date
    FROM users u WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$start_date = $user_data['first_dep_date'] ? $user_data['first_dep_date'] : $user_data['user_date'];

// Calculs des cycles
$start_date_obj = new DateTime($start_date);
$today_obj = new DateTime();
$days_passed = $today_obj->diff($start_date_obj)->days;

$cycle_number = floor($days_passed / 30) + 1;
$current_day_in_cycle = ($days_passed % 30) + 1;

// Logique des frais 
if ($current_day_in_cycle < 30) {
    $profit_fee_pct = 15.3;
} elseif ($current_day_in_cycle == 30) {
    $profit_fee_pct = 0;
} elseif ($current_day_in_cycle >= 31 && $current_day_in_cycle <= 33) {
    $profit_fee_pct = 0;
} else {
    $profit_fee_pct = 15.3;
}
$capital_fee_pct = 28.4;

// Clawback
$cycle_start_days = ($cycle_number - 1) * 30;
$cycle_start_date = clone $start_date_obj;
$cycle_start_date->modify("+$cycle_start_days days");
$cycle_start_str = $cycle_start_date->format('Y-m-d 00:00:00');

$stmt_cb = $pdo->prepare("SELECT SUM(amount) as total_amt, SUM(fee) as total_fee FROM withdrawals WHERE user_id = ? AND admin_notes LIKE '%سحب أرباح%' AND status != 'rejected' AND status != 'canceled' AND created_at >= ?");
$stmt_cb->execute([$user_id, $cycle_start_str]);
$cb = $stmt_cb->fetch();

$prof_withdrawn_this_cycle = $cb['total_amt'] ? (float)$cb['total_amt'] : 0;
$prof_fees_paid_this_cycle = $cb['total_fee'] ? (float)$cb['total_fee'] : 0;
$profit_clawback = ($prof_withdrawn_this_cycle * ($capital_fee_pct / 100)) - $prof_fees_paid_this_cycle;
if ($profit_clawback < 0) $profit_clawback = 0;

// Textes multilingues 
$t = [
    'ar' => [
        'title' => 'طلب تحويل', 'back' => 'رجوع',
        'profit_sec' => 'الكمية', 'capital_sec' => 'إنهاء الاشتراك الكلي',
        'wallet_lbl' => '⚠️ عنوان الاستلام (شبكة BNB - BEP20)', 'wallet_plc' => 'الصق عنوان محفظتك هنا...',
        'amount' => 'الكمية المراد تحويلها', 'btn_profit' => 'تأكيد التحويل',
        'btn_capital' => 'تأكيد الاسترداد الكلي',
        'net_lbl' => 'الصافي المحوّل:',
        'cap_warn' => 'تنبيه: إنهاء الاشتراك الكلي يترتب عليه رسوم إغلاق ويؤدي لإغلاق الحساب نهائياً.',
        'hist' => 'سجل العمليات',
        'filter_from' => 'من تاريخ', 'filter_to' => 'إلى تاريخ', 'filter_btn' => 'فلترة',
        'reset_btn' => 'إعادة تعيين',
        'cycle_total' => 'إجمالي أيام التشغيل:', 'days_txt' => 'يوم',
        'cycle_curr' => "الدورة الحالية (رقم " . num($cycle_number) . "):",
        'cycle_comp' => "مكتمل " . num($current_day_in_cycle) . " من " . num(30) . " يوم",
        'cap_tot' => 'الإجمالي الكلي:',
        'clawback_lbl' => 'تسوية المعاملات المبكرة:',
        'err_wallet' => 'يجب إدخال عنوان محفظة صحيح.',
        'err_bal' => 'الرصيد غير كافٍ أو المبلغ غير صحيح.',
        'err_min_amount' => 'الحد الأدنى للتحويل هو 5 دولار.',
        'err_cap' => 'لا يوجد اشتراك متاح للاسترداد (أو أقل من 5).',
        'err_pend' => 'يوجد طلب استرداد قيد المعالجة بالفعل.',
        'err_clawback' => '❌ <b>تم رفض الطلب آلياً:</b> مبلغ الاسترداد لا يغطي رسوم التسوية المطلوبة.',
        'err_sys' => 'حدث خطأ في النظام.',
        'err_csrf' => 'رمز الأمان غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى.',
        'err_otp' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
        'succ_prof' => '✅ تم إرسال طلب التحويل بنجاح.',
        'succ_cap' => '✅ تم طلب إنهاء الاشتراك. سيتم التحويل بعد المعالجة.',
        'otp_sent' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
        'js_conf_cap' => 'هل أنت متأكد من إنهاء الاشتراك الكلي وإغلاق الحساب نهائياً؟ سيتم تطبيق رسوم الإغلاق.',
        'th_date' => 'التاريخ', 'th_amount' => 'الكمية', 'th_net' => 'الصافي', 'th_status' => 'الحالة', 'th_action' => 'إجراء'
    ],
    'en' => [
        'title' => 'Transfer Request', 'back' => 'Back',
        'profit_sec' => 'Quantity', 'capital_sec' => 'Terminate Total Subscription',
        'wallet_lbl' => '⚠️ Receiving Address (BNB - BEP20 Network)', 'wallet_plc' => 'Paste your wallet address here...',
        'amount' => 'Amount to transfer', 'btn_profit' => 'Confirm Transfer',
        'btn_capital' => 'Confirm Total Retrieval',
        'net_lbl' => 'Net Transferred:',
        'cap_warn' => 'Warning: Terminating total subscription incurs closing fees and leads to permanent account closure.',
        'hist' => 'Operations Log',
        'filter_from' => 'From Date', 'filter_to' => 'To Date', 'filter_btn' => 'Filter',
        'reset_btn' => 'Reset',
        'cycle_total' => 'Total Operational Days:', 'days_txt' => 'Days',
        'cycle_curr' => "Current Cycle (No. " . num($cycle_number) . "):",
        'cycle_comp' => "Completed " . num($current_day_in_cycle) . " of " . num(30) . " Days",
        'cap_tot' => 'Total Amount:',
        'clawback_lbl' => 'Early Transactions Settlement:',
        'err_wallet' => 'Please enter a valid wallet address.',
        'err_bal' => 'Insufficient balance or invalid amount.',
        'err_min_amount' => 'Minimum transfer amount is 5 USD.',
        'err_cap' => 'No subscription available to retrieve.',
        'err_pend' => 'A termination request is already pending.',
        'err_clawback' => '❌ <b>Auto-Rejected:</b> Retrieval amount does not cover required settlement fees.',
        'err_sys' => 'A system error occurred.',
        'err_csrf' => 'Invalid security token. Please refresh the page and try again.',
        'err_otp' => 'Invalid or expired verification code.',
        'succ_prof' => '✅ Transfer request sent successfully.',
        'succ_cap' => '✅ Subscription termination requested. Will be processed soon.',
        'otp_sent' => 'Verification code sent to your email.',
        'js_conf_cap' => 'Are you sure you want to terminate your total subscription and permanently close the account? Closing fees apply.',
        'th_date' => 'Date', 'th_amount' => 'Quantity', 'th_net' => 'Net', 'th_status' => 'Status', 'th_action' => 'Action'
    ]
];
$txt = $t[$lang];

// ==========================================
// 🔴 1. طلب الـ OTP عبر AJAX (محدث لكشف أخطاء SMTP)
// ==========================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'request_withdraw_otp') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'msg' => $txt['err_csrf']]); exit;
    }
    
    $target_wallet = !empty($_POST['wallet_address']) ? trim(htmlspecialchars($_POST['wallet_address'])) : '';
    $type = isset($_POST['withdraw_profit']) ? 'profit' : (isset($_POST['withdraw_capital']) ? 'capital' : '');
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    if (empty($target_wallet) || strlen($target_wallet) < 10) { echo json_encode(['status' => 'error', 'msg' => $txt['err_wallet']]); exit; }
    
    if ($type == 'profit') {
        if ($amount < 5) { echo json_encode(['status' => 'error', 'msg' => $txt['err_min_amount']]); exit; }
        if ($amount > $user_data['profit_balance']) { echo json_encode(['status' => 'error', 'msg' => $txt['err_bal']]); exit; }
    } elseif ($type == 'capital') {
        $amount = $user_data['balance'];
        if ($amount < 5) { echo json_encode(['status' => 'error', 'msg' => $txt['err_cap']]); exit; }
        $search_term = ($lang=='ar') ? '%رأس المال%' : '%Capital%';
        $chk = $pdo->prepare("SELECT id FROM withdrawals WHERE user_id=? AND status='pending' AND admin_notes LIKE ?");
        $chk->execute([$user_id, $search_term]);
        if ($chk->rowCount() > 0) { echo json_encode(['status' => 'error', 'msg' => $txt['err_pend']]); exit; }
        
        $fee = $amount * ($capital_fee_pct / 100);
        $net_capital = $amount - $fee;
        if (($net_capital - $profit_clawback) <= 0) { echo json_encode(['status' => 'error', 'msg' => $txt['err_clawback']]); exit; }
    }

    $wd_data = ['type' => $type, 'amount' => $amount, 'wallet' => $target_wallet];
    $otp_code = createOtp($pdo, $user_id, 'withdraw', $wd_data);
    
    // 🔴 استقبال نتيجة الإرسال التفصيلية
    $mailStatus = sendOtpEmail($user_data['email'], $otp_code, 'withdraw');
    
    if ($mailStatus === 'success') {
        echo json_encode(['status' => 'otp_required', 'msg' => $txt['otp_sent']]);
    } else {
        // 🔴 طباعة الخطأ القادم من الخادم مباشرة
        echo json_encode(['status' => 'error', 'msg' => "SMTP ERROR: " . $mailStatus]);
    }
    exit;
}

// ==========================================
// 🔴 2. التحقق من الـ OTP وتنفيذ السحب
// ==========================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'verify_withdraw_otp') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) { echo json_encode(['status' => 'error', 'msg' => $txt['err_csrf']]); exit; }
    
    $otp = trim($_POST['otp']);
    $otpData = verifyOtp($pdo, $user_id, $otp, 'withdraw');
    if ($otpData === false) { echo json_encode(['status' => 'error', 'msg' => $txt['err_otp']]); exit; }
    
    $wd = $otpData;
    $target_wallet = $wd['wallet'];
    $amount = floatval($wd['amount']);

    if ($wd['type'] == 'profit') {
        if ($amount > $user_data['profit_balance']) { echo json_encode(['status' => 'error', 'msg' => $txt['err_bal']]); exit; }
        $fee_amount = $amount * ($profit_fee_pct / 100);
        $net_amount = $amount - $fee_amount;
        $note = ($lang=='ar') ? "سحب أرباح (دورة $cycle_number - يوم $current_day_in_cycle - رسوم $profit_fee_pct%)" : "Profit Withdraw (Cycle $cycle_number - Day $current_day_in_cycle - Fee $profit_fee_pct%)";
        
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO withdrawals (user_id, amount, wallet_address, fee, net_amount, status, created_at, admin_notes) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?)")->execute([$user_id, $amount, $target_wallet, $fee_amount, $net_amount, $note]);
            $pdo->prepare("UPDATE users SET profit_balance = profit_balance - ? WHERE id = ?")->execute([$amount, $user_id]);
            if ($target_wallet !== $user_data['personal_wallet']) { $pdo->prepare("UPDATE users SET personal_wallet = ? WHERE id = ?")->execute([$target_wallet, $user_id]); }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'msg' => $txt['succ_prof']]);
        } catch (Exception $e) {
            $pdo->rollBack(); echo json_encode(['status' => 'error', 'msg' => "DB Error: " . $e->getMessage()]);
        }
    } elseif ($wd['type'] == 'capital') {
        $amount = $user_data['balance'];
        $fee = $amount * ($capital_fee_pct / 100);
        $net_capital = $amount - $fee;
        $final_net_payout = $net_capital - $profit_clawback;
        
        if ($final_net_payout <= 0) { echo json_encode(['status' => 'error', 'msg' => $txt['err_clawback']]); exit; }
        
        $note = ($lang=='ar') ? "سحب رأس المال" : "Capital Withdraw";
        if ($profit_clawback > 0) $note .= ($lang=='ar') ? " | تسوية أرباح الدورة: -$profit_clawback" : " | Profit Clawback Settlement: -$profit_clawback";
        
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO withdrawals (user_id, amount, wallet_address, fee, net_amount, status, created_at, admin_notes) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?)")->execute([$user_id, $amount, $target_wallet, $fee + $profit_clawback, $final_net_payout, $note]);
            $pdo->prepare("UPDATE users SET balance = 0 WHERE id = ?")->execute([$user_id]);
            if ($target_wallet !== $user_data['personal_wallet']) { $pdo->prepare("UPDATE users SET personal_wallet = ? WHERE id = ?")->execute([$target_wallet, $user_id]); }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'msg' => $txt['succ_cap']]);
        } catch (Exception $e) {
            $pdo->rollBack(); echo json_encode(['status' => 'error', 'msg' => "DB Error: " . $e->getMessage()]);
        }
    }
    exit;
}

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'cancel_withdrawal') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) { 
        echo json_encode(['status' => 'error', 'msg' => $txt['err_csrf']]); exit; 
    }

    $wd_id = intval($_POST['wd_id']);
    
    $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$wd_id, $user_id]);
    $wd = $stmt->fetch();
    
    if (!$wd) {
        echo json_encode(['status' => 'error', 'msg' => ($lang=='ar'?'لا يمكن إلغاء هذا الطلب أو تم الرد عليه بالفعل.':'Cannot cancel this request.')]); exit;
    }

    try {
        $pdo->beginTransaction();
        
        $cancel_note = ($lang == 'ar') ? " ❌ [قام المستثمر بإلغاء الطلب واسترجاع أمواله]" : " ❌ [Canceled by User - Funds Returned]";
        $pdo->prepare("UPDATE withdrawals SET status = 'canceled', admin_notes = CONCAT(COALESCE(admin_notes, ''), ?) WHERE id = ?")->execute([$cancel_note, $wd_id]);
        
        if (strpos($wd['admin_notes'], 'سحب أرباح') !== false || strpos($wd['admin_notes'], 'Profit') !== false) {
            $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?")->execute([$wd['amount'], $user_id]);
        } 
        elseif (strpos($wd['admin_notes'], 'رأس المال') !== false || strpos($wd['admin_notes'], 'Capital') !== false) {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$wd['amount'], $user_id]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'msg' => ($lang=='ar'?'تم إلغاء الطلب بنجاح.':'Request canceled successfully.')]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'msg' => "DB Error: " . $e->getMessage()]);
    }
    exit;
}

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$sql_where = "WHERE user_id = ?";
$params = [$user_id];
if (!empty($from_date)) { $sql_where .= " AND DATE(created_at) >= ?"; $params[] = $from_date; }
if (!empty($to_date)) { $sql_where .= " AND DATE(created_at) <= ?"; $params[] = $to_date; }
$sql = "SELECT * FROM withdrawals $sql_where ORDER BY created_at DESC";
$stmt_withdrawals = $pdo->prepare($sql);
$stmt_withdrawals->execute($params);
$withdrawals = $stmt_withdrawals->fetchAll();

$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($txt['title']); ?> | Wealth Excel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --gold: #d4af37; --bg: #020617; --card: #1e293b; --text: #fff; }
        body { background: var(--bg); color: var(--text); font-family: '<?php echo ($lang=='ar')?'Cairo':'Inter'; ?>', sans-serif; margin: 0; padding: 20px; font-size: 14px; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 15px; }
        .back-btn { text-decoration: none; color: var(--gold); border: 1px solid var(--gold); padding: 5px 15px; border-radius: 5px; font-size: 13px; }
        .lang-switch { display: flex; gap: 5px; }
        .lang-btn { text-decoration: none; padding: 4px 10px; border-radius: 4px; font-size: 12px; border: 1px solid #334155; color: #aaa; }
        .lang-btn.active { background: var(--gold); color: #000; border-color: var(--gold); }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; text-align: center; }
        .success { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981; }
        .error { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid #ef4444; }
        .cycle-box { background: linear-gradient(135deg, rgba(30,41,59,1), rgba(15,23,42,1)); border: 1px solid var(--gold); padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .cycle-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed rgba(255,255,255,0.1); }
        .cycle-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .highlight { color: var(--gold); font-weight: bold; }
        .card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid #334155; margin-bottom: 20px; }
        .card-title { font-size: 14px; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        label { display: block; color: #aaa; font-size: 12px; margin-bottom: 5px; margin-top: 15px; }
        input { width: 100%; padding: 12px; background: #020617; border: 1px solid #444; color: #fff; border-radius: 6px; box-sizing: border-box; outline: none; font-size: 14px; }
        input:focus { border-color: var(--gold); }
        .input-wallet { font-family: monospace; font-size: 13px; color: #10b981; border-color: #10b981; direction: ltr; text-align: left; }
        .calc-box { margin-top: 15px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px; font-size: 12px; }
        .calc-row { display: flex; justify-content: space-between; margin-bottom: 5px; color: #aaa; }
        .calc-row.final { color: var(--gold); font-weight: bold; border-top: 1px solid #444; padding-top: 5px; margin-top: 5px; font-size: 14px; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 15px; transition: 0.3s; }
        .btn-gold { background: var(--gold); color: #000; }
        .btn-gold:hover { background: #b59021; }
        .btn-red { background: transparent; border: 1px solid #ef4444; color: #ef4444; }
        .btn-red:hover { background: #ef4444; color: #fff; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-bar input { width: auto; flex: 1; margin-top: 0; }
        .filter-bar button { padding: 10px 15px; background: var(--gold); color: #000; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 10px; }
        th { text-align: <?php echo ($lang=='ar')?'right':'left'; ?>; color: #999; padding: 8px; border-bottom: 1px solid #333; }
        td { padding: 8px; border-bottom: 1px solid #222; color: #ddd; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: #0f172a; padding: 25px; border-radius: 20px; border: 1px solid var(--gold); max-width: 400px; width: 90%; text-align: center; }
        .modal-content input { text-align: center; letter-spacing: 4px; font-size: 20px; font-family: monospace; }
        .badge-type { background: rgba(239,68,68,0.15); color: #ef4444; padding: 2px 6px; border-radius: 4px; font-size: 9px; margin-top: 3px; display: inline-block; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h3 style="margin:0;"><i class="fa-solid fa-money-bill-transfer" style="color:var(--gold);"></i> <?php echo htmlspecialchars($txt['title']); ?></h3>
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="lang-switch">
                <a href="?lang=ar" class="lang-btn <?php echo ($lang == 'ar') ? 'active' : ''; ?>">AR</a>
                <a href="?lang=en" class="lang-btn <?php echo ($lang == 'en') ? 'active' : ''; ?>">EN</a>
            </div>
            <a href="dashboard.php" class="back-btn"><?php echo htmlspecialchars($txt['back']); ?></a>
        </div>
    </div>
    
    <div id="alerts-container"></div>
    
    <div class="cycle-box">
        <div class="cycle-row"><span><?php echo htmlspecialchars($txt['cycle_total']); ?></span><span class="highlight"><?php echo num($days_passed); ?> <?php echo htmlspecialchars($txt['days_txt']); ?></span></div>
        <div class="cycle-row"><span><?php echo htmlspecialchars($txt['cycle_curr']); ?></span><span style="color:#10b981;"><?php echo htmlspecialchars($txt['cycle_comp']); ?></span></div>
    </div>
    
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-sack-dollar" style="color:#10b981;"></i> <?php echo htmlspecialchars($txt['profit_sec']); ?> <span style="font-size:11px; color:#aaa;">(<?php echo money($user_data['profit_balance']); ?>)</span></div>
        <form class="withdraw-form" id="form-profit">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="withdraw_profit" value="1">
            <label><?php echo htmlspecialchars($txt['amount']); ?></label>
            <input type="number" id="p_amount" name="amount" step="0.01" placeholder="0.00" min="5" max="<?php echo $user_data['profit_balance']; ?>" oninput="calcProfit()" required>
            <label style="color: #ffd700;"><?php echo htmlspecialchars($txt['wallet_lbl']); ?></label>
            <input type="text" class="input-wallet" name="wallet_address" placeholder="<?php echo htmlspecialchars($txt['wallet_plc']); ?>" value="<?php echo htmlspecialchars($user_data['personal_wallet']); ?>" required>
            <div class="calc-box">
                <div class="calc-row final"><span><?php echo htmlspecialchars($txt['net_lbl']); ?></span><span id="p_net"><?php echo num(0,2); ?></span></div>
            </div>
            <button type="submit" class="btn btn-gold" id="btn-submit-prof"><?php echo htmlspecialchars($txt['btn_profit']); ?></button>
        </form>
    </div>
    
    <div class="card" style="border-color:rgba(239,68,68,0.4);">
        <div class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i> <?php echo htmlspecialchars($txt['capital_sec']); ?> <span style="font-size:11px; color:#aaa;">(<?php echo money($user_data['balance']); ?>)</span></div>
        <p style="font-size:11px; color:#aaa;"><?php echo htmlspecialchars($txt['cap_warn']); ?></p>
        <?php if ($user_data['balance'] >= 5): ?>
            <form class="withdraw-form" id="form-capital">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="withdraw_capital" value="1">
                <label style="color: #ffd700;"><?php echo htmlspecialchars($txt['wallet_lbl']); ?></label>
                <input type="text" name="wallet_address" class="input-wallet" style="color:#ef4444; border-color: rgba(239,68,68,0.5);" placeholder="<?php echo htmlspecialchars($txt['wallet_plc']); ?>" value="<?php echo htmlspecialchars($user_data['personal_wallet']); ?>" required>
                <div class="calc-box">
                    <div class="calc-row"><span><?php echo htmlspecialchars($txt['cap_tot']); ?></span><span><?php echo money($user_data['balance']); ?></span></div>
                    <?php if($profit_clawback > 0): ?>
                    <div class="calc-row"><span style="color:#f59e0b;"><?php echo htmlspecialchars($txt['clawback_lbl']); ?></span><span style="color:#f59e0b;">-<?php echo money($profit_clawback); ?></span></div>
                    <?php endif; ?>
                    <div class="calc-row final">
                        <span><?php echo htmlspecialchars($txt['net_lbl']); ?></span>
                        <span style="color:#10b981;">
                            <?php 
                            $net_cap_ui = ($user_data['balance'] * (1 - ($capital_fee_pct/100))) - $profit_clawback;
                            echo money($net_cap_ui); 
                            ?>
                        </span>
                    </div>
                </div>
                <button type="submit" class="btn btn-red" id="btn-submit-cap"><?php echo htmlspecialchars($txt['btn_capital']); ?></button>
            </form>
        <?php else: ?>
            <div class="alert error"><?php echo htmlspecialchars($txt['err_cap']); ?></div>
        <?php endif; ?>
    </div>
    
    <div style="margin-top:30px;">
        <h4 style="color:#aaa; margin-bottom:10px;"><i class="fa-solid fa-list"></i> <?php echo htmlspecialchars($txt['hist']); ?></h4>
        <div class="filter-bar">
            <input type="date" id="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
            <input type="date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
            <button onclick="applyFilter()"><?php echo htmlspecialchars($txt['filter_btn']); ?></button>
            <button onclick="resetFilter()"><?php echo htmlspecialchars($txt['reset_btn']); ?></button>
        </div>
        <div class="table-container" style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars($txt['th_date']); ?></th>
                        <th><?php echo htmlspecialchars($txt['th_amount']); ?></th>
                        <th><?php echo htmlspecialchars($txt['th_net']); ?></th>
                        <th><?php echo htmlspecialchars($txt['th_status']); ?></th>
                        <th style="text-align:center;"><?php echo htmlspecialchars($txt['th_action']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($withdrawals as $w): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo num(date('Y-m-d', strtotime($w['created_at']))); ?></td>
                        <td><?php echo money($w['amount']); ?></td>
                        <td style="color:#10b981; font-weight:bold;"><?php echo money($w['net_amount']); ?></td>
                        <td>
                            <?php 
                            $s = $w['status']; 
                            $clr = ($s=='paid'||$s=='approved')?'#10b981':(($s=='rejected'||$s=='canceled')?'#ef4444':'#f59e0b'); 
                            $display_status = ($s=='approved'||$s=='paid') ? (($lang=='ar')?'مكتمل':'Completed') : (($s=='pending')? (($lang=='ar')?'قيد المعالجة':'Pending') : (($s=='canceled')? (($lang=='ar')?'ملغي':'Canceled') : (($lang=='ar')?'مرفوض':'Rejected'))); 
                            echo "<span style='color:$clr; font-weight:bold;'>".htmlspecialchars($display_status)."</span>"; 
                            if(strpos($w['admin_notes'],'رأس المال')!==false || strpos($w['admin_notes'],'Capital')!==false) echo "<br><span class='badge-type'>".(($lang=='ar')?'اشتراك رئيسي':'Main Subscription')."</span>"; 
                            ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if($s == 'pending'): ?>
                                <button onclick="cancelWithdrawal(<?php echo $w['id']; ?>)" style="background:rgba(239,68,68,0.2); color:#ef4444; border:1px solid #ef4444; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:11px; transition:0.3s;" onmouseover="this.style.background='#ef4444'; this.style.color='#fff';" onmouseout="this.style.background='rgba(239,68,68,0.2)'; this.style.color='#ef4444';"><?php echo ($lang=='ar')?'إلغاء':'Cancel'; ?></button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="otpModal" class="modal">
    <div class="modal-content">
        <h3 style="color:var(--gold); margin-top:0;"><i class="fa-solid fa-shield-halved"></i> <?php echo ($lang=='ar')?'رمز التحقق (OTP)':'Verification Code (OTP)'; ?></h3>
        <p style="color:#ccc; font-size:13px; line-height:1.5;"><?php echo ($lang=='ar')?'لحمايتك، تم إرسال رمز تحقق إلى بريدك الإلكتروني. يرجى إدخاله أدناه لإتمام عملية التحويل.':'For your security, a code was sent to your email. Enter it below to complete the transfer.'; ?></p>
        <input type="text" id="withdraw_otp" maxlength="6" placeholder="000000">
        <button class="btn btn-gold" id="btn-confirm-otp" onclick="submitWithdrawOtp()"><?php echo ($lang=='ar')?'تأكيد التحويل':'Confirm Transfer'; ?></button>
        <button class="btn" style="background:transparent; border:1px solid #555; color:#aaa;" onclick="closeOtpModal()"><?php echo ($lang=='ar')?'إلغاء':'Cancel'; ?></button>
    </div>
</div>

<script>
    const profitFeePct = <?php echo $profit_fee_pct; ?>;
    const lang = "<?php echo $lang; ?>";
    const csrfToken = "<?php echo $csrf_token; ?>";
    let pendingForm = null;

    function formatMoneyJS(n) {
        let s = n.toFixed(2);
        if(lang === "ar") { const eastern = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩']; s = s.replace(/\d/g, d => eastern[+d]); }
        return s;
    }

    function calcProfit() {
        let amount = parseFloat(document.getElementById('p_amount').value);
        if(isNaN(amount) || amount <= 0 || amount < 5) { document.getElementById('p_net').innerText = formatMoneyJS(0); return; }
        let fee = amount * (profitFeePct / 100);
        let net = amount - fee;
        document.getElementById('p_net').innerText = formatMoneyJS(net);
    }

    function applyFilter() {
        let from = document.getElementById('from_date').value, to = document.getElementById('to_date').value;
        let url = new URL(window.location.href);
        if(from) url.searchParams.set('from_date', from); else url.searchParams.delete('from_date');
        if(to) url.searchParams.set('to_date', to); else url.searchParams.delete('to_date');
        window.location.href = url.toString();
    }

    function resetFilter() { window.location.href = window.location.pathname; }
    
    function showAlert(msg, isSuccess) {
        const container = document.getElementById('alerts-container');
        container.innerHTML = `<div class="alert ${isSuccess ? 'success' : 'error'}">${msg}</div>`;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function cancelWithdrawal(wd_id) {
        if(!confirm('<?php echo ($lang=='ar')?'هل أنت متأكد من إلغاء الطلب؟ ستعود الأموال إلى رصيدك.':'Are you sure you want to cancel this request? Funds will be returned to your balance.'; ?>')) return;
        
        const fd = new FormData();
        fd.append('ajax_action', 'cancel_withdrawal');
        fd.append('wd_id', wd_id);
        fd.append('csrf_token', csrfToken);
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            alert(data.msg);
            if(data.status === 'success') {
                window.location.reload();
            }
        });
    }

    document.querySelectorAll('.withdraw-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (this.id === 'form-capital') {
                if(!confirm('<?php echo $txt['js_conf_cap']; ?>')) return;
            }

            pendingForm = this;
            const btn = this.querySelector('button[type="submit"]');
            const ogText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; btn.disabled = true;

            const fd = new FormData(this);
            fd.append('ajax_action', 'request_withdraw_otp');

            fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'otp_required') {
                    showAlert(data.msg, true);
                    document.getElementById('otpModal').style.display = 'flex';
                } else {
                    showAlert(data.msg, false); // 🔴 هذا السطر سيعرض لك خطأ الـ SMTP الدقيق باللغة الإنجليزية
                }
            })
            .finally(() => { btn.innerHTML = ogText; btn.disabled = false; });
        });
    });
    
    function submitWithdrawOtp() {
        const otp = document.getElementById('withdraw_otp').value.trim();
        if(!otp || otp.length != 6) { alert(lang=='ar'?'أدخل الرمز المكون من 6 أرقام':'Enter 6-digit code'); return; }
        
        const fd = new FormData(pendingForm);
        fd.append('ajax_action', 'verify_withdraw_otp');
        fd.append('otp', otp);
        
        const btn = document.getElementById('btn-confirm-otp');
        const original = btn.innerText;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; btn.disabled = true;
        
        fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                alert(data.msg);
                window.location.reload();
            } else {
                alert(data.msg);
                btn.innerHTML = original; 
                btn.disabled = false;
            }
        });
    }

    function closeOtpModal() {
        document.getElementById('otpModal').style.display = 'none';
        document.getElementById('withdraw_otp').value = '';
        pendingForm = null;
    }

    document.addEventListener('DOMContentLoaded', function() { calcProfit(); });
</script>
</body>
</html>