<?php
// validate_api.php - (Checks for ID Front, Back, and SELFIE)
require 'config.php';

$OPENAI_API_KEY = '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'; // 🔴 مفتاحك

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$image_base64 = $input['image'] ?? '';
$type = $input['type'] ?? ''; 

if (empty($image_base64)) { echo json_encode(['status' => 'error', 'message' => 'No image']); exit; }

$imgData = $image_base64;
if(strpos($imgData, 'base64,') !== false) { $imgData = explode('base64,', $imgData)[1]; }

// --- الأوامر الصارمة ---

if ($type === 'front_id') {
    // التحقق من وجه الهوية
    $prompt = "You are a strict ID verification AI. Analyze Image 1. Is it the FRONT side of a Government ID Card?
    CRITICAL CHECK:
    1. Does it contain a CLEAR PORTRAIT PHOTO of the person?
    2. Is the text readable?
    REJECT IF: No face visible, Back side, or random object.
    Reply JSON ONLY: {\"valid\": true/false, \"reason\": \"short reason\"}";
} 
else if ($type === 'back_id') {
    // التحقق من ظهر الهوية
    $prompt = "Analyze Image 1. Is it the BACK side of an ID Card?
    Expect: Barcodes, MRZ codes, dense text.
    REJECT IF: It contains a large portrait photo.
    Reply JSON ONLY: {\"valid\": true/false, \"reason\": \"short reason\"}";
} 
else if ($type === 'selfie') {
    // 🔥 التحقق من السيلفي (الوجه الحقيقي) 🔥
    $prompt = "Analyze Image 1. Is this a REAL HUMAN FACE (Selfie)?
    CRITICAL CHECK:
    1. Is there a clear human face facing the camera?
    2. Are the eyes and facial features visible?
    
    REJECT IF:
    - No face is found.
    - The face is covered or too dark.
    - It is a photo of a photo (screen).
    - It is an ID card (I want a real selfie now).
    
    Reply JSON ONLY: {\"valid\": true/false, \"reason\": \"No face detected / Face unclear\"}";
}
else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']); exit;
}

$payload = [
    "model" => "gpt-4o",
    "messages" => [
        ["role" => "system", "content" => $prompt],
        ["role" => "user", "content" => [ ["type" => "image_url", "image_url" => ["url" => "data:image/jpeg;base64," . $imgData]] ]]
    ],
    "max_tokens" => 60,
    "temperature" => 0.0
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer $OPENAI_API_KEY"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if(curl_errno($ch)) { echo json_encode(['status' => 'error', 'message' => 'Connection Error']); exit; }
curl_close($ch);

$resData = json_decode($response, true);
if (isset($resData['error'])) { echo json_encode(['status' => 'error', 'message' => 'AI Error: ' . $resData['error']['message']]); exit; }

$content = $resData['choices'][0]['message']['content'] ?? '{}';
$clean_json = str_replace(['```json', '```'], '', $content);
$ai_decision = json_decode($clean_json, true);

echo json_encode(['status' => 'success', 'ai' => $ai_decision]);
?>