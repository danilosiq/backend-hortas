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
// 🔑 Variável de ambiente
// =====================================================
$env_var_name = 'chave_gemini';
$geminiApiKey = getenv($env_var_name);

if (!$geminiApiKey) {
    send_error("A chave da API do Gemini ('$env_var_name') não foi encontrada.");
}

// =====================================================
// 📩 Recebendo o JSON
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Apenas POST é permitido.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido.', 400);
}

if (empty($inputData)) {
    send_error('Corpo da requisição vazio.', 400);
}

// =====================================================
// 🔍 Extrair id_produtor
// =====================================================
$id_produtor = $inputData["id_produtor"] ?? null;

// =====================================================
// 🥦 Extrair itens numerados (“0”, “1”, “2”, …)
// =====================================================
$alimentosList = [];
$restricoesList = [];
$adicionaisList = [];

foreach ($inputData as $key => $item) {
    if (!is_numeric($key)) continue; // ignora "id_produtor"

    if (!empty($item["Alimentos"]))      $alimentosList[]  = $item["Alimentos"];
    if (!empty($item["Restrições"]))     $restricoesList[] = $item["Restrições"];
    if (!empty($item["Adicionais"]))     $adicionaisList[] = $item["Adicionais"];
}

if (empty($alimentosList)) {
    send_error('O campo "Alimentos" não pode estar vazio.', 400);
}

// =====================================================
// 🍽️ Montar prompt
// =====================================================
$userPrompt = "Crie uma receita detalhada usando os seguintes ingredientes: " .
              implode(', ', $alimentosList) . ".";

if (!empty($restricoesList)) {
    $userPrompt .= " Leve em consideração estas restrições: " .
                   implode(', ', $restricoesList) . ".";
}

if (!empty($adicionaisList)) {
    $userPrompt .= " Observações adicionais: " .
                   implode(', ', $adicionaisList) . ".";
}

$userPrompt .= " A resposta deve ser um JSON contendo nome, descrição, ingredientes, instruções, tempo de preparo, porções e tabela nutricional.";

// =====================================================
// 🧾 Novo schema compatível com Gemini 2.5
// =====================================================
$recipeSchema = [
    "type" => "object",
    "properties" => [
        "NomeDaReceita" => ["type" => "string"],
        "Descricao" => ["type" => "string"],
        "Ingredientes" => ["type" => "array", "items" => ["type" => "string"]],
        "Instrucoes" => ["type" => "array", "items" => ["type" => "string"]],
        "TempoDePreparo" => ["type" => "string"],
        "Porcoes" => ["type" => "string"],
        "TabelaNutricional" => [
            "type" => "object",
            "properties" => [
                "Calorias" => ["type" => "string"],
                "Carboidratos" => ["type" => "string"],
                "Proteinas" => ["type" => "string"],
                "Gorduras" => ["type" => "string"]
            ]
        ]
    ]
];

// =====================================================
// 🤖 Chamada para Gemini
// =====================================================
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=$geminiApiKey";

$payload = json_encode([
    "contents" => [
        [
            "parts" => [
                ["text" => $userPrompt]
            ]
        ]
    ],
    "generationConfig" => [
        "response_mime_type" => "application/json",
        "response_schema"   => $recipeSchema
    ]
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("Erro Gemini: $apiResponse");
    send_error("Erro ao comunicar com a API Gemini. Código HTTP: $httpCode", $httpCode);
}

$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A API não retornou um JSON válido.");
}

// =====================================================
// 🧮 Atualizar banco se existir id_produtor
// =====================================================
if (!empty($id_produtor)) {
    try {
        include 'banco_mysql.php';
        if ($conn) {
            $sql = "UPDATE hortas
                    SET receitas_geradas = COALESLES(receitas_geradas, 0) + 1
                    WHERE produtor_id_produtor = :id_produtor";

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id_produtor', $id_produtor);
            $stmt->execute();
        }
    } catch (Throwable $e) {
        error_log("Erro BD: " . $e->getMessage());
    }
}

// =====================================================
// 🎉 Resposta final
// =====================================================
echo $jsonString;

?>
