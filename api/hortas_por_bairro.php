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
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// 🔧 Função de erro
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit();
}

// =====================================================
// 🔌 Conexão com banco
// =====================================================
include 'banco_mysql.php';

if (!isset($conn) || !$conn) {
    send_error('Falha na conexão com o banco de dados. Verifique banco_mysql.php.');
}

// =====================================================
// 📩 Recebe e valida JSON
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas POST é aceito.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido enviado.', 400);
}

$bairro = trim($inputData['bairro'] ?? '');

if (empty($bairro)) {
    send_error('O campo "bairro" é obrigatório.', 400);
}

// =====================================================
// 🗂️ Busca hortas no banco
// =====================================================
$sql = "SELECT h.nome, h.descricao, e.nm_rua AS endereco, e.nm_bairro AS bairro
        FROM hortas h
        INNER JOIN endereco_hortas e 
            ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas
        WHERE e.nm_bairro = ?
        ORDER BY h.nome ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    send_error('Erro ao preparar consulta SQL: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $bairro);
mysqli_stmt_execute($stmt);
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
// ✅ Retorno final
// =====================================================
echo json_encode([
    'bairro' => $bairro,
    'quantidade' => count($hortas),
    'hortas' => $hortas
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>