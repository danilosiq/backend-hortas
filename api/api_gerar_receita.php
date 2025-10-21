<?php
// =====================================================
// ✅ CONFIGURAÇÕES DE CORS (necessário para chamadas do frontend)
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json; charset=utf-8');

// ✅ Responde ao preflight (requisições OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit();
}

// =====================================================
// 🔧 FUNÇÃO DE ERRO PADRONIZADA
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// =====================================================
// 🔑 PASSO 1: OBTÉM VARIÁVEL DE AMBIENTE (CHAVE GEMINI)
// =====================================================
$env_var_name = 'chave_gemini';
$geminiApiKey = getenv($env_var_name);

if (!$geminiApiKey) {
    send_error("A chave da API do Gemini ('$env_var_name') não foi encontrada no ambiente do servidor.");
}

// =====================================================
// 📩 PASSO 2: RECEBE E VALIDA A REQUISIÇÃO DO FRONTEND
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas requisições POST são aceitas.', 405);
}

// Lê o JSON enviado pelo frontend
$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido do frontend.', 400);
}

if (empty($inputData) || !is_array($inputData)) {
    send_error('Dados de entrada inválidos. Esperava-se um array de itens.', 400);
}

// =====================================================
// 🍽️ PASSO 3: CONSTRÓI O PROMPT PARA A API GEMINI
// =====================================================
$alimentosList = [];
$restricoesList = [];
$adicionaisList = [];

foreach ($inputData as $item) {
    if (!empty($item['Alimentos'])) {
        $alimentosList[] = $item['Alimentos'];
    }
    if (!empty($item['Restrições']) && strtolower($item['Restrições']) !== 'nenhuma') {
        $restricoesList[] = $item['Restrições'];
    }
    if (!empty($item['Adicionais'])) {
        $adicionaisList[] = $item['Adicionais'];
    }
}

if (empty($alimentosList)) {
    send_error('A lista de alimentos não pode estar vazia.', 400);
}

// Cria o prompt para o modelo Gemini
$userPrompt = "Crie uma receita detalhada em português que utilize principalmente os seguintes ingredientes: " . implode(', ', $alimentosList) . ".";

if (!empty($restricoesList)) {
    $userPrompt .= " Por favor, leve em consideração as seguintes restrições: " . implode(', ', array_unique($restricoesList)) . ".";
}

if (!empty($adicionaisList)) {
    $userPrompt .= " Considere também estas notas: " . implode(', ', array_unique($adicionaisList)) . ".";
}

$userPrompt .= " A resposta deve ser um JSON único e bem formatado, contendo o nome da receita, uma descrição curta, uma lista de ingredientes com quantidades, um passo-a-passo das instruções, o tempo de preparo, o número de porções que a receita serve e uma tabela nutricional estimada para uma porção.";

// =====================================================
// 🤖 PASSO 4: MONTA E ENVIA REQUISIÇÃO À API GEMINI
// =====================================================
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . $geminiApiKey;

// Define o formato esperado de resposta
$recipeSchema = [
    'type' => 'OBJECT',
    'properties' => [
        'NomeDaReceita' => ['type' => 'STRING', 'description' => 'O nome criativo da receita.'],
        'Descricao' => ['type' => 'STRING', 'description' => 'Uma breve descrição do prato.'],
        'Ingredientes' => [
            'type' => 'ARRAY',
            'description' => 'Lista completa de ingredientes com quantidades.',
            'items' => ['type' => 'STRING']
        ],
        'Instrucoes' => [
            'type' => 'ARRAY',
            'description' => 'Passo-a-passo de como preparar a receita.',
            'items' => ['type' => 'STRING']
        ],
        'TempoDePreparo' => ['type' => 'STRING', 'description' => 'Tempo total de preparo (ex: "30 minutos").'],
        'Porcoes' => ['type' => 'STRING', 'description' => 'Número de porções que a receita serve.'],
        'TabelaNutricional' => [
            'type' => 'OBJECT',
            'description' => 'Informações nutricionais estimadas por porção.',
            'properties' => [
                'Calorias' => ['type' => 'STRING'],
                'Carboidratos' => ['type' => 'STRING'],
                'Proteinas' => ['type' => 'STRING'],
                'Gorduras' => ['type' => 'STRING']
            ]
        ]
    ]
];

// Monta payload JSON
$payload = json_encode([
    'contents' => [['parts' => [['text' => $userPrompt]]]],
    'generationConfig' => [
        'responseMimeType' => "application/json",
        'responseSchema' => $recipeSchema,
    ],
]);

// Executa a chamada HTTP
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// =====================================================
// 🚨 PASSO 5: TRATA ERROS E RETORNA RESULTADO
// =====================================================
if ($httpCode !== 200 || $apiResponse === false) {
    error_log("Erro na API Gemini: " . $apiResponse);
    send_error("Erro ao comunicar com a API Gemini. Código HTTP: $httpCode", $httpCode);
}

$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o JSON esperado da receita.");
}

// ✅ Retorna o JSON da receita diretamente
echo $jsonString;
?>