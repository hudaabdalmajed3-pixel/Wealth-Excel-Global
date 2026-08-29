<?php
// dashboard.php - Secured with Auto Fractional Deposit System
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'config.php';
require_once 'otp_helper.php';

// =======================================================
// 🎁 ثوابت نظام الإحالات (Referral System Constants)
// =======================================================
define('REFERRAL_TARGET', 30);
define('REFERRAL_BONUS_PERCENT', 0.085); // 8.5%

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }

// =======================================================
// ✅ التأكد من قواعد البيانات وتحديثها تلقائياً (Auto-Fix)
// =======================================================
try { $pdo->query("SELECT 1 FROM referrals LIMIT 1"); } catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `referrals` ( `id` int(11) NOT NULL AUTO_INCREMENT, `referrer_id` int(11) NOT NULL, `referred_id` int(11) NOT NULL, `is_active` tinyint(1) DEFAULT 0, `total_deposit` decimal(15,2) DEFAULT 0.00, `created_at` datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `referrer_id` (`referrer_id`), KEY `referred_id` (`referred_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
try { $pdo->query("SELECT 1 FROM user_otp LIMIT 1"); } catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_otp` ( `id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `otp_code` varchar(10) NOT NULL, `action` varchar(50) NOT NULL, `data` text DEFAULT NULL, `expires_at` datetime NOT NULL, `created_at` datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
try { $pdo->query("SELECT referred_id FROM referrals LIMIT 1"); } catch (PDOException $e) { try { $pdo->exec("ALTER TABLE referrals ADD COLUMN referred_id INT(11) NOT NULL AFTER referrer_id"); } catch(Exception $ex) {} }
try { $pdo->query("SELECT is_active FROM referrals LIMIT 1"); } catch (PDOException $e) { try { $pdo->exec("ALTER TABLE referrals ADD COLUMN is_active TINYINT(1) DEFAULT 0"); $pdo->exec("ALTER TABLE referrals ADD COLUMN total_deposit DECIMAL(15,2) DEFAULT 0.00"); } catch(Exception $ex) {} }
try { $pdo->query("SELECT bonus_capital FROM users LIMIT 1"); } catch (PDOException $e) { $pdo->exec("ALTER TABLE users ADD COLUMN bonus_capital DECIMAL(15,2) DEFAULT 0.00 AFTER balance"); }
try { $pdo->query("SELECT bonus_activated FROM users LIMIT 1"); } catch (PDOException $e) { $pdo->exec("ALTER TABLE users ADD COLUMN bonus_activated TINYINT(1) DEFAULT 0 AFTER bonus_capital"); }
try { $pdo->query("SELECT status FROM plans LIMIT 1"); } catch (PDOException $e) { try { $pdo->exec("ALTER TABLE plans ADD COLUMN status VARCHAR(20) DEFAULT 'active'"); } catch(Exception $ex) {} }
try { $pdo->query("SELECT wallet_address FROM users LIMIT 1"); } catch (PDOException $e) { $pdo->exec("ALTER TABLE users ADD COLUMN wallet_address VARCHAR(255) DEFAULT NULL AFTER personal_wallet"); }
try { $pdo->query("SELECT account_status FROM users LIMIT 1"); } catch (PDOException $e) { $pdo->exec("ALTER TABLE users ADD COLUMN account_status VARCHAR(20) DEFAULT 'active' AFTER role"); }

$stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('referral_bonus_plan_id', '1')"); $stmt->execute();

$user_id = $_SESSION['user_id'];
$lang = $_SESSION['lang'] ?? 'en';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// جلب بيانات المستخدم
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmtUser->execute([$user_id]); $user = $stmtUser->fetch();

// 🔴 حماية التجميد 
if (!$user || (isset($user['account_status']) && strtolower($user['account_status']) === 'frozen')) { 
    session_unset();
    session_destroy();
    header("Location: login.php?error=frozen"); 
    exit; 
}

$admin_wallet = '';
try { $admin_wallet = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='deposit_wallet'")->fetchColumn(); } catch(Exception $e) {}

// =======================================================
// ✅ تحديث سجل الإحالة تلقائياً 
// =======================================================
$stmtDep = $pdo->prepare("SELECT SUM(amount) FROM auto_deposits WHERE user_id = ? AND status = 'completed'");
$stmtDep->execute([$user_id]);
$total_deposits = floatval($stmtDep->fetchColumn());

$stmtWd = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status IN ('completed', 'approved', 'paid')");
$stmtWd->execute([$user_id]);
$total_withdrawals = floatval($stmtWd->fetchColumn());

$net_deposit = max(0, $total_deposits - $total_withdrawals);

$stmtRefCheck = $pdo->prepare("SELECT id, total_deposit, is_active FROM referrals WHERE referred_id = ?");
$stmtRefCheck->execute([$user_id]);
$refRecord = $stmtRefCheck->fetch();

if ($refRecord) {
    if (floatval($refRecord['total_deposit']) != $net_deposit) {
        $pdo->prepare("UPDATE referrals SET total_deposit = ? WHERE id = ?")->execute([$net_deposit, $refRecord['id']]);
    }
    $is_active_now = ($net_deposit > 0) ? 1 : 0;
    if ($refRecord['is_active'] != $is_active_now) {
        $pdo->prepare("UPDATE referrals SET is_active = ? WHERE id = ?")->execute([$is_active_now, $refRecord['id']]);
    }
}

// =======================================================
// 🔴 1. تحديث إعدادات العنوان 
// =======================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'update_my_wallet') {
    header('Content-Type: application/json');
    $new_wallet = trim(htmlspecialchars($_POST['wallet']));
    if (empty($new_wallet) || strlen($new_wallet) < 10) { echo json_encode(['status' => 'error', 'msg' => ($lang=='ar'?'عنوان غير صالح!':'Invalid address!')]); exit; }
    try { $pdo->prepare("UPDATE users SET personal_wallet = ? WHERE id = ?")->execute([$new_wallet, $user_id]); echo json_encode(['status' => 'success', 'msg' => ($lang=='ar'?'تم حفظ العنوان بنجاح.':'Address saved successfully.')]); exit; } catch (Exception $e) { echo json_encode(['status' => 'error', 'msg' => 'Database Error: ' . $e->getMessage()]); exit; }
}

// =======================================================
// 🔴 2. إعادة استثمار الأرباح (مباشر بدون OTP)
// =======================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'process_reinvest') {
    header('Content-Type: application/json');
    $amount = floatval($_POST['amount']);
    
    $u_data = $pdo->query("SELECT profit_balance, balance, bonus_capital, plan_id FROM users WHERE id = $user_id")->fetch();
    
    if ($amount <= 0 || $amount > $u_data['profit_balance']) { 
        echo json_encode(['status' => 'error', 'msg' => ($lang=='ar'?'رصيد الأرباح غير كافٍ!':'Insufficient profit balance!')]); 
        exit; 
    }
    
    try {
        $pdo->beginTransaction();
        $new_total_balance = $u_data['balance'] + $amount; 
        $combined_for_active = $new_total_balance + floatval($u_data['bonus_capital']);
        $plan_id = $u_data['plan_id']; 
        $new_active_capital = 0;
        
        if ($plan_id) { 
            $plan_info = $pdo->query("SELECT max_price FROM plans WHERE id = $plan_id")->fetch(); 
            $max_price = floatval($plan_info['max_price']); 
            $new_active_capital = ($max_price == -1 || $max_price == 0) ? $combined_for_active : min($combined_for_active, $max_price); 
        }
        
        $pdo->prepare("UPDATE users SET profit_balance = profit_balance - ?, balance = ?, active_capital = ? WHERE id = ?")->execute([$amount, $new_total_balance, $new_active_capital, $user_id]);
        
        $reinvest_txid = "REINVEST-" . strtoupper(substr(uniqid(), -6));
        $pdo->prepare("INSERT INTO auto_deposits (user_id, txn_id, amount, currency, status, created_at) VALUES (?, ?, ?, 'USDT', 'completed', NOW())")->execute([$user_id, $reinvest_txid, $amount]);
        
        $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, ($lang=='ar'?"🔄 تمت إعادة توجيه $$amount كطاقة تشغيلية إضافية.":"🔄 Redirected $$amount as additional operational capacity.")]);
        
        $pdo->commit(); 
        echo json_encode(['status' => 'success', 'msg' => ($lang=='ar'?'تم التدوير التقني بنجاح!':'Technical recycling processed successfully!')]); 
        exit;
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        echo json_encode(['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()]); 
        exit; 
    }
}

// =======================================================
// 🔴 3. ترقية الخدمة (Upgrade Plan)
// =======================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'upgrade_plan') {
    header('Content-Type: application/json');
    $new_plan_id = intval($_POST['plan_id']);
    $u_data = $pdo->query("SELECT balance, bonus_capital FROM users WHERE id = $user_id")->fetch();
    $total_balance = floatval($u_data['balance']) + floatval($u_data['bonus_capital']);
    
    $plan_info = $pdo->query("SELECT min_price, max_price, name FROM plans WHERE id = $new_plan_id")->fetch();
    if (!$plan_info) { echo json_encode(['status' => 'error', 'msg' => 'Service not found']); exit; }
    if ($total_balance < $plan_info['min_price']) { echo json_encode(['status' => 'error', 'msg' => ($lang=='ar'?'السعة التشغيلية لا تكفي لتحديث كفاءة النظام.':'Operational capacity insufficient for system update.')]); exit; }
    
    $max_price = floatval($plan_info['max_price']);
    $new_active_capital = ($max_price == -1 || $max_price == 0) ? $total_balance : min($total_balance, $max_price);
    
    try {
        $pdo->prepare("UPDATE users SET plan_id = ?, active_capital = ? WHERE id = ?")->execute([$new_plan_id, $new_active_capital, $user_id]);
        $msg = ($lang=='ar') ? "⭐ تم تحديث كفاءة النظام إلى (".$plan_info['name'].")" : "⭐ System efficiency updated to ".$plan_info['name'];
        $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, $msg]);
        echo json_encode(['status' => 'success', 'msg' => ($lang=='ar'?'تم تحديث النظام بنجاح!':'System updated successfully!')]); exit;
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()]); exit; }
}

// =======================================================
// 🔴 4. طلب الإيداع 
// =======================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'request_deposit') {
    header('Content-Type: application/json');
    $base_amount = floatval($_POST['amount']);
    
    if (empty($admin_wallet)) { 
        echo json_encode(['status' => 'error', 'msg' => ($lang=='ar'?'محفظة المنصة غير معدة.':'Platform wallet not set.')]); exit; 
    }
    if ($base_amount < 10) { 
        echo json_encode(['status' => 'error', 'msg' => ($lang=='ar'?'المبلغ أقل من الحد الأدنى.':'Amount is below minimum.')]); exit; 
    }

    try {
        $unique_amount = 0;
        $is_unique = false;
        
        while (!$is_unique) {
            $fraction = rand(10, 990) / 1000;
            $unique_amount = number_format($base_amount + $fraction, 3, '.', '');
            $stmt = $pdo->prepare("SELECT id FROM auto_deposits WHERE amount = ? AND status = 'pending'");
            $stmt->execute([$unique_amount]);
            if ($stmt->rowCount() == 0) { $is_unique = true; }
        }

        $temp_txn = "PEND-" . strtoupper(uniqid());

        $pdo->prepare("INSERT INTO auto_deposits (user_id, txn_id, amount, currency, status, created_at) VALUES (?, ?, ?, 'USDT', 'pending', NOW())")
            ->execute([$user_id, $temp_txn, $unique_amount]);
            
        $new_dep_id = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success', 
            'unique_amount' => $unique_amount,
            'dep_id' => $new_dep_id
        ]); exit;

    } catch (Exception $e) { 
        echo json_encode(['status' => 'error', 'msg' => 'Database Error: ' . $e->getMessage()]); exit; 
    }
}

// =======================================================
// 🔴 4.5. فحص حالة الإيداع المباشر
// =======================================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'check_deposit_status') {
    header('Content-Type: application/json');
    $dep_id = intval($_POST['dep_id']);
    try {
        $stmt = $pdo->prepare("SELECT status FROM auto_deposits WHERE id = ? AND user_id = ?");
        $stmt->execute([$dep_id, $user_id]);
        $dep = $stmt->fetch();
        if ($dep && $dep['status'] == 'completed') {
            echo json_encode(['status' => 'completed']); exit;
        }
        echo json_encode(['status' => 'pending']); exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error']); exit;
    }
}

// =======================================================
// 🔴 5. تأمين الجلسات اليومية 
// =======================================================
date_default_timezone_set('UTC');
$date_today = date('Y-m-d');
$current_time_minutes = (int)date('H') * 60 + (int)date('i');

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'execute_task') {
    header('Content-Type: application/json');
    $task_type = $_POST['task_type'];
    
    $tStmt = $pdo->prepare("SELECT * FROM daily_tasks WHERE user_id = ? AND task_date = ?"); 
    $tStmt->execute([$user_id, $date_today]); 
    $todayTask = $tStmt->fetch();
    
    if ($task_type == 'task1') {
        $invID = $user['plan_id'] ?: 0;
        if ($todayTask) { 
            $pdo->prepare("UPDATE daily_tasks SET market_checkin=1, checkin_time=NOW() WHERE id=?")->execute([$todayTask['id']]); 
        } else { 
            $pdo->prepare("INSERT INTO daily_tasks (user_id, investment_id, task_date, market_checkin, checkin_time, status) VALUES (?, ?, ?, 1, NOW(), 'pending')")->execute([$user_id, $invID, $date_today]); 
        }
        echo json_encode(['status' => 'success', 'msg' => ($lang=='ar'?'تم بدء الجلسة بنجاح!':'Session started successfully!')]); exit;
    }
    else if ($task_type == 'task2') {
        $active_capital = floatval($user['active_capital']);
        $daily_profit = 0;
        
        if ($user['plan_id']) {
            $plan_roi = $pdo->query("SELECT roi_percentage FROM plans WHERE id = {$user['plan_id']}")->fetchColumn();
            $daily_profit = ($active_capital * ($plan_roi / 100)) / 30;
            
            if ($daily_profit > 0) { 
                $col_to_update = ($user['is_cumulative'] == 1) ? 'balance' : 'profit_balance'; 
                $pdo->prepare("UPDATE users SET $col_to_update = $col_to_update + ? WHERE id=?")->execute([$daily_profit, $user_id]); 
            }
        }
        
        if ($todayTask) { 
            $pdo->prepare("UPDATE daily_tasks SET profit_claimed=1, claim_time=NOW(), profit_amount=?, status='completed' WHERE id=?")->execute([$daily_profit, $todayTask['id']]); 
        } else { 
            $pdo->prepare("INSERT INTO daily_tasks (user_id, investment_id, task_date, profit_claimed, claim_time, profit_amount, status) VALUES (?, ?, ?, 1, NOW(), ?, 'completed')")->execute([$user_id, $user['plan_id'], $date_today, $daily_profit]); 
        }
        echo json_encode(['status' => 'success', 'msg' => ($lang=='ar'?'تمت مراجعة النتائج وتحديث الإنجاز بنجاح!':'Results reviewed successfully!')]); exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'mark_read') { try { $pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ?")->execute([$user_id]); } catch(Exception $e){} exit('success'); }

// =======================================================
// 🎁 نظام الإحالات الديناميكي
// =======================================================
$stmtRef = $pdo->prepare("
    SELECT 
        r.referred_id, 
        u.username, 
        u.created_at,
        (SELECT COALESCE(SUM(amount), 0) FROM auto_deposits WHERE user_id = r.referred_id AND status = 'completed') as total_deps,
        (SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE user_id = r.referred_id AND status IN ('completed', 'approved', 'paid')) as total_withs
    FROM referrals r 
    JOIN users u ON r.referred_id = u.id 
    WHERE r.referrer_id = ? 
    ORDER BY r.created_at ASC
"); 
$stmtRef->execute([$user_id]); 
$my_direct_team = $stmtRef->fetchAll();

$active_referrals = 0; 
$team_total_deposits = 0;

foreach ($my_direct_team as $member) { 
    $member_net_deposit = max(0, floatval($member['total_deps']) - floatval($member['total_withs']));
    $is_active = ($member_net_deposit > 0) ? 1 : 0;

    if ($is_active == 1 && $member_net_deposit > 0) { 
        $active_referrals++; 
        $team_total_deposits += $member_net_deposit;
    } 
    
    $pdo->prepare("UPDATE referrals SET total_deposit = ?, is_active = ? WHERE referred_id = ?")->execute([$member_net_deposit, $is_active, $member['referred_id']]);
}

$total_balance = floatval($user['balance']); 
$profit_balance = floatval($user['profit_balance']);

if ($active_referrals >= REFERRAL_TARGET) { 
    $expected_bonus = $team_total_deposits * REFERRAL_BONUS_PERCENT; 
    $expected_activated = 1; 
} else { 
    $expected_bonus = 0.00; 
    $expected_activated = 0; 
}

if (floatval($user['bonus_capital']) != $expected_bonus || $user['bonus_activated'] != $expected_activated) {
    if ($expected_activated == 1 && $user['bonus_activated'] == 0) {
        $msg = ($lang=='ar') ? "🎉 تم استيفاء شروط العائلة النشطة. تم تفعيل الهدية لدعم السعة التشغيلية." : "🎉 Active family conditions met. Bonus activated to support operational capacity.";
        $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, $msg]);
    } elseif ($expected_activated == 0 && $user['bonus_activated'] == 1) {
        $msg = ($lang=='ar') ? "⚠️ تم إيقاف الهدية لعدم اكتمال شروط العائلة النشطة." : "⚠️ Bonus paused: Active family conditions not met.";
        $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, $msg]);
    }
    elseif ($expected_activated == 1 && $expected_bonus > floatval($user['bonus_capital'])) {
        $msg = ($lang=='ar') ? "📈 زاد النشاط التفاعلي لعائلتك! تم تحديث قوة التفاعل الإضافية إلى: $$expected_bonus" : "📈 Family interactive activity grew! Bonus updated to: $$expected_bonus";
        $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, $msg]);
    }
    
    $pdo->prepare("UPDATE users SET bonus_capital = ?, bonus_activated = ? WHERE id = ?")->execute([$expected_bonus, $expected_activated, $user_id]);
    $user['bonus_capital'] = $expected_bonus; 
    $user['bonus_activated'] = $expected_activated;
}

$bonus_capital = floatval($user['bonus_capital']); 
$bonus_activated = intval($user['bonus_activated']);
$combined_capital = $total_balance + $bonus_capital;

$bestPlanStmt = $pdo->query("SELECT id, max_price FROM plans WHERE status = 'active' AND min_price <= $combined_capital ORDER BY min_price DESC LIMIT 1"); 
$bestPlan = $bestPlanStmt->fetch();
$target_plan_id = $bestPlan ? $bestPlan['id'] : 0; 
$plan_max = $bestPlan ? floatval($bestPlan['max_price']) : 0;
$calculated_active = $combined_capital;
if ($plan_max > 0 && $calculated_active > $plan_max) { $calculated_active = $plan_max; }
if ($user['plan_id'] != $target_plan_id || floatval($user['active_capital']) != $calculated_active) {
    $pdo->prepare("UPDATE users SET active_capital = ?, plan_id = ? WHERE id = ?")->execute([$calculated_active, $target_plan_id, $user_id]);
    $user['active_capital'] = $calculated_active; 
    $user['plan_id'] = $target_plan_id;
}
$active_capital = floatval($user['active_capital']);

// المهام اليومية
$task1_start = 15 * 60 + 30;   
$task1_end   = 17 * 60 + 30;   
$task2_start = 18 * 60 + 30;   
$task2_end   = 21 * 60 + 30;   

$show_task_1 = ($current_time_minutes >= $task1_start && $current_time_minutes < $task1_end);
$show_task_2 = ($current_time_minutes >= $task2_start && $current_time_minutes < $task2_end);

$tStmt = $pdo->prepare("SELECT * FROM daily_tasks WHERE user_id = ? AND task_date = ?"); 
$tStmt->execute([$user_id, $date_today]); 
$todayTask = $tStmt->fetch();
$is_4pm_done = ($todayTask && $todayTask['market_checkin'] == 1); 
$is_8pm_done = ($todayTask && $todayTask['profit_claimed'] == 1);

// =======================================================
// تحضير البيانات للواجهة
// =======================================================
$page_title = "Investor Dashboard";
require 'header.php';

$plansList = [];
try { $plansList = $pdo->query("SELECT * FROM plans WHERE status = 'active' ORDER BY min_price ASC")->fetchAll(); } catch (Exception $e) {}
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$script_path = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$my_referral_link = "{$protocol}://{$domain}{$script_path}/Register.php?ref={$user_id}";

$notifications = []; $has_unread = false;
try { $notif_stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 10"); $notif_stmt->execute([$user_id]); $notifications = $notif_stmt->fetchAll(); foreach($notifications as $n) { if(isset($n['is_read']) && $n['is_read'] == 0) $has_unread = true; } } catch (Exception $e) {}

$history = [];
try {
    $stmtDep = $pdo->prepare("SELECT amount, created_at, txn_id as ref, status, 'deposit' as type FROM auto_deposits WHERE user_id = ?"); $stmtDep->execute([$user_id]); foreach($stmtDep->fetchAll() as $row) $history[] = $row;
    $stmtWd = $pdo->prepare("SELECT amount, created_at, wallet_address as ref, status, 'withdraw' as type FROM withdrawals WHERE user_id = ?"); $stmtWd->execute([$user_id]); foreach($stmtWd->fetchAll() as $row) $history[] = $row;
} catch(Exception $e) {}
usort($history, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); }); $history = array_slice($history, 0, 15);

$user_plan_name = ($lang == 'ar') ? "لا توجد خدمة" : "No Service"; 
$user_roi = 0;
if (!empty($user['plan_id'])) {
    $stmtPlan = $pdo->prepare("SELECT name, roi_percentage FROM plans WHERE id = ?"); $stmtPlan->execute([$user['plan_id']]); $p = $stmtPlan->fetch();
    if ($p) { $user_plan_name = $p['name']; $user_roi = $p['roi_percentage']; }
}

function num($val, $decimals = 0) { global $lang; $val = number_format((float)$val, $decimals); if ($lang == 'ar') { $std=['0','1','2','3','4','5','6','7','8','9']; $east=['٠','١','٢','٣','٤','٥','٦','٧','٨','٩']; return str_replace($std, $east, (string)$val); } return $val; }
function money($val) { global $lang; $val = number_format((float)$val, 2); if ($lang == 'ar') { $std=['0','1','2','3','4','5','6','7','8','9']; $east=['٠','١','٢','٣','٤','٥','٦','٧','٨','٩']; return str_replace($std, $east, (string)$val); } return $val; }

$d_txt = [
    'en' => [ 
        'welcome'=>'Welcome,', 
        'daily'=>'Daily Execution Schedule', 
        'task1'=>'Start Operations Session', 
        'task2'=>'Review Daily Results', 
        'done'=>'Executed', 
        'act'=>'Execute', 
        'claim'=>'Execute', 
        'lock'=>'Locked', 
        'market'=>'Market Watch', 
        'prof_bal'=>'Available Value', 
        'cap_bal'=>'Total Resources (Base)', 
        'active_cap'=>'Active Value (Incl. Grant)', 
        'current_plan' => 'Current Service', 
        'withdraw_txt'=>'Export Resources', 
        'deposit_txt'=>'Operational Input', 
        'upgrade_txt'=>'System Efficiency Update', 
        'reinvest_txt'=>'Transfer Value', 
        'notif_title' => 'Notifications', 
        'no_notif' => 'No new notifications', 
        'req_task1' => 'Start Session First!', 
        'market_closed' => 'Closed', 
        'daily_earn' => 'Achieved Daily Progress', 
        'rate' => 'Rate', 
        'ref'=>'Invite Friends', 
        'copy'=>'Copy', 
        'invite_win' => 'Referral Program', 
        'tx_title' => 'Technical Activity Log' 
    ],
    'ar' => [ 
        'welcome'=>'مرحباً،', 
        'daily'=>'الجدول التنفيذي اليومي', 
        'task1'=>'بدء جلسة العمليات', 
        'task2'=>'مراجعة النتائج اليومية', 
        'done'=>'تم التنفيذ', 
        'act'=>'تنفيذ', 
        'claim'=>'تنفيذ', 
        'lock'=>'مغلق', 
        'market'=>'السوق', 
        'prof_bal'=>'القيمة المتاحة', 
        'cap_bal'=>'الموارد الكلية (الأساسي)', 
        'active_cap'=>'(شامل المنحة) القيمة النشطة', 
        'current_plan' => 'الخدمة الحالية', 
        'withdraw_txt'=>'تصدير الموارد', 
        'deposit_txt'=>'إدخال تشغيلي', 
        'upgrade_txt'=>'تحديث كفاءة النظام', 
        'reinvest_txt'=>'تحويل القيمة', 
        'notif_title' => 'الإشعارات', 
        'no_notif' => 'لا توجد إشعارات جديدة', 
        'req_task1' => 'يجب بدء الجلسة أولاً!', 
        'market_closed' => 'مغلق', 
        'daily_earn' => 'الإنجاز اليومي المحقق', 
        'rate' => 'المعدل', 
        'ref'=>'دعوة الأصدقاء', 
        'copy'=>'نسخ', 
        'invite_win' => 'نظام الإحالات', 
        'tx_title' => 'سجل النشاط التقني' 
    ]
];
function d_lang($k) { global $d_txt, $lang; return $d_txt[$lang][$k] ?? $k; }

$earned_amount = ($is_4pm_done && $is_8pm_done && isset($todayTask['profit_amount'])) ? $todayTask['profit_amount'] : 0;
$box_class = ($is_4pm_done && $is_8pm_done) ? 'completed' : 'pending';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo ($lang=='ar')?'rtl':'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #d4af37; --bg-card: #1e293b; --bg-dark: #0f172a; --text-muted: #94a3b8; }
        body { background-color: var(--bg-dark); color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; font-weight: 300; }
        .dashboard-container { padding-top: 15px; padding: 15px; max-width: 1200px; margin: 0 auto; }
        .user-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .notif-wrapper { position: relative; }
        .notif-icon { font-size: 20px; color: #fff; cursor: pointer; }
        .notif-badge { position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; display: <?php echo $has_unread ? 'block' : 'none'; ?>; }
        .notif-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2,6,23,0.85); z-index: 1999; backdrop-filter: blur(4px); }
        .notif-modal { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 350px; background: #1e293b; border: 1px solid rgba(212,175,55,0.3); border-radius: 20px; z-index: 2000; box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden; }
        .notif-header { background: rgba(212,175,55,0.05); padding: 18px 20px; color: var(--gold); font-weight: 400; letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(212,175,55,0.1); }
        .notif-list { max-height: 300px; overflow-y: auto; padding: 0; }
        .notif-item { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; font-size: 13px; font-weight: 300; }
        .split-section { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 1024px) { .split-section { grid-template-columns: 1fr; } }
        .tasks-box { background: rgba(212,175,55,0.02); border: 1px solid rgba(212,175,55,0.2); border-radius: 16px; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
        .task-item { background: rgba(15,23,42,0.6); padding: 12px 15px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.03); }
        .task-txt { font-weight: 400; font-size: 13px; color: #f1f5f9; letter-spacing: 0.3px; }
        .task-time { font-size: 11px; color: #94a3b8; font-family: monospace; font-weight: 300; }
        .today-profit-box { margin-top: 10px; border-radius: 12px; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; }
        .today-profit-box.pending { background: rgba(15,23,42,0.4); border: 1px dashed rgba(255, 255, 255, 0.1); }
        .today-profit-box.completed { background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.3); }
        .profit-label { font-size: 12px; font-weight: 400; letter-spacing: 0.5px; }
        .profit-value { font-size: 15px; font-weight: 500; font-family: monospace; letter-spacing: 0.5px; }
        .btn-task { padding: 8px 18px; border-radius: 20px; border: none; font-weight: 400; cursor: pointer; font-size: 12px; min-width: 90px; transition: 0.3s; letter-spacing: 0.5px; }
        .btn-active { background: linear-gradient(135deg, #d4af37, #b8860b); color: #000; animation: pulse 2s infinite; }
        .btn-lock { background: #334155; color: #94a3b8; cursor: not-allowed; }
        .btn-done { background: transparent; color: #10b981; border: 1px solid #10b981; cursor: default; }
        .finance-stack { display: flex; flex-direction: column; gap: 10px; margin-bottom: 25px; }
        .fin-card { background: linear-gradient(145deg, rgba(30,41,59,0.6), rgba(15,23,42,0.8)); padding: 16px 20px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(5px); }
        .fin-card.plan { border-<?php echo ($lang=='ar')?'right':'left'; ?>: 3px solid var(--gold); }
        .fin-card.capital { border-<?php echo ($lang=='ar')?'right':'left'; ?>: 3px solid #3b82f6; }
        .fin-card.profit { border-<?php echo ($lang=='ar')?'right':'left'; ?>: 3px solid #10b981; }
        .fin-lbl { font-size: 12px; color: #cbd5e1; font-weight: 400; letter-spacing: 0.3px; }
        .fin-val { font-size: 16px; font-weight: 400; color: #fff; font-family: monospace; text-align: <?php echo ($lang=='ar')?'left':'right'; ?>; letter-spacing: 0.5px; }
        .btn-reinvest { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 12px; border-radius: 20px; font-size: 11px; cursor: pointer; transition: 0.3s; margin-top: 8px; font-weight: 400; letter-spacing: 0.5px; }
        .btn-reinvest:hover { background: #10b981; color: #000; }
        .my-wallet-section { background: rgba(30,41,59,0.3); border: 1px dashed rgba(255,255,255,0.1); border-radius: 16px; padding: 20px; margin-bottom: 25px; }
        .my-wallet-title { color: #f1f5f9; font-size: 13px; font-weight: 400; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; letter-spacing: 0.5px; }
        .my-wallet-input-box { display: flex; gap: 10px; }
        .my-wallet-input-box input { flex: 1; background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.08); color: #10b981; padding: 12px 16px; border-radius: 10px; outline: none; font-family: monospace; font-size: 13px; font-weight: 300; transition: 0.3s; text-align: left; direction: ltr; }
        .my-wallet-input-box input:focus { border-color: var(--gold); box-shadow: 0 0 8px rgba(212,175,55,0.1); }
        .my-wallet-btn { background: linear-gradient(135deg, #d4af37, #b8860b); color: #000; border: none; padding: 0 20px; border-radius: 10px; font-weight: 500; cursor: pointer; transition: 0.3s; font-size: 13px; letter-spacing: 0.5px; }
        .my-wallet-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .action-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 25px; }
        @media(max-width: 600px) { .action-grid { grid-template-columns: repeat(2, 1fr); } }
        .btn-action-card { background: linear-gradient(145deg, rgba(30,41,59,0.6), rgba(15,23,42,0.8)); border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 16px; padding: 16px 8px; display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer; transition: all 0.3s ease; color: #fff; text-decoration: none; text-align: center; backdrop-filter: blur(5px); }
        .btn-action-card:hover { transform: translateY(-3px); border-color: rgba(212, 175, 55, 0.6); box-shadow: 0 8px 20px rgba(212, 175, 55, 0.1); }
        .btn-action-card i { font-size: 22px; opacity: 0.9; }
        .btn-action-card span { font-weight: 400; font-size: 12px; line-height: 1.3; letter-spacing: 0.5px; }
        .plan-card-ui { background: linear-gradient(145deg, rgba(30,41,59,0.7), rgba(15,23,42,0.9)); border: 1px solid rgba(212,175,55,0.15); border-radius: 12px; padding: 16px; margin-bottom: 12px; cursor: pointer; transition: all 0.3s ease; text-align: <?php echo ($lang=='ar')?'right':'left'; ?>; position: relative; overflow: hidden; backdrop-filter: blur(5px); }
        .plan-card-ui:hover { border-color: rgba(212,175,55,0.6); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(212,175,55,0.08); }
        .plan-title { color: var(--gold); font-weight: 400; font-size: 16px; margin-bottom: 6px; letter-spacing: 0.5px; }
        .plan-limits { color: #94a3b8; font-size: 11px; margin-top: 6px; font-family: monospace; font-weight: 300; }
        .w-input-group { margin-bottom: 18px; text-align: <?php echo ($lang=='ar')?'right':'left'; ?>; width: 100%; }
        .w-input-group label { display: block; color: #cbd5e1; font-size: 12px; margin-bottom: 8px; font-weight: 300; letter-spacing: 0.3px; }
        .w-input-group input { width: 100%; padding: 14px 16px; background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 12px; box-sizing: border-box; outline: none; transition: 0.3s; font-family: monospace; font-weight: 300; font-size: 14px; }
        .w-input-group input:focus { border-color: var(--gold); box-shadow: 0 0 10px rgba(212,175,55,0.15); background: rgba(15,23,42,0.9); }
        .w-btn-submit { width: 100%; padding: 14px 20px; background: linear-gradient(135deg, rgba(212,175,55,0.9), rgba(184,134,11,0.9)); border: 1px solid rgba(255,255,255,0.05); border-radius: 30px; color: #000; font-weight: 500; font-size: 13px; cursor: pointer; transition: all 0.3s ease; }
        .w-btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(212,175,55,0.25); background: linear-gradient(135deg, #d4af37, #b8860b); }
        .tx-item { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .tx-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; font-weight: 300; }
        .tx-icon.dep { background: rgba(16, 185, 129, 0.08); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        .tx-icon.wit { background: rgba(239, 68, 68, 0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .tx-info { flex: 1; padding: 0 15px; text-align: <?php echo ($lang=='ar')?'right':'left'; ?>; overflow: hidden; }
        .tx-title { font-size: 13px; font-weight: 400; color: #f1f5f9; letter-spacing: 0.3px; }
        .tx-ref { font-size: 10px; color: #64748b; font-family: monospace; font-weight: 300; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 4px; direction: ltr; text-align: left; }
        .tx-amount-box { text-align: <?php echo ($lang=='ar')?'left':'right'; ?>; flex-shrink: 0; }
        .tx-amount { font-size: 14px; font-weight: 400; font-family: monospace; letter-spacing: 0.5px; }
        .tx-amount.dep { color: #10b981; }
        .tx-amount.wit { color: #ef4444; }
        .tx-date { font-size: 10px; color: #64748b; margin-top: 4px; font-family: monospace; font-weight: 300; }
        .tx-status { font-size: 10px; padding: 3px 8px; border-radius: 12px; display: inline-block; margin-top: 5px; font-weight: 400; letter-spacing: 0.3px; }
        .tx-status.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
        .tx-status.completed { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        .ref-container { background: rgba(30,41,59,0.3); border: 1px dashed rgba(212,175,55,0.3); border-radius: 16px; padding: 20px; margin-bottom: 25px; }
        .ref-input-group { display: flex; gap: 10px; background: rgba(15,23,42,0.8); padding: 6px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); margin-top: 12px; }
        .ref-input-group input { flex: 1; background: transparent; border: none; color: #10b981; padding: 10px; font-family: monospace; outline: none; text-align: center; font-weight: 300; }
        .btn-copy { background: linear-gradient(135deg, #d4af37, #b8860b); color: #000; border: none; padding: 0 20px; border-radius: 8px; font-weight: 500; cursor: pointer; letter-spacing: 0.5px; transition: 0.3s; }
        .btn-copy:hover { filter: brightness(1.1); transform: scale(1.02); }
        .referral-bonus-card { background: linear-gradient(135deg, rgba(212,175,55,0.1), rgba(15,23,42,0.8)); border: 1px solid var(--gold); border-radius: 16px; padding: 15px 20px; margin-bottom: 25px; display: flex; flex-direction: column; gap: 15px; }
        .bonus-info h4 { margin: 0 0 5px; color: var(--gold); font-weight: 500; }
        .btn-team { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 8px 20px; border-radius: 30px; text-decoration: none; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; align-self: flex-start; }
        .social-icons-row { display: flex; justify-content: center; gap: 20px; margin-top: 15px; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; }
        .social-icon-link { color: #cbd5e1; font-size: 22px; opacity: 0.7; transition: all 0.3s ease; }
        .social-icon-link:hover { opacity: 1; transform: scale(1.2); color: var(--gold); }
        #toast { visibility: hidden; min-width: 250px; background: rgba(15,23,42,0.95); color: #fff; text-align: center; border-radius: 30px; padding: 14px 20px; position: fixed; z-index: 100000; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 13px; font-weight: 300; border: 1px solid rgba(212,175,55,0.4); backdrop-filter: blur(5px); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }
    </style>
</head>
<body>
<div id="toast"></div>
<div class="container dashboard-container">
    <div class="user-header">
        <div>
            <span style="color:#94a3b8; font-size:12px; font-weight: 300; letter-spacing: 0.5px;"><?php echo d_lang('welcome'); ?></span> 
            <span style="color:var(--gold); font-weight:400; font-size:16px; letter-spacing: 0.5px;"><?php echo htmlspecialchars($user['username']); ?></span>
        </div>
        <div class="notif-wrapper">
            <div class="notif-icon" onclick="toggleNotif()">
                <i class="fa-regular fa-bell" style="opacity: 0.9;"></i>
                <span class="notif-badge" style="display: <?php echo $has_unread ? 'block' : 'none'; ?>;"></span>
            </div>
        </div>
    </div>

    <div class="notif-overlay" id="main-overlay" onclick="closeAllModals()"></div>
    
    <div class="notif-modal" id="otp-modal" style="z-index:4000;">
        <div style="padding: 25px; background: #1e293b; border-radius: 20px; border: 1px solid var(--gold); max-width: 400px; margin:auto; text-align:center;">
            <h3 style="color:var(--gold); margin-top:0; font-weight:400;"><i class="fa-solid fa-shield-halved"></i> <?php echo ($lang=='ar')?'التحقق الأمني':'Security OTP'; ?></h3>
            <p style="color:#94a3b8; font-size:12px; margin-bottom:20px;">
                <?php echo ($lang=='ar')?'تم إرسال رمز التحقق إلى بريدك الإلكتروني. يرجى إدخاله أدناه لإتمام العملية.':'An OTP code has been sent to your email. Please enter it below.'; ?>
            </p>
            <input type="text" id="otp-input" placeholder="<?php echo ($lang=='ar')?'أدخل الرمز':'Enter 6-digit Code'; ?>" style="width:100%; padding:14px; background:rgba(15,23,42,0.8); border:1px solid rgba(212,175,55,0.5); color:#10b981; border-radius:10px; text-align:center; font-size:18px; letter-spacing:5px; margin-bottom:15px; outline:none;">
            <input type="hidden" id="otp-action-type">
            <button class="w-btn-submit" onclick="submitOtp()" id="btn-submit-otp" style="background:linear-gradient(135deg, #10b981, #059669); color:#fff; border-radius:25px;">
                <?php echo ($lang=='ar')?'تأكيد الرمز':'Verify OTP'; ?>
            </button>
            <button class="w-btn-submit" style="background:transparent; color:#cbd5e1; margin-top:10px; border:1px solid rgba(255,255,255,0.1); border-radius: 25px;" onclick="closeAllModals()">
                <?php echo ($lang=='ar')?'إلغاء':'Cancel'; ?>
            </button>
        </div>
    </div>

    <div class="notif-modal" id="notif-modal" style="z-index:3001;">
        <div class="notif-header">
            <span><?php echo d_lang('notif_title'); ?></span>
            <span class="close-notif" onclick="closeAllModals()"><i class="fa-solid fa-xmark"></i></span>
        </div>
        <div class="notif-list">
            <?php 
            if(count($notifications) > 0) { foreach($notifications as $n) { echo "<div class='notif-item'>{$n['message']}</div>"; } } else { echo "<div class='notif-item' style='text-align:center; color:#777;'>" . d_lang('no_notif') . "</div>"; } 
            ?>
        </div>
    </div>
    
    <div class="notif-modal" id="reinvest-modal" style="z-index:3001;">
        <div style="padding: 25px; background: #1e293b; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.3); max-width: 400px; margin:auto;">
            <h3 style="color:#10b981; margin-top:0; text-align:center; font-weight: 400; letter-spacing: 0.5px;">
                <i class="fa-solid fa-recycle"></i> <?php echo ($lang=='ar')?'تأكيد التدوير التقني':'Confirm Technical Recycling'; ?>
            </h3>
            <p style="font-size:11px; color:#94a3b8; text-align:center; margin-bottom:18px; font-weight: 300;">
                <?php echo ($lang=='ar')?'قم بإعادة توجيه البيانات التفاعلية لتوسيع الطاقة التشغيلية.<br>(تأكد من تحديث الخدمة عند الاقتراب من الحد)':'Redirect interactive data to expand operational capacity.<br>(Ensure service update when approaching the limit)'; ?>
            </p>
            <div class="w-input-group">
                <label><?php echo ($lang=='ar')?'الحجم المستهدف للتدوير (الأقصى: ':'Target Recycling Volume (Max: '; ?><?php echo money($profit_balance); ?>)</label>
                <input type="number" id="reinvest-amount" max="<?php echo $profit_balance; ?>" value="<?php echo $profit_balance; ?>">
            </div>
            <button class="w-btn-submit" id="btn-reinvest-req" style="background:linear-gradient(135deg, #10b981, #059669); color:#fff; border-radius: 25px;" onclick="requestReinvest()">
                <?php echo ($lang=='ar')?'تأكيد التدوير':'Confirm Recycling'; ?>
            </button>
            <button class="w-btn-submit" style="background:transparent; color:#cbd5e1; margin-top:10px; border:1px solid rgba(255,255,255,0.1); border-radius: 25px;" onclick="closeAllModals()">
                <?php echo ($lang=='ar')?'تراجع':'Back'; ?>
            </button>
        </div>
    </div>

    <div class="notif-modal" id="upgrade-modal" style="z-index:3001;">
        <div style="padding: 25px; background: #1e293b; border-radius: 20px; border: 1px solid rgba(59, 130, 246, 0.3); max-width: 400px; margin:auto;">
            <h3 style="color:#3b82f6; margin-top:0; text-align:center; font-weight: 400; letter-spacing: 0.5px;">
                <i class="fa-solid fa-arrow-trend-up"></i> <?php echo d_lang('upgrade_txt'); ?>
            </h3>
            <p style="font-size:12px; color:#e2e8f0; text-align:center; background:rgba(59,130,246,0.05); padding:10px; border-radius:10px; border:1px solid rgba(59,130,246,0.2); font-weight: 300; line-height: 1.6;">
                <span style="color:#94a3b8;"><?php echo ($lang=='ar') ? 'السعة التشغيلية المؤهلة (الأساسية + الإضافية):' : 'Eligible Operational Capacity (Base + Additional):'; ?></span> <br>
                <b style="color:#3b82f6; font-family: monospace; font-size: 16px; font-weight: 500;"><?php echo money($combined_capital); ?></b>
            </p>
            <div style="max-height: 300px; overflow-y: auto; margin-top:15px; padding-right:5px;">
                <?php foreach($plansList as $pl): 
                    $p_min = (float)$pl['min_price']; $can_upgrade = ($combined_capital >= $p_min);
                    $btn_html = $can_upgrade ? '<button onclick="submitUpgrade('.$pl['id'].')" style="background:linear-gradient(135deg, #3b82f6, #2563eb); color:#fff; border:none; padding:6px 14px; border-radius:20px; font-size:11px; cursor:pointer; font-weight:400; letter-spacing:0.5px;">'.($lang=='ar'?'تحديث':'Update').'</button>' : '<span style="color:#ef4444; font-size:10px; font-weight:300;">'.($lang=='ar'?'غير مؤهل':'Ineligible').'</span>';
                ?>
                <div class="plan-card-ui" style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div class="plan-title" style="color:#60a5fa; font-size: 14px; margin-bottom: 3px;"><?php echo htmlspecialchars($pl['name']); ?></div>
                        <div style="font-size:11px; color:#94a3b8; font-family: monospace; font-weight: 300;">Min: <?php echo num($p_min); ?></div>
                    </div>
                    <div><?php echo $btn_html; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="w-btn-submit" style="background:transparent; color:#cbd5e1; margin-top:15px; border:1px solid rgba(255,255,255,0.1); border-radius: 25px;" onclick="closeAllModals()">
                <?php echo ($lang=='ar')?'إغلاق':'Close'; ?>
            </button>
        </div>
    </div>

    <div class="notif-modal" id="deposit-modal" style="z-index:3001; max-width:400px; padding:0; border:none; background:transparent;">
        <div style="background: #1e293b; width: 100%; max-width: 400px; padding: 30px 25px; border-radius: 20px; border: 1px solid rgba(212,175,55,0.3); position: relative; margin:auto; box-sizing: border-box; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <span class="close-notif" onclick="closeAllModals()" style="position:absolute; top:18px; right:18px; font-size:20px; color:#94a3b8; cursor:pointer; transition:0.3s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">&times;</span>
            <div id="step-1-plans">
                <h3 style="color:var(--gold); margin-top:0; text-align:center; font-weight: 400; letter-spacing: 0.5px;">
                    <i class="fa-solid fa-box-open" style="opacity: 0.8;"></i> <?php echo ($lang=='ar') ? 'اختر الخدمة المناسبة' : 'Choose Service Plan'; ?>
                </h3>
                <p style="font-size:12px; color:#94a3b8; text-align:center; margin-bottom:20px; font-weight: 300; letter-spacing: 0.3px; line-height: 1.5;">
                    <?php echo ($lang=='ar') ? 'اطلع على تفاصيل الخدمات واختر ما يناسب احتياجاتك.' : 'Explore service details and choose what fits your needs.'; ?>
                </p>
                <div style="max-height: 350px; overflow-y: auto; padding-right: 5px;">
                    <?php if(!empty($plansList)): ?>
                        <?php $count = count($plansList); foreach($plansList as $i => $pl):
                            $p_min = (float)$pl['min_price'];
                            if (isset($pl['max_price']) && $pl['max_price'] > 0) { $p_max = (float)$pl['max_price']; } else { $p_max = ($i < $count - 1) ? (float)$plansList[$i+1]['min_price'] - 1 : -1; }
                            $maxStr = ($p_max == -1) ? '+' : num($p_max);
                        ?>
                        <div class="plan-card-ui" onclick="goToStep2(<?php echo $p_min; ?>, '<?php echo addslashes($pl['name']); ?>', <?php echo $pl['id']; ?>)">
                            <div class="plan-title"><?php echo htmlspecialchars($pl['name']); ?></div>
                            <div class="plan-limits"><?php echo ($lang=='ar')?'حدود الخدمة':'Service Limits'; ?>: <?php echo num($p_min); ?> - <?php echo $maxStr; ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; color:#aaa; font-size:12px; font-weight:300;">No services available at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="deposit-form-area" style="display:none;">
                <h3 style="color:var(--gold); margin-top:0; text-align:center; font-weight: 400; letter-spacing: 0.5px;">
                    <i class="fa-solid fa-hand-holding-dollar" style="opacity: 0.8;"></i> <?php echo ($lang=='ar') ? 'تحديد مبلغ الإدخال' : 'Set Input Amount'; ?>
                </h3>
                <div style="background:rgba(16, 185, 129, 0.05); border:1px solid rgba(16, 185, 129, 0.2); padding:12px; border-radius:10px; text-align:center; margin-bottom:18px; color:#34d399; font-size:13px; font-weight:400; letter-spacing: 0.3px;" id="selected-plan-name"></div>
                <div class="w-input-group">
                    <label><?php echo ($lang=='ar') ? 'المبلغ الأساسي المطلوب ($)' : 'Base Amount Required ($)'; ?></label>
                    <input type="number" id="dep-amount" step="1" placeholder="100">
                </div>
                <div style="display:flex; gap:12px;">
                    <button type="button" class="w-btn-submit" style="background:transparent; border:1px solid rgba(255,255,255,0.1); color:#cbd5e1;" onclick="backToStep1()">
                        <?php echo ($lang=='ar') ? 'رجوع للخدمات' : 'Back to Services'; ?>
                    </button>
                    <button type="button" class="w-btn-submit" id="btn-request-deposit" onclick="goToStep3()">
                        <?php echo ($lang=='ar') ? 'تأكيد الطلب' : 'Confirm Request'; ?>
                    </button>
                </div>
            </div>

            <div id="invoice-area" class="invoice-box" style="display:none;">
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px dashed #f59e0b; padding: 15px; border-radius: 10px; margin-bottom: 15px; text-align: center;">
                    <span style="color: #f59e0b; font-size: 13px; font-weight: bold;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo ($lang=='ar')?'تنبيه هام جداً':'CRITICAL WARNING'; ?></span><br>
                    <span style="color: #fff; font-size: 12px;"><?php echo ($lang=='ar')?'يجب إرسال هذا الرقم الدقيق (شاملاً الكسور) لكي يتم التعرف على طلبك تلقائياً. إذا كنت تستخدم منصة خارجية، أضف رسومهم لكي يصل الصافي دقيقاً!':'Please transfer this EXACT amount (including fractions) to auto-verify your input. If using an exchange, remember to add their withdrawal fee so the net received is exact!'; ?></span>
                </div>

                <div style="font-size:14px; color:var(--gold); font-weight:400; margin-bottom:12px; text-align:center;">
                    <?php echo ($lang=='ar') ? 'قم بتحويل المبلغ إلى هذه المحفظة:' : 'Transfer the amount to this wallet:'; ?>
                </div>
                <div style="text-align:center; margin-bottom:15px;">
                    <img id="inv-qr" src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=<?php echo urlencode($admin_wallet); ?>" style="width:130px; height:130px; border-radius:10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                </div>
                
                <div style="font-size:12px; color:#cbd5e1; margin-top:15px; margin-bottom:5px; text-align:center; font-weight:300;">
                    <?php echo ($lang=='ar') ? 'قم بإرسال المبلغ الدقيق:' : 'Send Exactly:'; ?>
                </div>
                <div class="invoice-amount" style="font-weight:bold; font-size: 22px; color:#10b981; text-align:center; font-family: monospace;">
                    <span id="inv-amt">0.000</span> <span style="font-size:14px; color:#94a3b8; font-weight:300;">USDT</span>
                </div>
                
                <div style="font-size:12px; color:#cbd5e1; margin-top:15px; margin-bottom:5px; text-align:left; direction:ltr; font-weight:300;">
                    <?php echo ($lang=='ar') ? 'إلى المحفظة (TRC20):' : 'To Address (TRC20):'; ?>
                </div>
                <div class="invoice-addr-box" style="background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 12px; font-weight: 300; display:flex; justify-content:space-between; align-items:center;">
                    <span id="inv-addr" style="color:#f1f5f9; font-size:11px; font-family:monospace; word-break:break-all;"><?php echo htmlspecialchars($admin_wallet); ?></span>
                    <i class="fa-regular fa-copy invoice-copy" onclick="copyInvoiceAddr()" style="opacity: 0.8; cursor:pointer; font-size:16px; margin-left:8px;" onmouseover="this.style.opacity='1'; this.style.color='var(--gold)'" onmouseout="this.style.opacity='0.8'; this.style.color='#fff'"></i>
                </div>
                
                <div style="margin-top: 15px; font-size: 12px; color: #cbd5e1; text-align: center; font-weight:300;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="color:var(--gold);"></i> <?php echo ($lang=='ar')?'النظام في انتظار وصول التحويل لتحديث السعة تلقائياً...':'System is waiting for the exact transfer to update capacity automatically...'; ?>
                </div>
                
                <div style="display:flex; gap:12px; margin-top:20px;">
                    <button type="button" class="w-btn-submit" onclick="closeAllModals()">
                        <?php echo ($lang=='ar') ? 'إغلاق ومتابعة لاحقاً' : 'Close & Wait'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="notif-overlay" id="history-overlay" onclick="closeAllModals()" style="z-index:3000;"></div>
    <div class="notif-modal" id="history-modal" style="z-index:3001; max-width:450px; padding:0; border:none; background:transparent;">
        <div style="background: #1e293b; width: 100%; max-width: 450px; padding: 25px; border-radius: 20px; border: 1px solid rgba(59, 130, 246, 0.3); position: relative; margin:auto; box-sizing: border-box; box-shadow: 0 20px 50px rgba(0,0,0,0.6);">
            <span class="close-notif" onclick="closeAllModals()" style="position:absolute; top:15px; right:15px; font-size:20px; color:#94a3b8; cursor:pointer;">&times;</span>
            <h3 style="color:#3b82f6; margin-top:0; text-align:center; font-weight: 400; letter-spacing: 0.5px;">
                <i class="fa-solid fa-clock-rotate-left" style="opacity:0.8;"></i> <?php echo d_lang('tx_title'); ?>
            </h3>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px; margin-top:20px;">
                <?php if(!empty($history)): ?>
                    <?php foreach($history as $tx):
                        $is_dep = ($tx['type'] == 'deposit'); $icon = $is_dep ? '<i class="fa-solid fa-arrow-down"></i>' : '<i class="fa-solid fa-arrow-up"></i>';
                        $title = $is_dep ? (($lang=='ar') ? 'إدخال تشغيلي' : 'Operational Input') : (($lang=='ar') ? 'تصدير الموارد' : 'Export Resources'); 
                        $amount_class = $is_dep ? 'dep' : 'wit'; $sign = $is_dep ? '+' : '-';
                        $st = $tx['status']; if(in_array($st, ['completed', 'approved', 'paid'])) { $status_class = 'completed'; $status_text = ($lang=='ar')?'مكتمل':'Completed'; } elseif ($st == 'rejected') { $status_class = 'wit'; $status_text = ($lang=='ar')?'مرفوض':'Rejected'; } else { $status_class = 'pending'; $status_text = ($lang=='ar')?'قيد المعالجة':'Processing'; }
                    ?>
                    <div class="tx-item">
                        <div style="display:flex; align-items:center; flex:1; overflow:hidden;">
                            <div class="tx-icon <?php echo $amount_class; ?>"><?php echo $icon; ?></div>
                            <div class="tx-info">
                                <div class="tx-title"><?php echo $title; ?></div>
                                <div class="tx-ref"><?php echo htmlspecialchars($tx['ref'] ?? 'Pending TX'); ?></div>
                            </div>
                        </div>
                        <div class="tx-amount-box">
                            <div class="tx-amount <?php echo $amount_class; ?>"><?php echo $sign . money($tx['amount']); ?></div>
                            <div class="tx-status <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                            <div class="tx-date"><?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 40px 10px; color:#64748b; font-size:13px; font-weight: 300;">لا يوجد سجل عمليات بعد.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="split-section">
        <div class="tasks-box">
            <h4 style="margin-top:0; color:var(--gold); margin-bottom:12px; font-size: 14px; border-bottom:1px solid rgba(212,175,55,0.1); padding-bottom:8px; display:flex; align-items:center; justify-content:space-between; font-weight: 400; letter-spacing: 0.5px;">
                <span><?php echo d_lang('daily'); ?></span>
            </h4>
            <div class="task-item">
                <div>
                    <span class="task-txt"><?php echo d_lang('task1'); ?></span>
                    <div style="margin-top: 4px;">
                        <?php if(!$is_4pm_done): ?> <span id="timer-task1"></span> <?php endif; ?>
                    </div>
                </div>
                <?php if($is_4pm_done): ?>
                    <button class="btn-task btn-done"><i class="fa-solid fa-check"></i> <?php echo d_lang('done'); ?></button>
                <?php elseif($show_task_1): ?>
                    <button onclick="executeTask('task1')" class="btn-task btn-active" id="btn-task1"><?php echo d_lang('act'); ?></button>
                <?php else: ?>
                    <button class="btn-task btn-lock"><?php echo d_lang('market_closed'); ?></button>
                <?php endif; ?>
            </div>
            
            <div class="task-item">
                <div>
                    <span class="task-txt"><?php echo d_lang('task2'); ?></span>
                    <div style="margin-top: 4px;">
                        <?php if(!$is_8pm_done): ?> <span id="timer-task2"></span> <?php endif; ?>
                    </div>
                    <?php if(!$is_4pm_done && $show_task_2): ?>
                        <span style="display:block; font-size:10px; color:#ef4444; margin-top:4px; font-weight: 300;"><?php echo d_lang('req_task1'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if($is_8pm_done): ?>
                    <button class="btn-task btn-done"><i class="fa-solid fa-check"></i> <?php echo d_lang('done'); ?></button>
                <?php elseif($show_task_2): ?>
                    <?php if ($is_4pm_done): ?>
                        <button onclick="executeTask('task2')" class="btn-task btn-active" id="btn-task2"><?php echo d_lang('claim'); ?></button>
                    <?php else: ?>
                        <button class="btn-task btn-lock"><i class="fa-solid fa-lock" style="opacity: 0.7;"></i></button>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn-task btn-lock"><?php echo d_lang('lock'); ?></button>
                <?php endif; ?>
            </div>
            
            <div class="today-profit-box <?php echo $box_class; ?>">
                <div><div class="profit-label"><?php echo d_lang('daily_earn'); ?></div></div>
                <div class="profit-value">
                    <?php if($is_4pm_done && $is_8pm_done): ?>
                        <span style="color:#10b981; font-size:18px;"><?php echo money($earned_amount); ?></span>
                    <?php else: ?>
                        <span style="color:#64748b; font-size:15px;">0.00</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- بطاقات المال -->
    <div class="finance-stack">
        <div class="fin-card plan">
            <div>
                <div class="fin-lbl"><?php echo d_lang('current_plan'); ?>: <span style="color:var(--gold); font-weight: 400;"><?php echo htmlspecialchars($user_plan_name); ?></span></div>
                <?php if($bonus_capital > 0): ?>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 5px; font-family: monospace;">
                    <?php echo d_lang('active_cap'); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="fin-val" style="color:var(--gold);"><?php echo money($active_capital); ?></div>
        </div>
        
        <div class="fin-card capital">
            <div>
                <div class="fin-lbl"><?php echo d_lang('cap_bal'); ?></div>
            </div>
            <div class="fin-val" style="color:#3b82f6;"><?php echo money($total_balance); ?></div>
        </div>
        
        <div class="fin-card profit">
            <div>
                <div class="fin-lbl"><?php echo d_lang('prof_bal'); ?></div>
                <?php if($profit_balance > 0): ?>
                    <button class="btn-reinvest" onclick="requestReinvest()">
                        <i class="fa-solid fa-rotate" style="opacity: 0.8;"></i> <?php echo d_lang('reinvest_txt'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="fin-val" style="color:#10b981;"><?php echo money($profit_balance); ?></div>
        </div>
    </div>
    
    <div class="my-wallet-section">
        <div class="my-wallet-title">
            <span><i class="fa-solid fa-wallet" style="color:var(--gold); opacity: 0.8;"></i> <?php echo ($lang=='ar') ? 'إعدادات العنوان' : 'Wallet Settings'; ?></span>
        </div>
        <div class="my-wallet-input-box" style="margin-bottom:0;">
            <input type="text" id="my-personal-wallet" value="<?php echo htmlspecialchars($user['personal_wallet'] ?? ''); ?>" placeholder="<?php echo ($lang=='ar') ? 'الصق عنوان محفظتك هنا...' : 'Paste your withdrawal wallet address here...'; ?>">
            <button onclick="savePersonalWallet()" class="my-wallet-btn" id="btn-save-wallet"><?php echo ($lang=='ar') ? 'حفظ' : 'Save'; ?></button>
            <button onclick="copyMyWallet()" class="my-wallet-btn" style="background:rgba(255,255,255,0.05); color:#fff; border: 1px solid rgba(255,255,255,0.1);" title="نسخ">
                <i class="fa-regular fa-copy" style="opacity: 0.8;"></i>
            </button>
        </div>
    </div>
    
    <div class="action-grid">
        <a href="#" onclick="openModal('deposit-modal'); return false;" class="btn-action-card" style="border-color:rgba(212, 175, 55, 0.3);">
            <i class="fa-solid fa-circle-plus" style="color:var(--gold);"></i>
            <span style="color:var(--gold);"><?php echo d_lang('deposit_txt'); ?></span>
        </a>
        <a href="withdraw.php" class="btn-action-card" style="border-color:rgba(16, 185, 129, 0.3);">
            <i class="fa-solid fa-money-bill-transfer" style="color:#10b981;"></i>
            <span style="color:#10b981;"><?php echo d_lang('withdraw_txt'); ?></span>
        </a>
        <a href="#" onclick="openModal('upgrade-modal'); return false;" class="btn-action-card" style="border-color:rgba(59, 130, 246, 0.3);">
            <i class="fa-solid fa-arrow-trend-up" style="color:#3b82f6;"></i>
            <span style="color:#3b82f6;"><?php echo d_lang('upgrade_txt'); ?></span>
        </a>
        <a href="#" onclick="openModal('history-modal'); return false;" class="btn-action-card" style="border-color:rgba(148, 163, 184, 0.3);">
            <i class="fa-solid fa-clock-rotate-left" style="color:#94a3b8;"></i>
            <span style="color:#94a3b8;"><?php echo d_lang('tx_title'); ?></span>
        </a>
    </div>
    
    <div class="referral-bonus-card">
        <div class="bonus-info">
            <div style="margin-bottom: 10px; background: rgba(59,130,246,0.1); padding: 8px 12px; border-radius: 8px; border-right: 2px solid #3b82f6;">
                <span style="font-size: 12px; color: #cbd5e1;"><?php echo ($lang=='ar') ? 'إجمالي النشاط التفاعلي:' : 'Team Total Deposits:'; ?> <strong style="color:#3b82f6; font-family: monospace; font-size: 14px;"><?php echo money($team_total_deposits); ?></strong></span>
            </div>

            <div style="margin-top: 8px;">
                <span style="background: rgba(212,175,55,0.2); padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                    <?php echo ($lang=='ar') ? 'أعضاء متفاعلون: ' : 'Active Account Count: '; ?> <strong><?php echo min($active_referrals, REFERRAL_TARGET); ?></strong> / <?php echo REFERRAL_TARGET; ?>
                    <?php if($active_referrals >= REFERRAL_TARGET) echo ($lang=='ar' ? '(كامل)' : '(Completed)'); ?>
                </span>
                <div style="width: 100%; background: #334155; border-radius: 10px; margin-top: 8px; height: 6px;">
                    <div style="width: <?php echo min(100, ($active_referrals/REFERRAL_TARGET)*100); ?>%; background: var(--gold); height: 6px; border-radius: 10px;"></div>
                </div>
            </div>
            <?php if($bonus_activated): ?>
                <div style="margin-top: 10px; background: rgba(16,185,129,0.1); padding: 8px; border-radius: 8px; border-right: 2px solid #10b981;">
                    <span style="color: #10b981;">✅ <?php echo ($lang=='ar') ? 'تم تفعيل الهدية!' : 'Bonus activated!'; ?></span><br>
                    <span style="font-size: 12px;"><?php echo ($lang=='ar') ? 'قوة التفاعل الإضافية:' : 'Additional trading power:'; ?> <strong><?php echo money($bonus_capital); ?></strong></span>
                </div>
            <?php endif; ?>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <a href="my_team.php" class="btn-team"><i class="fa-solid fa-people-group"></i> <?php echo ($lang=='ar') ? 'عائلتي' : 'My Family'; ?></a>
            <div class="social-icons-row" style="margin-top: 0; border-top: none; padding-top: 0;">
                <a href="#" onclick="shareWX('whatsapp'); return false;" class="social-icon-link"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="#" onclick="shareWX('telegram'); return false;" class="social-icon-link"><i class="fa-brands fa-telegram"></i></a>
                <a href="#" onclick="shareWX('facebook'); return false;" class="social-icon-link"><i class="fa-brands fa-facebook"></i></a>
                <a href="#" onclick="shareWX('snapchat'); return false;" class="social-icon-link"><i class="fa-brands fa-snapchat"></i></a>
            </div>
        </div>
    </div>
    
    <div class="ref-container">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="lang-txt" style="color:#fff; font-weight:400; letter-spacing: 0.5px;"><?php echo d_lang('ref'); ?></span>
        </div>
        <div class="ref-input-group">
            <input type="text" value="<?php echo $my_referral_link; ?>" id="refLink" readonly>
            <button onclick="copyRef()" class="btn-copy"><?php echo d_lang('copy'); ?></button>
        </div>
    </div>
</div>

<script>
    let userCurrentCapital = <?php echo (float)$combined_capital; ?>;
    let selectedPlanId = 0; 
    let depositPollInterval = null;
    
    function showToast(msg) { var x = document.getElementById("toast"); x.innerHTML = msg; x.className = "show"; setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000); }
    function copyMyWallet() { var copyText = document.getElementById("my-personal-wallet"); copyText.select(); navigator.clipboard.writeText(copyText.value); showToast('<?php echo ($lang=="ar") ? "تم النسخ!" : "Copied!"; ?>'); }
    function copyRef() { var copyText = document.getElementById("refLink"); copyText.select(); navigator.clipboard.writeText(copyText.value); showToast('<?php echo ($lang=="ar")?"تم النسخ!":"Copied!"; ?>'); }
    function copyInvoiceAddr() { var t = document.getElementById("inv-addr").innerText; navigator.clipboard.writeText(t); showToast('<?php echo ($lang=="ar")?"تم نسخ المحفظة!":"Wallet Copied!"; ?>'); }
    
    function savePersonalWallet() {
        const wallet = document.getElementById('my-personal-wallet').value.trim(); const btn = document.getElementById('btn-save-wallet');
        if (!wallet) { alert("<?php echo ($lang=='ar') ? 'يرجى إدخال المحفظة أولاً!' : 'Please enter a wallet address!'; ?>"); return; }
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; btn.disabled = true;
        const fd = new FormData(); fd.append('ajax_action', 'update_my_wallet'); fd.append('wallet', wallet);
        fetch('dashboard.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => { alert(data.msg); }).finally(() => { btn.innerHTML = '<?php echo ($lang=="ar")?"حفظ":"Save"; ?>'; btn.disabled = false; });
    }
    
    function openModal(id) { document.getElementById('main-overlay').style.display = 'block'; document.getElementById(id).style.display = 'block'; }
    function closeAllModals() { 
        document.getElementById('main-overlay').style.display = 'none'; 
        document.querySelectorAll('.notif-modal').forEach(m => m.style.display = 'none'); 
        if(depositPollInterval) clearInterval(depositPollInterval);
    }
    function toggleNotif() { const modal = document.getElementById('notif-modal'); if (modal.style.display === 'block') { closeAllModals(); } else { closeAllModals(); openModal('notif-modal'); } }
    
    function openOtpModal(actionType) {
        document.getElementById('otp-action-type').value = actionType;
        document.getElementById('otp-input').value = '';
        closeAllModals();
        openModal('otp-modal');
    }

    function submitOtp() {
        const action = document.getElementById('otp-action-type').value;
        const otp = document.getElementById('otp-input').value.trim();
        if(!otp) { alert("<?php echo ($lang=='ar')?'يرجى إدخال الرمز!':'Please enter OTP!'; ?>"); return; }
        
        const btn = document.getElementById('btn-submit-otp');
        const ogHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; btn.disabled = true;
        
        const fd = new FormData();
        fd.append('otp', otp);
        
        let endpointAction = '';
        if(action === 'reinvest') endpointAction = 'confirm_reinvest_otp';

        fd.append('ajax_action', endpointAction);
        
        fetch('dashboard.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
             if(data.status === 'success') { alert(data.msg); window.location.reload(); } 
             else { alert(data.msg); }
        }).finally(()=>{ btn.innerHTML = ogHtml; btn.disabled = false; });
    }

    function executeTask(taskType) {
        const btn = document.getElementById('btn-'+taskType); 
        const og = btn.innerHTML; 
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; 
        btn.disabled = true;
        
        const fd = new FormData(); 
        fd.append('ajax_action', 'execute_task'); 
        fd.append('task_type', taskType);
        
        fetch('dashboard.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            alert(data.msg);
            if(data.status === 'success') {
                window.location.reload();
            }
        }).catch(err => {
            alert("<?php echo ($lang=='ar')?'حدث خطأ في الاتصال!':'Connection error occurred!'; ?>");
        }).finally(() => { 
            btn.innerHTML = og; 
            btn.disabled = false; 
        });
    }

    function requestReinvest() {
        const amt = document.getElementById('reinvest-amount').value; 
        if(!amt || amt <= 0) return;
        
        if(!confirm("<?php echo ($lang=='ar')?'هل أنت متأكد من رغبتك بإعادة توجيه البيانات التفاعلية؟':'Are you sure you want to redirect interactive data?'; ?>")) return;

        const btn = document.getElementById('btn-reinvest-req'); 
        const og = btn.innerHTML; 
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; 
        btn.disabled=true;
        
        const fd = new FormData(); 
        fd.append('ajax_action', 'process_reinvest'); 
        fd.append('amount', amt);
        
        fetch('dashboard.php', { method: 'POST', body: fd })
        .then(res=>res.json())
        .then(data=>{
            alert(data.msg);
            if(data.status === 'success') window.location.reload();
        }).finally(()=>{ btn.innerHTML = og; btn.disabled=false; });
    }

    function submitUpgrade(plan_id) {
        if(confirm("<?php echo ($lang=='ar')?'هل أنت متأكد من تحديث كفاءة النظام؟':'Confirm system efficiency update?'; ?>")) {
            const fd = new FormData(); fd.append('ajax_action', 'upgrade_plan'); fd.append('plan_id', plan_id);
            fetch('dashboard.php', { method: 'POST', body: fd }).then(res=>res.json()).then(data=>{alert(data.msg); window.location.reload();});
        }
    }
    
    function goToStep2(min, name, planId) {
        selectedPlanId = planId; let req = min - userCurrentCapital; if(req <= 0) req = 10;
        document.getElementById('step-1-plans').style.display = 'none'; document.getElementById('deposit-form-area').style.display = 'block';
        document.getElementById('dep-amount').min = req; document.getElementById('dep-amount').value = req;
        let msg = "<?php echo ($lang=='ar')?'الخدمة المختارة: ':'Selected Service: '; ?>" + "<span style='color:#fff;'>" + name + "</span>";
        if (userCurrentCapital > 0 && min > userCurrentCapital) { msg += "<br><span style='color:#ef4444; font-size:11px; margin-top:5px; display:block; font-weight:300;'>(" + "<?php echo ($lang=='ar')?'تحديث - تحتاج إدخال إضافي كحد أدنى':'Update - Minimum additional input required'; ?>" + " " + req + ")</span>"; }
        document.getElementById('selected-plan-name').innerHTML = msg;
    }
    
    function goToStep3() {
        const amtIn = document.getElementById('dep-amount'); 
        const baseAmount = parseFloat(amtIn.value); 
        const min = parseFloat(amtIn.min);
        
        if (!baseAmount || baseAmount < min) { alert("<?php echo ($lang=='ar' ? 'المبلغ يجب أن يكون على الأقل ' : 'Amount must be at least '); ?>" + min); return; }
        
        const btn = document.getElementById('btn-request-deposit'); 
        const originalHtml = btn.innerHTML; 
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; 
        btn.disabled = true;

        const fd = new FormData(); 
        fd.append('ajax_action', 'request_deposit'); 
        fd.append('amount', baseAmount); 
        fd.append('plan_id', selectedPlanId);
        
        fetch('dashboard.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => { 
            if (data.status === 'success') { 
                document.getElementById('deposit-form-area').style.display = 'none'; 
                document.getElementById('invoice-area').style.display = 'block'; 
                document.getElementById('inv-amt').innerText = data.unique_amount;
                
                const dep_id = data.dep_id;
                depositPollInterval = setInterval(() => {
                    const fdCheck = new FormData();
                    fdCheck.append('ajax_action', 'check_deposit_status');
                    fdCheck.append('dep_id', dep_id);
                    fetch('dashboard.php', { method: 'POST', body: fdCheck })
                    .then(r => r.json())
                    .then(resData => {
                        if (resData.status === 'completed') {
                            clearInterval(depositPollInterval);
                            alert("<?php echo ($lang=='ar')?'🎉 تم تأكيد الإدخال بنجاح وتحديث السعة التشغيلية!':'🎉 Input confirmed and operational capacity updated!'; ?>");
                            window.location.reload();
                        }
                    });
                }, 10000); 

            } else { alert(data.msg); } 
        }).catch(err => {
             alert("<?php echo ($lang=='ar')?'حدث خطأ في الاتصال!':'Connection error occurred!'; ?>");
        }).finally(() => { btn.innerHTML = originalHtml; btn.disabled = false; });
    }
    
    function backToStep1() { document.getElementById('deposit-form-area').style.display = 'none'; document.getElementById('step-1-plans').style.display = 'block'; selectedPlanId=0; }
    function backToStep2() { document.getElementById('invoice-area').style.display = 'none'; document.getElementById('deposit-form-area').style.display = 'block'; }
    
    function shareWX(platform) {
        var refLink = document.getElementById("refLink").value; var messageBody = "<?php echo ($lang=='ar') ? 'انضم إلي في منصة Wealth Excel.' : 'Join me on Wealth Excel.'; ?>";
        var fullText = messageBody + "\n\n" + refLink; var finalUrl = "";
        if (platform === 'whatsapp') finalUrl = "https://wa.me/?text=" + encodeURIComponent(fullText);
        else if (platform === 'telegram') finalUrl = "https://t.me/share/url?url=" + encodeURIComponent(refLink) + "&text=" + encodeURIComponent(messageBody);
        else if (platform === 'facebook') finalUrl = "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(refLink) + "&quote=" + encodeURIComponent(messageBody);
        else if (platform === 'snapchat') finalUrl = "https://www.snapchat.com/scan?attachmentUrl=" + encodeURIComponent(refLink);
        if(finalUrl) window.open(finalUrl, '_blank');
    }
    
    function startTaskTimers() {
        const timer1 = document.getElementById('timer-task1');
        const timer2 = document.getElementById('timer-task2');
        const isAr = "<?php echo $lang; ?>" === "ar";
        
        const task1Start = 15 * 60 + 30;
        const task1End = 17 * 60 + 30;
        const task2Start = 18 * 60 + 30;
        const task2End = 21 * 60 + 30;
        
        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${hours > 0 ? hours + 'h ' : ''}${minutes}m ${secs}s`;
        }
        
        function getTimerHTML(taskStart, taskEnd) {
            const now = new Date();
            const nowMinutes = now.getUTCHours() * 60 + now.getUTCMinutes();
            const nowSecs = now.getUTCHours() * 3600 + now.getUTCMinutes() * 60 + now.getUTCSeconds();
            const startSecs = (Math.floor(taskStart / 60) * 3600) + ((taskStart % 60) * 60);
            const endSecs = (Math.floor(taskEnd / 60) * 3600) + ((taskEnd % 60) * 60);
            
            if (nowMinutes < taskStart) {
                const diff = startSecs - nowSecs;
                return `<div style="color:#ef4444; margin-top:4px; font-size:10px; font-family:monospace;"><i class="fa-regular fa-clock"></i> ${isAr ? 'يفتح خلال:' : 'Opens in:'} <span dir="ltr">${formatTime(diff)}</span></div>`;
            } else if (nowMinutes >= taskStart && nowMinutes < taskEnd) {
                const diff = endSecs - nowSecs;
                return `<div style="color:#10b981; margin-${isAr ? 'right' : 'left'}:8px; font-size:11px; font-weight:bold; font-family:monospace;"><i class="fa-solid fa-hourglass-half fa-spin-pulse"></i> <span dir="ltr">${formatTime(diff)}</span></div>`;
            } else {
                const nextStartSecs = (24 * 3600 - nowSecs) + startSecs;
                return `<div style="color:#f59e0b; margin-top:4px; font-size:10px; font-family:monospace;"><i class="fa-solid fa-rotate-right"></i> ${isAr ? 'يفتح غداً خلال:' : 'Tomorrow in:'} <span dir="ltr">${formatTime(nextStartSecs)}</span></div>`;
            }
        }
        
        setInterval(() => {
            if (timer1) timer1.innerHTML = getTimerHTML(task1Start, task1End);
            if (timer2) timer2.innerHTML = getTimerHTML(task2Start, task2End);
        }, 1000);
    }
    document.addEventListener('DOMContentLoaded', startTaskTimers);
</script>
</body>
</html>