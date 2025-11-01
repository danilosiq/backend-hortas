<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// 🔧 Função de resposta padronizada (nunca 4xx/5xx)
// =====================================================
function send_response($status, $mensagem, $dados = []) {
    http_response_code(200);
    echo json_encode([
        'status' => $status,
        'mensagem' => $mensagem,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// =====================================================
// 🔌 Conexão com banco
// =====================================================
include 'banco_mysql.php';

if (!isset($conn) || !$conn) {
    send_response('erro', 'Falha ao conectar ao banco de dados. Verifique a configuração.');
}

// =====================================================
// 📩 Validação do método e JSON
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response('erro', 'Método inválido. Utilize POST.');
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response('erro', 'JSON inválido enviado.');
}

$bairro = trim($inputData['bairro'] ?? '');

if ($bairro === '') {
    send_response('erro', 'O campo "bairro" é obrigatório.');
}

// =====================================================
// 🗂️ Busca hortas no banco
// =====================================================
$sql = "SELECT 
            h.nome, 
            h.descricao, 
            e.nm_rua AS endereco, 
            e.nm_bairro AS bairro
        FROM hortas h
        INNER JOIN endereco_hortas e 
            ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas
        WHERE e.nm_bairro = ?
        ORDER BY h.nome ASC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    send_response('erro', 'Erro interno ao preparar consulta SQL.');
}

mysqli_stmt_bind_param($stmt, "s", $bairro);

if (!mysqli_stmt_execute($stmt)) {
    send_response('erro', 'Erro ao executar consulta: ' . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);

$hortas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $hortas[] = [
        'nome' => $row['nome'],
        'descricao' => $row['descricao'],
        'endereco' => $row['endereco'],
        'bairro' => $row['bairro']
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// =====================================================
// ✅ Retorno final (sempre 200)
// =====================================================
if (empty($hortas)) {
    send_response('erro', "Nenhuma horta encontrada no bairro \"$bairro\".");
}

send_response('sucesso', "Hortas encontradas no bairro \"$bairro\".", [
    'bairro' => $bairro,
    'quantidade' => count($hortas),
    'hortas' => $hortas
]);
?>