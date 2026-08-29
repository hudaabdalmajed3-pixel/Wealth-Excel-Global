<?php
// ai_chat.php - معالج الذكاء الاصطناعي (محمي ومقيد)
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// ==========================================
// مفتاح API (تأكد من صلاحيته)
// ==========================================
$apiKey = "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"; 

// 1. استقبال البيانات
$input = json_decode(file_get_contents('php://input'), true);
$question = trim($input['question'] ?? '');
$moduleTitle = trim($input['module_title'] ?? 'General Investment');
$lang = $input['lang'] ?? 'en';

// التحقق من وجود سؤال
if (empty($question)) {
    echo json_encode(['reply' => ($lang == 'ar' ? "الرجاء كتابة سؤال." : "Please ask a question.")]);
    exit;
}

// 2. التحقق من عدد الأسئلة لغير المسجلين (Server-Side Limit)
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    // تهيئة العداد إذا لم يكن موجوداً
    if (!isset($_SESSION['guest_questions_count'])) {
        $_SESSION['guest_questions_count'] = 0;
    }

    // التحقق من الحد الأقصى (3 أسئلة)
    if ($_SESSION['guest_questions_count'] >= 3) {
        $msg = ($lang == 'ar') 
            ? "عفواً، لقد تجاوزت الحد المسموح (3 أسئلة). يرجى التسجيل للمتابعة." 
            : "Limit reached (3 questions). Please register to continue.";
        echo json_encode(['reply' => $msg, 'limit_reached' => true]);
        exit;
    }

    // زيادة العداد
    $_SESSION['guest_questions_count']++;
}

// 3. تجهيز التعليمات الصارمة (Strict Prompt)
// نجبر الذكاء الاصطناعي على تقمص دور المعلم في هذا المجال فقط وعدم الخروج عنه
$systemMessage = "You are a strict and professional instructor specializing ONLY in the topic: '{$moduleTitle}'.
Your Goal: Answer the student's question based strictly on the principles of {$moduleTitle}.

RULES:
1. Do NOT answer any questions unrelated to {$moduleTitle} (e.g., sports, cooking, general chat). If the user asks off-topic, say: 'I can only answer questions related to this module.'
2. Keep your answers concise, educational, and direct.
3. If the user asks in Arabic, answer in Arabic. If English, answer in English.
4. Do not mention you are an AI. Act as the course tutor.";

// 4. إرسال الطلب لـ OpenAI
$data = [
    "model" => "gpt-3.5-turbo",
    "messages" => [
        ["role" => "system", "content" => $systemMessage],
        ["role" => "user", "content" => $question]
    ],
    "temperature" => 0.5, // تقليل الحرارة ليكون الرد أكثر دقة وجدية
    "max_tokens" => 400
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $apiKey
]);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['reply' => 'Connection Error.']);
    exit;
}
curl_close($ch);

$response = json_decode($result, true);

// التحقق من وجود خطأ في API
if (isset($response['error'])) {
    $reply = ($lang == 'ar') ? "عذراً، النظام مشغول حالياً." : "System is busy.";
} else {
    $reply = $response['choices'][0]['message']['content'] ?? "";
}

echo json_encode(['reply' => $reply]);
?>