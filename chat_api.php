<?php
// chat_api.php
header('Content-Type: application/json');

// 1. إعداد مفتاح API
$apiKey = getenv('OPENAI_API_KEY'); 
if (!$apiKey) {
    // احتياطياً للملوكل هوست اذا لم تضبط البيئة
    $apiKey = "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";
}

// استقبال البيانات
$input = json_decode(file_get_contents('php://input'), true);
$question = $input['question'] ?? '';
$moduleId = intval($input['module_id'] ?? 0);
$lang = $input['lang'] ?? 'en';

if (!$question || !$moduleId) {
    echo json_encode(['reply' => 'Error: Missing data.']);
    exit;
}

// ==========================================
// 2. تعريف System Prompts لكل مجال
// ==========================================
$systemPrompts = [
    1 => "You are a specialized educational assistant for the 'Global Investing' module within Wealth Xcel Academy. You must base all explanations strictly on the official Global Investing PDF. Objectives: Teach global investing fundamentals, guide portfolio construction, and build structured investment thinking. Rules: No trading signals, predictions, or guarantees. No content outside Global Investing. Tone: Professional, educational, structured.",
    2 => "You are the educational assistant for the Capital Management module. Objectives: Teach cashflow control, capital allocation, compounding, and bucket systems. Rules: No financial advice, promises, or market predictions. Redirect unrelated questions. Tone: Calm, disciplined, educational.",
    3 => "You are a Risk Management instructor. Objectives: Teach risk control, position sizing, loss limitation, and scenario thinking. Rules: No leverage encouragement. No trade instructions. Redirect non-risk topics. Tone: Protective, analytical, serious.",
    4 => "You are a behavioral finance coach. Objectives: Explain psychological biases, encourage journaling/discipline, and help manage emotions. Rules: No investment or trading advice. Redirect technical questions. Tone: Supportive, reflective, structured.",
    5 => "You are an AI-for-Business instructor. Objectives: Teach practical AI usage for productivity, workflow design, and governance. Rules: No exaggerated AI claims. No unrelated AI topics. Tone: Professional, systems-oriented.",
    6 => "You are a macroeconomics education assistant. Objectives: Explain rates, inflation, economic regimes, and scenario-based reasoning. Rules: No forecasts or political opinions. Redirect micro-level questions. Tone: Neutral, analytical, educational.",
    7 => "You are an international trade instructor. Objectives: Teach import/export fundamentals, logistics, supplier evaluation, and pricing. Rules: No legal or customs advice. No real supplier recommendations. Tone: Operational, practical, professional.",
    8 => "You are a digital marketing instructor. Objectives: Teach funnels, offers, conversion logic, testing, and KPIs. Rules: No platform hacks or guarantees. Redirect non-marketing topics. Tone: Strategic, data-driven.",
    9 => "You are an entrepreneurship instructor. Objectives: Teach validation, business models, execution, and experimentation. Rules: No funding promises. No legal or tax advice. Tone: Visionary but grounded.",
    10 => "You are a Web3 education assistant. Objectives: Explain blockchain, digital ownership, security, and risk awareness. Rules: No price predictions or token recommendations. No speculation encouragement. Tone: Cautious, educational, security-focused."
];

// اختيار البرومبت المناسب
$basePrompt = $systemPrompts[$moduleId] ?? "You are a helpful assistant.";

// إضافة قاعدة اللغة الصارمة
$langInstruction = "Always respond in " . ($lang == 'ar' ? "Arabic" : "English") . ". If the user asks in Arabic, answer in Arabic. If in English, answer in English. Stick strictly to the provided PDF Context.";

$finalSystemPrompt = $basePrompt . "\n" . $langInstruction;

// ==========================================
// 3. قراءة ملف PDF (Context)
// ==========================================
$pdfPath = __DIR__ . "/uploads/academy/" . $moduleId . ".pdf";
$pdfText = "";

if (file_exists($pdfPath)) {
    try {
        $pdfText = extractTextFromPdf($pdfPath);
    } catch (Exception $e) { $pdfText = ""; }
}

// تقليص النص لتجنب تجاوز حد التوكنز
$context = substr($pdfText, 0, 12000); 

// ==========================================
// 4. الإرسال إلى OpenAI
// ==========================================
$data = [
    "model" => "gpt-3.5-turbo", // أو gpt-4o-mini
    "messages" => [
        ["role" => "system", "content" => $finalSystemPrompt . "\n\nCONTEXT FROM PDF:\n" . $context],
        ["role" => "user", "content" => $question]
    ],
    "temperature" => 0.3
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $apiKey"
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$reply = $result['choices'][0]['message']['content'] ?? "Error: AI Service Unavailable.";

echo json_encode(['reply' => $reply]);

// --- دالة استخراج النص من PDF (مبسطة) ---
function extractTextFromPdf($filename) {
    $infile = @file_get_contents($filename, FILE_BINARY);
    if (empty($infile)) return "";
    $texts = [];
    preg_match_all("#obj(.*)endobj#ismU", $infile, $objects);
    foreach ($objects[1] as $object) {
        if (preg_match("#stream(.*)endstream#ismU", $object, $stream)) {
            $data = ltrim($stream[1]);
            if (strpos($object, "FlateDecode") !== false) {
                $data = @gzuncompress($data);
            }
            preg_match_all("#BT(.*)ET#ismU", $data, $containers);
            foreach ($containers[1] as $text) {
                if(preg_match_all("#\((.*)\)#ismU", $text, $matches)) {
                    foreach($matches[1] as $m) $texts[] = $m;
                }
            }
        }
    }
    return implode(" ", $texts);
}
?>