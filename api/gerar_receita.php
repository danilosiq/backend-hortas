<?php
header('Content-Type: application/json; charset=utf-8');

// Função de utilidade para enviar uma mensagem de erro padronizada e parar o script.
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// ---
// Passo 1: Obter Chaves Secretas (Variáveis de Ambiente)
// ---
//require __DIR__ . '/vendor/autoload.php';

//use Dotenv\Dotenv;

// Carrega o arquivo .env
//$dotenv = Dotenv::createImmutable(__DIR__);
//$dotenv->load();

$geminiApiKey = $_ENV['chave_gemini'] ?? null;

if (!$geminiApiKey) {
    send_error('A chave da API do Gemini (GEMINI_API_KEY) não foi encontrada no ambiente do servidor.');
}

// ---
// Passo 2: Receber e Validar o Pedido do Frontend
// ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas requisições POST são aceites.', 405);
}

// Lê o JSON enviado pelo frontend.
$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido do frontend.', 400);
}

// Validação dos dados de entrada
if (empty($inputData['itens']) || !is_array($inputData['itens'])) {
    send_error('Dados de entrada inválidos. Esperava-se um objeto com uma chave "itens" contendo um array.', 400);
}

// ATUALIZAÇÃO: Valida se o ID da horta foi enviado para contabilizar a receita gerada
if (empty($inputData['id_horta'])) {
    send_error('O ID da horta é obrigatório para gerar uma receita.', 400);
}

$id_horta = (int)$inputData['id_horta'];
$itens_receita = $inputData['itens'];

// ---
// Passo 3: Construir o Prompt para a API Gemini
// ---
$alimentosList = [];
$restricoesList = [];
$adicionaisList = [];

foreach ($itens_receita as $item) {
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

// Constrói um prompt claro e detalhado para a IA
$userPrompt = "Crie uma receita detalhada em português que utilize principalmente os seguintes ingredientes: " . implode(', ', $alimentosList) . ".";
if (!empty($restricoesList)) {
    $userPrompt .= " Por favor, leve em consideração as seguintes restrições: " . implode(', ', array_unique($restricoesList)) . ".";
}
if (!empty($adicionaisList)) {
    $userPrompt .= " Considere também estas notas: " . implode(', ', array_unique($adicionaisList)) . ".";
}
$userPrompt .= " A resposta deve ser um JSON único e bem formatado, contendo o nome da receita, uma descrição curta, uma lista de ingredientes com quantidades, um passo-a-passo das instruções, o tempo de preparo, o número de porções que a receita serve e uma tabela nutricional estimada para uma porção.";

// ---
// Passo 4: Preparar e Executar a Chamada para a API Gemini
// ---
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . $geminiApiKey;

$recipeSchema = [
    'type' => 'OBJECT',
    'properties' => [
        'NomeDaReceita' => ['type' => 'STRING'], 'Descricao' => ['type' => 'STRING'],
        'Ingredientes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        'Instrucoes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        'TempoDePreparo' => ['type' => 'STRING'], 'Porcoes' => ['type' => 'STRING'],
        'TabelaNutricional' => [
            'type' => 'OBJECT',
            'properties' => ['Calorias' => ['type' => 'STRING'], 'Carboidratos' => ['type' => 'STRING'], 'Proteinas' => ['type' => 'STRING'], 'Gorduras' => ['type' => 'STRING']],
            'required' => ['Calorias', 'Carboidratos', 'Proteinas', 'Gorduras']
        ]
    ],
    'required' => ['NomeDaReceita', 'Descricao', 'Ingredientes', 'Instrucoes', 'TempoDePreparo', 'Porcoes', 'TabelaNutricional']
];

$payload = json_encode([
    'contents' => [['parts' => [['text' => $userPrompt]]]],
    'generationConfig' => ['responseMimeType' => "application/json", 'responseSchema' => $recipeSchema]
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
    send_error("Erro ao comunicar com a API Gemini. Código de Status: $httpCode", $httpCode);
}

$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o JSON da receita esperado.");
}

// ---
// Passo 5: Atualizar o Contador de Receitas no Banco de Dados
// ---
include "banco_mysql.php";
try {
    // ATUALIZAÇÃO: Incrementa o contador 'receitas_baixadas' na tabela 'hortas'
    $sql_update = "UPDATE hortas SET receitas_geradas = receitas_geradas + 1 WHERE id_hortas = :id_horta";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
    $stmt_update->execute();
} catch (PDOException $e) {
    // Se a atualização falhar, loga o erro mas não impede o envio da receita para o usuário
    error_log("Falha ao atualizar contador de receitas para a horta ID $id_horta: " . $e->getMessage());
}

// ---
// Passo 6: Enviar a Resposta de Sucesso para o Frontend
// ---
echo $jsonString;
?>
