<?php
// Cabeçalhos para permitir requisições de outras origens (CORS) e definir o tipo de conteúdo
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Este endpoint usará GET
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Função de utilidade para enviar erros
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// Em caso de requisição OPTIONS (pre-flight request), apenas retorna sucesso.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inclui os arquivos necessários
include "banco_mysql.php";
include "validador_jwt.php"; // Nosso novo validador de token

// ---
// Passo 1: Autenticação do Usuário via JWT
// ---
$dados_usuario = validar_token_jwt();
$id_produtor = $dados_usuario['id_produtor'] ?? null;

if (!$id_produtor) {
    send_error('Token inválido ou não contém o ID do produtor.', 401);
}

// ---
// Passo 2: Buscar Dados do Usuário e do Estoque no Banco de Dados
// ---
$lista_estoque_formatada = "";
$localizacao_horta = "";

try {
    // Busca o ID da horta e a localização do produtor logado
    $sql_horta = "SELECT 
                    h.hortas_id_hortas, 
                    e.nm_cidade, 
                    e.nm_estado 
                  FROM produtor p
                  JOIN hortas h ON p.hortas_id_hortas = h.id_hortas
                  JOIN endereco_hortas e ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas
                  WHERE p.id_produtor = :id_produtor";
    
    $stmt_horta = $conn->prepare($sql_horta);
    $stmt_horta->bindValue(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmt_horta->execute();
    $horta_info = $stmt_horta->fetch(PDO::FETCH_ASSOC);

    if (!$horta_info) {
        send_error('Horta não encontrada para este produtor.', 404);
    }
    $id_horta = $horta_info['hortas_id_hortas'];
    $localizacao_horta = $horta_info['nm_cidade'] . ", " . $horta_info['nm_estado'];

    // Busca os itens atuais no estoque da horta
    $sql_estoque = "SELECT DISTINCT 
                      pr.nm_produto 
                    FROM estoques es
                    JOIN produtos pr ON es.produto_id_produto = pr.id_produto
                    WHERE es.hortas_id_hortas = :id_horta AND es.ds_quantiade > 0";
    
    $stmt_estoque = $conn->prepare($sql_estoque);
    $stmt_estoque->bindValue(':id_horta', $id_horta, PDO::PARAM_INT);
    $stmt_estoque->execute();
    $itens_estoque = $stmt_estoque->fetchAll(PDO::FETCH_COLUMN);

    if (empty($itens_estoque)) {
       $lista_estoque_formatada = "O estoque está vazio.";
    } else {
       $lista_estoque_formatada = implode(', ', $itens_estoque);
    }

} catch (PDOException $e) {
    send_error("Erro ao consultar o banco de dados: " . $e->getMessage());
}

// ---
// Passo 3: Construir o Prompt para a API Gemini
// ---
// Carrega a chave da API Gemini do arquivo .env
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$geminiApiKey = $_ENV['chave_gemini'] ?? null;
if (!$geminiApiKey) {
    send_error('A chave da API Gemini (chave_gemini) não foi encontrada.');
}

$data_atual = date('d/m/Y');

$userPrompt = "Aja como um agrônomo e consultor de negócios agrícolas para pequenos produtores.
Com base na data de hoje ($data_atual) e na localização da horta ($localizacao_horta), analise a lista de produtos que o produtor já tem em estoque: [$lista_estoque_formatada].
Sugira 3 novas culturas (frutas, legumes ou ervas) que sejam inteligentes para plantar agora.
Para cada sugestão, explique o motivo da escolha, considerando:
1.  **Sazonalidade:** Se é a época ideal de plantio na região.
2.  **Tendência de Mercado:** Se o produto tem boa demanda ou pode ser um diferencial.
3.  **Complementaridade:** Como a nova cultura complementa o que já é produzido.
4.  **Rotação de Culturas:** Se ajuda a manter o solo saudável.

A resposta DEVE ser um JSON bem formatado, contendo uma chave 'sugestoes' que é um array de objetos. Cada objeto deve ter as chaves 'produto', 'motivo_sazonalidade', 'motivo_mercado' e 'dica_cultivo'. Não adicione markdown.";

// ---
// Passo 4: Chamar a API Gemini
// ---
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $geminiApiKey;
$payload = json_encode(['contents' => [['parts' => [['text' => $userPrompt]]]]]);

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
    send_error("Erro ao comunicar com a API Gemini. Código: $httpCode", $httpCode > 0 ? $httpCode : 500);
}

// ---
// Passo 5: Enviar a Resposta para o Frontend
// ---
$result = json_decode($apiResponse, true);
// Extrai o texto da resposta da API, que deve ser o nosso JSON.
$jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$jsonString) {
    send_error("A resposta da API não continha o conteúdo esperado.");
}

// Define o header como JSON e envia a resposta.
header('Content-Type: application/json');
echo $jsonString;
?>
