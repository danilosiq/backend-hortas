<?php
// Define o cabeçalho da resposta como JSON, garantindo a correta interpretação pelo frontend.
header('Content-Type: application/json; charset=utf-8');

/**
 * Função de utilidade para enviar uma mensagem de erro padronizada e parar o script.
 * @param string $message A mensagem de erro.
 * @param int $statusCode O código de status HTTP (padrão 500).
 */
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// ---
// Passo 1: Carregar Variáveis de Ambiente (Chave da API)
// ---
//require __DIR__ . '/vendor/autoload.php';
//use Dotenv\Dotenv;

//$dotenv = Dotenv::createImmutable(__DIR__);
//$dotenv->load();

$geminiApiKey = $_ENV['chave_gemini'] ?? null;

if (!$geminiApiKey) {
    send_error('A chave da API (chave_gemini) não foi encontrada no arquivo .env.');
}

// ---
// Passo 2: Receber e Validar a Requisição do Frontend
// ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas requisições POST são aceitas.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido do frontend.', 400);
}

// VALIDAÇÃO ATUALIZADA: Agora inclui 'metodo_cultivo' como obrigatório.
if (empty($inputData['local']) || empty($inputData['data']) || empty($inputData['metodo_cultivo'])) {
    send_error('Dados de entrada inválidos. As chaves "data", "local" e "metodo_cultivo" são obrigatórias.', 400);
}

$local = htmlspecialchars($inputData['local']);
$data = htmlspecialchars($inputData['data']);
// Valida o valor de 'metodo_cultivo' para aceitar apenas 'vaso' ou 'terreno'.
$metodo_cultivo = strtolower(htmlspecialchars($inputData['metodo_cultivo']));
if ($metodo_cultivo !== 'vaso' && $metodo_cultivo !== 'terreno') {
    send_error('Valor para "metodo_cultivo" inválido. Use "vaso" ou "terreno".', 400);
}


// ---
// Passo 3: Definir o Schema (Estrutura) do JSON de Resposta
// ---
// SCHEMA ATUALIZADO: O campo 'metodo_cultivo' agora é um enum para forçar a resposta.
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
                    'instrucoes' => ['type' => 'STRING', 'description' => 'Passo-a-passo do plantio.'],
                    'cuidados_especiais' => ['type' => 'STRING', 'description' => 'Dicas de rega, luz, etc.']
                ],
                'required' => ['nome_planta', 'tipo', 'metodo_cultivo', 'instrucoes']
            ]
        ]
    ],
    'required' => ['titulo', 'introducao', 'plantas_sugeridas']
];

// ---
// Passo 4: Construir o Prompt e Fazer a Requisição para a API
// ---
// PROMPT ATUALIZADO: Agora instrui a IA a filtrar as sugestões pelo método de cultivo escolhido.
$userPrompt = "Aja como um especialista em jardinagem. Crie um guia de cultivo simples e prático para iniciantes, com base na localização: '$local' e data de plantio: '$data'.
O usuário deseja plantar exclusivamente em '$metodo_cultivo'.
Portanto, sugira de 3 a 5 plantas (frutas, ervas e/ou hortaliças) que sejam especificamente adequadas para o cultivo em '$metodo_cultivo' nesta região e época.
Para cada planta, confirme no campo 'metodo_cultivo' do JSON que ela é ideal para '$metodo_cultivo'.
A resposta DEVE ser um JSON seguindo o schema definido, sem markdown.";


// Monta o corpo (payload) da requisição para a API.
$payload = json_encode([
    'contents' => [['parts' => [['text' => $userPrompt]]]],
    'generationConfig' => [
        'responseMimeType' => "application/json",
        'responseSchema' => $guiaSchema
    ]
]);

// URL da API Gemini para geração de conteúdo.
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $geminiApiKey;

// Inicia a requisição cURL.
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Verifica se a requisição à API falhou.
if ($httpCode !== 200 || $apiResponse === false) {
    error_log("Erro na API Gemini: " . $apiResponse); // Loga o erro para depuração
    send_error("Erro ao comunicar com a API. Código de Status: $httpCode", $httpCode > 0 ? $httpCode : 500);
}

// Decodifica a resposta da API.
$result = json_decode($apiResponse, true);
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o JSON do guia esperado.");
}

// ---
// Passo 5: Enviar a Resposta de Sucesso para o Frontend
// ---
// Envia o JSON gerado pela API diretamente para o frontend.
echo $jsonString;

?>
