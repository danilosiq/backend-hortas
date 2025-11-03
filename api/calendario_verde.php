<?php
// =====================================================
// ✅ CORS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// 🔧 Função de erro
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// =====================================================
// 📩 Receber e validar o JSON
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas POST é aceito.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido enviado.', 400);
}

$cidade = htmlspecialchars($inputData['cidade'] ?? '');
$data = htmlspecialchars($inputData['data'] ?? '');

// Se cidade não for enviada
if (empty($cidade)) {
    send_error('O campo "cidade" é obrigatório.', 400);
}

// Se a data vier vazia, usa a data atual no formato dd-mm-yyyy
if (empty($data)) {
    $data = date('d-m-Y');
} else {
    // Valida e força o formato dd-mm-yyyy
    $timestamp = strtotime(str_replace('/', '-', $data));
    if ($timestamp === false) {
        send_error('Formato de data inválido. Use dd-mm-yyyy.', 400);
    }
    $data = date('d-m-Y', $timestamp);
}

// =====================================================
// 🔑 Carrega chave da API Gemini
// =====================================================
$geminiApiKey = getenv('chave_gemini');
if (!$geminiApiKey) {
    send_error('A chave da API Gemini (chave_gemini) não foi encontrada.');
}

// =====================================================
// 🌱 Prompt dinâmico
// =====================================================
$userPrompt = "
Você é um agrônomo especialista em análises sazonais e de mercado agrícola.

Com base na cidade '$cidade' e na data '$data', analise as **tendências agrícolas atuais** e sugira **3 culturas ideais para plantar agora**.

Para cada cultura, retorne:
1. produto — nome da fruta, legume ou erva;
2. tendencia_sazonal — motivo climático/sazonal;
3. tendencia_mercado — motivo econômico (preço, demanda, exportação, etc.);
4. recomendacao_pratica — dica realista de manejo e plantio.

Responda apenas com JSON puro, no formato:
{
  \"tendencias\": [
    {
      \"produto\": \"string\",
      \"tendencia_sazonal\": \"string\",
      \"tendencia_mercado\": \"string\",
      \"recomendacao_pratica\": \"string\"
    }
  ]
}
Sem markdown, sem texto adicional.";

// =====================================================
// 🤖 Chamada à API Gemini
// =====================================================
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $geminiApiKey;

$payload = json_encode([
    'contents' => [['parts' => [['text' => $userPrompt]]]],
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $apiResponse === false) {
    error_log("Erro na API Gemini: " . $apiResponse);
    send_error("Erro ao comunicar com a API Gemini. Código HTTP: $httpCode", $httpCode);
}

// =====================================================
// ✅ Resposta final
// =====================================================
$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o JSON esperado.");
}

echo $jsonString;
?>