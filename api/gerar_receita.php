<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

header("Content-Type: application/json; charset=utf-8");

// =====================================================
// 🔧 Função de erro padronizada
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// =====================================================
// 🔑 Passo 1: Carregar variável de ambiente (chave da API)
// =====================================================
$env_var_name = 'chave_gemini';
$geminiApiKey = getenv($env_var_name);

if (!$geminiApiKey) {
    send_error("A chave da API do Gemini ('$env_var_name') não foi encontrada no ambiente do servidor.");
}

// =====================================================
// 📩 Passo 2: Receber e validar o JSON do frontend
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas requisições POST são aceitas.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido do frontend.', 400);
}

if (empty($inputData['planta']) || empty($inputData['cidade']) || empty($inputData['data']) || empty($inputData['metodo_cultivo'])) {
    send_error('Os campos "planta", "cidade", "data" e "metodo_cultivo" são obrigatórios.', 400);
}

$planta = htmlspecialchars($inputData['planta']);
$cidade = htmlspecialchars($inputData['cidade']);
$data = htmlspecialchars($inputData['data']);
$metodo_cultivo = strtolower(htmlspecialchars($inputData['metodo_cultivo']));

if ($metodo_cultivo !== 'vaso' && $metodo_cultivo !== 'solo') {
    send_error('O valor de "metodo_cultivo" deve ser "vaso" ou "solo".', 400);
}

// =====================================================
// 🧠 Passo 3: Schema esperado do JSON de resposta
// =====================================================
$guiaSchema = [
    'type' => 'OBJECT',
    'properties' => [
        'titulo' => ['type' => 'STRING'],
        'planta' => ['type' => 'STRING'],
        'cidade' => ['type' => 'STRING'],
        'data_considerada' => ['type' => 'STRING'],
        'metodo_cultivo' => ['type' => 'STRING', 'enum' => ['vaso', 'solo']],
        'introducao' => ['type' => 'STRING'],
        'modo_cultivo' => ['type' => 'STRING'],
        'rota_irrigacao' => ['type' => 'STRING'],
        'consumo_sol' => ['type' => 'STRING'],
        'tempo_colheita' => ['type' => 'STRING'],
        'recomendacao_epoca' => ['type' => 'STRING']
    ],
    'required' => [
        'titulo',
        'planta',
        'cidade',
        'data_considerada',
        'metodo_cultivo',
        'introducao',
        'modo_cultivo',
        'rota_irrigacao',
        'consumo_sol',
        'tempo_colheita',
        'recomendacao_epoca'
    ]
];

// =====================================================
// ✏️ Passo 4: Monta o prompt e envia à API Gemini
// =====================================================
$userPrompt = "Você é um especialista em jardinagem. Crie um guia detalhado sobre o cultivo da planta '$planta' na cidade de '$cidade', considerando a data '$data' e o método de cultivo '$metodo_cultivo' (vaso ou solo).

O guia deve conter APENAS as seguintes informações, em formato JSON, seguindo o schema fornecido:
- 'titulo': um título descritivo do guia
- 'planta': o nome da planta
- 'cidade': a cidade informada
- 'data_considerada': a data informada
- 'metodo_cultivo': o método informado (vaso ou solo)
- 'introducao': breve explicação sobre as condições gerais dessa planta
- 'modo_cultivo': instruções específicas de plantio conforme o método de cultivo informado
- 'rota_irrigacao': quanto e com que frequência irrigar por mês
- 'consumo_sol': se precisa de sol direto ou parcial e horários ideais
- 'tempo_colheita': tempo médio até a colheita
- 'recomendacao_epoca': com base na data e cidade informadas, diga se é ou não uma boa época para plantar

Não recomende outras plantas e não adicione nada além do que foi solicitado.
Responda apenas com o JSON puro, sem markdown, sem explicações extras.";

$payload = json_encode([
    'contents' => [['parts' => [['text' => $userPrompt]]]],
    'generationConfig' => [
        'responseMimeType' => "application/json",
        'responseSchema' => $guiaSchema
    ]
]);

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $geminiApiKey;

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
// ✅ Passo 5: Retorna a resposta JSON
// =====================================================
$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o JSON esperado do guia.");
}

echo $jsonString;
?>