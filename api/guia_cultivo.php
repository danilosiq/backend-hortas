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
// Garante que as dependências do Composer (como o Dotenv) sejam carregadas.
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Carrega as variáveis do arquivo .env localizado no mesmo diretório.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Obtém a chave da API do Gemini. É crucial que ela esteja no seu arquivo .env.
$geminiApiKey = $_ENV['chave_gemini'] ?? null;

if (!$geminiApiKey) {
    send_error('A chave da API (chave_gemini) não foi encontrada no arquivo .env.');
}

// ---
// Passo 2: Receber e Validar a Requisição do Frontend
// ---
// Apenas requisições do tipo POST são aceitas.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas requisições POST são aceitas.', 405);
}

// Lê o corpo da requisição e decodifica o JSON para um array associativo.
$inputData = json_decode(file_get_contents('php://input'), true);

// Verifica se houve algum erro na decodificação do JSON.
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido do frontend.', 400);
}

// Valida se os campos 'local' e 'data' foram enviados e não estão vazios.
if (empty($inputData['local']) || empty($inputData['data'])) {
    send_error('Dados de entrada inválidos. As chaves "data" e "local" são obrigatórias.', 400);
}

$local = htmlspecialchars($inputData['local']); // Simples sanitização
$data = htmlspecialchars($inputData['data']);   // Simples sanitização

// ---
// Passo 3: Definir o Schema (Estrutura) do JSON de Resposta
// ---
// Este schema define para a IA exatamente qual formato o JSON de resposta deve ter.
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
                    'metodo_cultivo' => ['type' => 'STRING', 'description' => 'Indica se é melhor em vaso ou terreno.'],
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
// O prompt instrui a IA sobre o que fazer e qual formato de resposta usar.
$userPrompt = "Aja como um especialista em jardinagem. Crie um guia de cultivo simples e prático para iniciantes, com base na seguinte localização: '$local' e data de plantio: '$data'. O guia deve sugerir 3 a 5 plantas (frutas, ervas e/ou hortaliças) adequadas para a época e local, indicando se podem ser plantadas em vaso ou diretamente no terreno. A resposta DEVE ser um JSON seguindo o schema definido.";

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

/*
// ---
// Passo 5: Atualizar o Contador no Banco de Dados (Opcional)
// ---
// O código abaixo foi comentado porque a variável $id_horta não foi definida.
// Para usá-lo, você precisaria enviar um 'id' do frontend junto com 'data' e 'local'.

include "banco_mysql.php";
try {
    // ATUALIZAÇÃO: Incrementa o contador 'guias_gerados' na tabela 'hortas'
    // A coluna foi renomeada de 'receitas_geradas' para 'guias_gerados' para maior clareza.
    $id_horta_recebido = $inputData['id_horta'] ?? null;
    if ($id_horta_recebido) {
        $sql_update = "UPDATE hortas SET guias_gerados = guias_gerados + 1 WHERE id_hortas = :id_horta";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindValue(':id_horta', $id_horta_recebido, PDO::PARAM_INT);
        $stmt_update->execute();
    }
} catch (PDOException $e) {
    // Se a atualização falhar, loga o erro mas não impede o envio do guia para o usuário.
    error_log("Falha ao atualizar contador de guias para a horta ID $id_horta_recebido: " . $e->getMessage());
}
*/

// ---
// Passo 6: Enviar a Resposta de Sucesso para o Frontend
// ---
// Envia o JSON gerado pela API diretamente para o frontend.
echo $jsonString;

?>
