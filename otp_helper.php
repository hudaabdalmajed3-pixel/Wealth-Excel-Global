<?php
// otp_helper.php - Security OTP Core Functions & SMTP Integration (With Fallback Logging)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// تأكد من صحة مسار ملفات مكتبة PHPMailer
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * الدالة المركزية لإرسال الإيميلات عبر خادم SMTP (هوستنجر)
 */
function sendEmailSMTP($to_email, $subject, $html_body) {
    $mail = new PHPMailer(true);
    try {
        // 1. إعدادات الاتصال بالخادم
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; 
        $mail->SMTPAuth   = true; 
        
        // 2. بيانات تسجيل الدخول
        $mail->Username   = '//'; 
        // 🔴 ضع كلمة المرور الحقيقية هنا
        $mail->Password   = '//';  
        
        // 3. إعدادات الأمان الخاصة بـ Hostinger
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465; 
        $mail->Timeout    = 15;

        // 4. تخطي فحص شهادة SSL (لتجنب مشاكل الاستضافة المشتركة)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // 5. إعدادات المرسل والمستلم
        $mail->setFrom('//'); 
        $mail->addAddress($to_email);

        // 6. محتوى الرسالة
        $mail->isHTML(true); 
        $mail->CharSet = 'UTF-8'; 
        $mail->Subject = $subject;
        $mail->Body    = $html_body;

        // 7. محاولة الإرسال
        $mail->send();
        return 'success'; 

    } catch (Exception $e) {
        $error_msg = $mail->ErrorInfo;
        
        // 🔴 نظام الطوارئ (Fallback): حفظ الرمز في ملف نصي في حال رفض السيرفر إرسال الإيميل
        // نستخرج الرمز السري من الـ HTML باستخدام دالة البحث (Regex)
        preg_match('/<h1[^>]*>(\d+)<\/h1>/', $html_body, $matches);
        $extracted_otp = isset($matches[1]) ? $matches[1] : 'غير_معروف';
        
        // نكتب الرمز والخطأ في ملف نصي يمكننا قراءته
        $log_entry = "[" . date('Y-m-d H:i:s') . "] OTP: $extracted_otp | To: $to_email | Error: $error_msg\n";
        file_put_contents(__DIR__ . '/otp_debug_log.txt', $log_entry, FILE_APPEND);
        
        return $error_msg;
    }
}

/**
 * دالة لتجهيز محتوى رسالة رمز التحقق (OTP)
 */
function sendOtpEmail($email, $code, $action) {
    $actionText = 'Security Action';
    if ($action === 'withdraw') $actionText = 'Withdrawal Request';

    $msg = "
    <div style='background:#0f172a; padding:30px; text-align:center; border-radius:10px; border:1px solid #d4af37; color:#fff; font-family:Arial, sans-serif;'>
        <h2 style='color:#d4af37; margin-top:0;'>Wealth Excel Security</h2>
        <p style='color:#ccc;'>You requested a secure action: <b>$actionText</b>.</p>
        <p style='color:#ccc;'>Your One-Time Password (OTP) is:</p>
        <h1 style='background:#1e293b; padding:15px; border-radius:8px; color:#10b981; letter-spacing:5px; font-size:32px;'>$code</h1>
        <p style='font-size:12px; color:#888;'>This code expires in 10 minutes. Do not share it with anyone.</p>
    </div>";
    
    return sendEmailSMTP($email, "Security OTP - Wealth Excel", $msg);
}

/**
 * إنشاء الرمز السري وحفظه في قاعدة البيانات
 */
function createOtp($pdo, $user_id, $action, $data = null) {
    $otp_code = rand(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $dataJson = $data ? json_encode($data) : null;
    
    $pdo->prepare("DELETE FROM user_otp WHERE user_id = ? AND action = ?")->execute([$user_id, $action]);
    $pdo->prepare("INSERT INTO user_otp (user_id, otp_code, action, expires_at, data) VALUES (?, ?, ?, ?, ?)")
        ->execute([$user_id, $otp_code, $action, $expires_at, $dataJson]);
        
    return $otp_code;
}

/**
 * التحقق من صحة الرمز المدخل
 */
function verifyOtp($pdo, $user_id, $otp_code, $action) {
    $stmt = $pdo->prepare("SELECT * FROM user_otp WHERE user_id = ? AND otp_code = ? AND action = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $otp_code, $action]);
    $otpData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($otpData) {
        $pdo->prepare("DELETE FROM user_otp WHERE id = ?")->execute([$otpData['id']]);
        return $otpData['data'] ? json_decode($otpData['data'], true) : true;
    }
    return false;
}