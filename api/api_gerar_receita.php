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
// 🔧 Função padrão de erro
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// =====================================================
// 🔑 Passo 1: Variável de ambiente
// =====================================================
$env_var_name = 'chave_gemini';
$geminiApiKey = getenv($env_var_name);

if (!$geminiApiKey) {
    send_error("A chave da API do Gemini ('$env_var_name') não foi encontrada no ambiente do servidor.");
}

// =====================================================
// 📩 Passo 2: Recebe e valida POST
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas requisições POST são aceitas.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido do frontend.', 400);
}

if (empty($inputData) || !is_array($inputData)) {
    send_error('Dados de entrada inválidos. Esperava-se um array de itens.', 400);
}

// =====================================================
// 🍽️ Passo 3: Monta prompt para Gemini
// =====================================================
$alimentosList = [];
$restricoesList = [];
$adicionaisList = [];

foreach ($inputData as $item) {
    if (!empty($item['Alimentos'])) $alimentosList[] = $item['Alimentos'];
    if (!empty($item['Restrições']) && strtolower($item['Restrições']) !== 'nenhuma')
        $restricoesList[] = $item['Restrições'];
    if (!empty($item['Adicionais'])) $adicionaisList[] = $item['Adicionais'];
}

if (empty($alimentosList)) {
    send_error('A lista de alimentos não pode estar vazia.', 400);
}

$userPrompt = "Crie uma receita detalhada em português que utilize principalmente os seguintes ingredientes: " . implode(', ', $alimentosList) . ".";
if (!empty($restricoesList))
    $userPrompt .= " Leve em consideração as seguintes restrições: " . implode(', ', array_unique($restricoesList)) . ".";
if (!empty($adicionaisList))
    $userPrompt .= " Considere também estas notas: " . implode(', ', array_unique($adicionaisList)) . ".";
$userPrompt .= " A resposta deve ser um JSON único e bem formatado contendo nome, descrição, ingredientes, instruções, tempo de preparo, porções e tabela nutricional estimada.";

// =====================================================
// 🤖 Passo 4: Chama API Gemini
// =====================================================
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . $geminiApiKey;

$recipeSchema = [
    'type' => 'OBJECT',
    'properties' => [
        'NomeDaReceita' => ['type' => 'STRING'],
        'Descricao' => ['type' => 'STRING'],
        'Ingredientes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        'Instrucoes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        'TempoDePreparo' => ['type' => 'STRING'],
        'Porcoes' => ['type' => 'STRING'],
        'TabelaNutricional' => [
            'type' => 'OBJECT',
            'properties' => [
                'Calorias' => ['type' => 'STRING'],
                'Carboidratos' => ['type' => 'STRING'],
                'Proteinas' => ['type' => 'STRING'],
                'Gorduras' => ['type' => 'STRING']
            ]
        ]
    ]
];

$payload = json_encode([
    'contents' => [['parts' => [['text' => $userPrompt]]]],
    'generationConfig' => [
        'responseMimeType' => "application/json",
        'responseSchema' => $recipeSchema,
    ],
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
// ✅ Passo 5: Retorna a resposta
// =====================================================
$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o JSON esperado da receita.");
}

echo $jsonString;
?>