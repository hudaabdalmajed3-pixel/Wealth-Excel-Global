<?php
// api-admin.php - Full Complete Version (With KYC Data correctly mapped & Withdrawal Bugs Fixed)
error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    if (!file_exists('config.php')) throw new Exception("Config missing");
    require 'config.php';
    if (ob_get_length()) ob_clean();

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception("Unauthorized");
    }

    function response($status, $data=[], $msg='') {
        echo json_encode(['status'=>$status, 'data'=>$data, 'message'=>$msg]);
        exit;
    }

    function uploadFile($fileInputName) {
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] != 0) { return null; }
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
        
        $info = pathinfo($_FILES[$fileInputName]["name"]);
        $ext = strtolower($info['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'pdf', 'mov', 'avi', 'mkv'];
        
        if (!in_array($ext, $allowed)) return null;

        $fileName = uniqid('media_') . '.' . $ext;
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $targetFilePath)) {
            return $targetFilePath;
        }
        return null;
    }

    $action = $_REQUEST['action'] ?? '';
    $input  = $_POST;

    if(empty($action)) throw new Exception("No Action");

    switch($action) {
        case 'get_dashboard_stats':
            $u = $pdo->query("SELECT COUNT(*) FROM users WHERE role='investor'")->fetchColumn();
            $d = $pdo->query("SELECT SUM(amount) FROM auto_deposits WHERE status='completed'")->fetchColumn() ?: 0;
            $p_wd = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='max_investor_count'");
            $display = max($u, ($stmt->fetchColumn() ?: 0));
            response('success', ['online_users' => $u, 'total_deposits' => floatval($d), 'users' => $display, 'pending_count' => $p_wd]);
            break;

        case 'update_user_details':
            $id = intval($input['id']);
            $notes = $input['notes'] ?? '';
            $pdo->prepare("UPDATE users SET admin_notes=? WHERE id=?")->execute([$notes, $id]);
            response('success');
            break;

        case 'send_message':
            $user_id = intval($input['user_id']);
            $message = $input['message'];
            if(!empty($message)) { $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, $message]); }
            response('success');
            break;

        case 'freeze_user':
            $id = intval($input['id']);
            $pdo->prepare("UPDATE users SET account_status='frozen' WHERE id=?")->execute([$id]);
            $msg = "⚠️ تم تجميد حسابك مؤقتاً، يرجى التواصل مع الدعم الفني.\n⚠️ Your account has been temporarily frozen, please contact support.";
            $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$id, $msg]);
            response('success');
            break;

        case 'unfreeze_user':
            $id = intval($input['id']);
            $pdo->prepare("UPDATE users SET account_status='active' WHERE id=?")->execute([$id]);
            $msg = "✅ تم فك التجميد عن حسابك، يمكنك الآن استخدام المنصة بشكل طبيعي.\n✅ Your account has been reactivated, you can now use the platform normally.";
            $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$id, $msg]);
            response('success');
            break;

        case 'delete_user':
            $id = intval($input['id']);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            response('success');
            break;

        case 'get_investment_logs':
            $sql = "SELECT d.id, d.amount, d.status, d.created_at, u.username, u.balance as current_capital, d.txn_id as txid, p.name as plan_name 
                    FROM auto_deposits d 
                    JOIN users u ON d.user_id = u.id 
                    LEFT JOIN plans p ON u.plan_id = p.id
                    ORDER BY CASE WHEN d.status = 'pending' THEN 1 ELSE 2 END, d.created_at DESC LIMIT 100";
            
            $logs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($logs as &$log) {
                if (strpos($log['txid'], 'REINVEST') !== false) {
                    $log['txid'] = '<span style="color:#10b981; font-weight:bold;">إعادة استثمار ♻️</span>';
                    $log['plan_name'] = 'إضافة لرأس المال';
                }
            }
            response('success', $logs);
            break;
            
        case 'process_deposit':
            $id = intval($input['id']);
            $status = $input['status'];
            
            $stmt = $pdo->prepare("SELECT user_id, amount, status FROM auto_deposits WHERE id = ?");
            $stmt->execute([$id]);
            $inv = $stmt->fetch();
            
            if ($inv && $inv['status'] === 'pending') {
                $pdo->prepare("UPDATE auto_deposits SET status = ? WHERE id = ?")->execute([$status, $id]);
                
                if ($status === 'completed') {
                    $user_id = $inv['user_id'];
                    $amount = $inv['amount'];
                    
                    $current_bal = $pdo->query("SELECT balance FROM users WHERE id = $user_id")->fetchColumn();
                    $new_total = $current_bal + $amount;
                    
                    $plan_data = $pdo->query("SELECT id, max_price FROM plans WHERE min_price <= $new_total ORDER BY min_price DESC LIMIT 1")->fetch();
                    
                    if ($plan_data) {
                        $max_p = floatval($plan_data['max_price']);
                        $new_active = ($max_p == -1 || $max_p == 0) ? $new_total : min($new_total, $max_p);
                        $pdo->prepare("UPDATE users SET balance = ?, active_capital = ?, plan_id = ? WHERE id = ?")->execute([$new_total, $new_active, $plan_data['id'], $user_id]);
                    } else {
                        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$new_total, $user_id]);
                    }
                    
                    $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, "✅ تمت الموافقة على إيداعك بمبلغ $" . number_format($amount, 2) . " وتم تحديث رصيدك."]);
                } else {
                    $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$inv['user_id'], "❌ تم رفض طلب الإيداع الخاص بك، يرجى التأكد من البيانات أو مراجعة الدعم."]);
                }
            }
            response('success');
            break;

        case 'get_withdrawals':
            $sql = "SELECT w.id, w.user_id, w.amount, w.fee, w.net_amount, w.wallet_address, w.status, w.created_at, w.admin_notes, u.username 
                    FROM withdrawals w LEFT JOIN users u ON w.user_id = u.id ORDER BY CASE WHEN w.status = 'pending' THEN 1 ELSE 2 END, w.created_at DESC";
            response('success', $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
            break;

        // 🔴 التحديث الجذري لمعالجة السحوبات 🔴
        case 'process_withdrawal':
            $id = $_POST['id']; $status = $_POST['status'];
            $stmt = $pdo->prepare("SELECT user_id, amount, net_amount, admin_notes, status FROM withdrawals WHERE id = ?");
            $stmt->execute([$id]); $w = $stmt->fetch();
            
            if ($w && $w['status'] === 'pending') {
                $user_id = $w['user_id']; 
                $amount = $w['amount']; // المبلغ الأصلي قبل الخصم (مثل 500,000)
                $notes = $w['admin_notes'];
                $is_capital = (strpos($notes, 'رأس المال') !== false || strpos($notes, 'Capital') !== false);
                
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE withdrawals SET status = ? WHERE id = ?")->execute([$status, $id]);
                    
                    if ($status === 'approved') {
                        if ($is_capital) {
                            // تم إزالة السطر الذي يخصم المبلغ مرة أخرى لتجنب الرصيد السالب!
                            // نقوم فقط بتصفير الخطة وتنشيط الحساب في حال كان مجمداً
                            $pdo->prepare("UPDATE users SET plan_id = NULL, active_capital = 0, account_status = 'active' WHERE id = ?")->execute([$user_id]);
                            $msg_text = "✅ تمت الموافقة على سحب رأس المال الكلي وإغلاق الاشتراك.";
                        } else {
                            $msg_text = "✅ تمت الموافقة على سحب الأرباح وتحويل الصافي لمحفظتك.";
                        }
                    } elseif ($status === 'rejected') {
                        if ($is_capital) {
                            // 🔴 إصلاح الخطأ: إرجاع رأس المال كاملاً وتنشيط الحساب 🔴
                            $pdo->prepare("UPDATE users SET balance = balance + ?, account_status = 'active' WHERE id = ?")->execute([$amount, $user_id]);
                        } else {
                            // 🔴 إرجاع الأرباح كاملة 🔴
                            $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?")->execute([$amount, $user_id]);
                        }
                        $msg_text = "❌ تم رفض طلب السحب الخاص بك وتم إرجاع المبلغ إلى رصيدك.";
                    }
                    
                    $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$user_id, $msg_text]);
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw new Exception("Database Error during withdrawal process.");
                }
            }
            response('success');
            break;

        case 'get_investors': 
            $sql = "SELECT u.id, u.username, u.email, u.balance as capital, u.active_capital, p.name as plan_name, u.admin_notes, r.username as referrer_name, u.region, u.kyc_selfie, u.kyc_front, u.kyc_back 
                    FROM users u 
                    LEFT JOIN plans p ON u.plan_id = p.id 
                    LEFT JOIN users r ON u.referred_by = r.id 
                    WHERE u.role IN ('investor', 'user') 
                    ORDER BY u.id DESC";
            response('success', $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)); 
            break;

        case 'get_videos': 
            response('success', $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC)); 
            break;

        case 'add_video':
            $title = $input['title']; $desc = $input['desc']; $type = $input['type']; $path = '';
            if ($type == 'file') { $path = uploadFile('video_file'); if (!$path) throw new Exception("فشل رفع الفيديو."); } else { $path = $input['video_link']; }
            $pdo->prepare("INSERT INTO videos (title, description, filename, file_type, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$title, $desc, $path, $type]);
            response('success'); 
            break;

        case 'delete_video':
            $id = intval($input['id']); $v = $pdo->query("SELECT filename, file_type FROM videos WHERE id=$id")->fetch();
            if($v && $v['file_type']=='file' && file_exists($v['filename'])) { @unlink($v['filename']); }
            $pdo->prepare("DELETE FROM videos WHERE id=?")->execute([$id]); 
            response('success'); 
            break;

        case 'get_plans': 
            response('success', $pdo->query("SELECT * FROM plans ORDER BY min_price ASC")->fetchAll(PDO::FETCH_ASSOC)); 
            break;

        case 'update_plan': 
            $pdo->prepare("UPDATE plans SET name=?, min_price=?, max_price=?, roi_percentage=? WHERE id=?")->execute([$input['name'], $input['min_price'], $input['max_price'], $input['roi'], $input['id']]); 
            response('success'); 
            break;

        case 'add_plan': 
            $pdo->prepare("INSERT INTO plans (name, min_price, max_price, roi_percentage) VALUES (?,?,?,?)")->execute([$input['name'], $input['min_price'], $input['max_price'], $input['roi']]); 
            response('success'); 
            break;

        case 'delete_plan': 
            $pdo->prepare("DELETE FROM plans WHERE id=?")->execute([$input['id']]); 
            response('success'); 
            break;

        case 'get_academy': 
            response('success', $pdo->query("SELECT * FROM academy_topics ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC)); 
            break;

        case 'add_academy':
            $title = $input['title']; $desc = $input['desc'] ?? ''; $path = uploadFile('pdf_file'); 
            if (!$path) throw new Exception("فشل رفع الملف.");
            $pdo->prepare("INSERT INTO academy_topics (title, description, filename, created_at) VALUES (?, ?, ?, NOW())")->execute([$title, $desc, $path]); 
            response('success'); 
            break;

        case 'delete_academy':
            $id = intval($input['id']); $item = $pdo->query("SELECT filename FROM academy_topics WHERE id=$id")->fetch();
            if($item && file_exists($item['filename'])) { @unlink($item['filename']); }
            $pdo->prepare("DELETE FROM academy_topics WHERE id=?")->execute([$id]); 
            response('success'); 
            break;
        
        case 'get_profit_tracking': 
            $from = $input['from_date'] ?? date('Y-m-01'); $to = $input['to_date'] ?? date('Y-m-d');
            $sql = "SELECT u.id, u.username, p.name as plan_name, p.roi_percentage, u.profit_balance as profit_amount, (SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=u.id AND (status='paid' OR status='approved')) as withdrawals FROM users u LEFT JOIN plans p ON u.plan_id=p.id WHERE u.role='investor' OR u.role='user'"; 
            response('success', $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)); 
            break;

        case 'save_deposit_settings': 
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('deposit_wallet', ?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$input['address'], $input['address']]); 
            response('success'); 
            break;

        case 'save_ref_settings':
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ref_l1_pct', ?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$input['l1'], $input['l1']]);
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ref_l2_pct', ?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$input['l2'], $input['l2']]); 
            response('success'); 
            break;
            
        case 'get_referral_grid':
            $l1_pct = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ref_l1_pct'")->fetchColumn() ?: 15;
            $l2_pct = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='ref_l2_pct'")->fetchColumn() ?: 5;
            $sql = "SELECT u.id as inv_id, u.username as inv_name, u.balance as capital, p.name as plan_name, p.roi_percentage as plan_roi, l1.id as l1_id, l1.username as l1_name, l2.id as l2_id, l2.username as l2_name FROM users u JOIN users l1 ON u.referred_by = l1.id LEFT JOIN users l2 ON l1.referred_by = l2.id LEFT JOIN plans p ON u.plan_id = p.id WHERE u.balance > 0 AND u.plan_id IS NOT NULL AND u.role != 'admin' ORDER BY l1.id DESC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            foreach($rows as $row) {
                $monthly_roi = floatval($row['plan_roi']); $capital = floatval($row['capital']);
                $weekly_profit = ($capital * $monthly_roi / 100) / 4;
                $row['weekly_profit'] = $weekly_profit; 
                $row['l1_comm'] = ($weekly_profit * $l1_pct) / 100; $row['l2_comm'] = ($weekly_profit * $l2_pct) / 100;
                $data[] = $row;
            }
            response('success', $data); 
            break;
            
        case 'distribute_referral_reward':
            if($input['l1_id']) { $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id=?")->execute([$input['l1_amount'], $input['l1_id']]); $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$input['l1_id'], "مبروك! مكافأة إحالة: $".$input['l1_amount']]); }
            if($input['l2_id']) { $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id=?")->execute([$input['l2_amount'], $input['l2_id']]); $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$input['l2_id'], "مبروك! مكافأة إحالة: $".$input['l2_amount']]); }
            response('success'); 
            break;
            
        case 'skip_referral_reward':
            $msg = "لم يتم استحقاق مكافأة هذا الأسبوع.";
            if($input['l1_id']) $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$input['l1_id'], $msg]);
            if($input['l2_id']) $pdo->prepare("INSERT INTO messages (user_id, message, created_at) VALUES (?, ?, NOW())")->execute([$input['l2_id'], $msg]);
            response('success'); 
            break;

        case 'get_admin_profile':
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'admin'"); $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch(); response('success', ['email' => $admin['email'] ?? '']); 
            break;

        case 'update_admin_profile':
            $email = $input['email']; $password = $input['password'];
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ? AND role = 'admin'")->execute([$email, $hashed, $_SESSION['user_id']]);
            } else {
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'admin'")->execute([$email, $_SESSION['user_id']]);
            }
            response('success'); 
            break;

        default: throw new Exception("Invalid Action");
    }
} catch (Exception $e) { ob_clean(); echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); exit; }
?>