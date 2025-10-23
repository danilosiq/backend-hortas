<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
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

if (empty($inputData['local']) || empty($inputData['data']) || empty($inputData['metodo_cultivo'])) {
    send_error('Os campos "local", "data" e "metodo_cultivo" são obrigatórios.', 400);
}

$local = htmlspecialchars($inputData['local']);
$data = htmlspecialchars($inputData['data']);
$metodo_cultivo = strtolower(htmlspecialchars($inputData['metodo_cultivo']));

if ($metodo_cultivo !== 'vaso' && $metodo_cultivo !== 'terreno') {
    send_error('O valor de "metodo_cultivo" deve ser "vaso" ou "terreno".', 400);
}

// =====================================================
// 🧠 Passo 3: Schema esperado do JSON de resposta
// =====================================================
$guiaSchema = [
    'type' => 'OBJECT',
    'properties' => [
        'titulo' => ['type' => 'STRING'],
        'introducao' => ['type' => 'STRING'],
        'plantas_sugeridas' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'nome_planta' => ['type' => 'STRING'],
                    'tipo' => ['type' => 'STRING', 'enum' => ['Hortaliça', 'Fruta', 'Erva Aromática']],
                    'metodo_cultivo' => ['type' => 'STRING', 'enum' => ['Vaso', 'Terreno']],
                    'instrucoes' => ['type' => 'STRING'],
                    'cuidados_especiais' => ['type' => 'STRING']
                ],
                'required' => ['nome_planta', 'tipo', 'metodo_cultivo', 'instrucoes']
            ]
        ]
    ],
    'required' => ['titulo', 'introducao', 'plantas_sugeridas']
];

// =====================================================
// ✏️ Passo 4: Monta o prompt e envia à API Gemini
// =====================================================
$userPrompt = "Aja como um especialista em jardinagem. Crie um guia de cultivo simples e prático para iniciantes, com base na localização: '$local' e data de plantio: '$data'.
O usuário deseja plantar exclusivamente em '$metodo_cultivo'.
Portanto, sugira de 3 a 5 plantas (frutas, ervas e/ou hortaliças) que sejam especificamente adequadas para o cultivo em '$metodo_cultivo' nesta região e época.
Para cada planta, confirme no campo 'metodo_cultivo' do JSON que ela é ideal para '$metodo_cultivo'.
A resposta DEVE ser um JSON seguindo o schema definido, sem markdown.";

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