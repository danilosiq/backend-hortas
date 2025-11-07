<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
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
// 🧩 Inclui banco e JWT
// =====================================================
include 'banco_mysql.php';
include 'validador_jwt.php';

$dados_usuario = validar_token_jwt();
$id_produtor = $dados_usuario['id_produtor'] ?? null;

if (!$id_produtor) {
    send_error('Token inválido ou ausente.', 401);
}

// =====================================================
// 📩 Lê o corpo da requisição
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Use PUT ou POST.', 405);
}

$inputData = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido recebido.', 400);
}

$id_horta = $inputData['id_horta'] ?? null;

if (!$id_horta) {
    send_error('O campo "id_horta" é obrigatório para editar.', 400);
}

// =====================================================
// 🧱 Monta UPDATE dinâmico
// =====================================================
$campos = [];
$valores = [];

$possiveisCampos = [
    'nome' => 's',
    'descricao' => 's',
    'endereco_id' => 'i',
    'cnpj' => 's',
    'visibilidade' => 'i'
];

foreach ($possiveisCampos as $campo => $tipo) {
    if (isset($inputData[$campo])) {
        $campos[] = "$campo = ?";
        $valores[] = $inputData[$campo];
    }
}

if (empty($campos)) {
    send_error('Nenhum campo foi enviado para atualização.', 400);
}

// =====================================================
// 💾 Executa no banco
// =====================================================
try {
    $sql = "UPDATE hortas 
            SET " . implode(", ", $campos) . " 
            WHERE id_hortas = ? 
              AND produtor_id_produtor = ?";

    $stmt = $conn->prepare($sql);

    $tipos = str_repeat('s', count($valores)) . "ii"; // tipos dinâmicos
    $valores[] = $id_horta;
    $valores[] = $id_produtor;

    // vincula os parâmetros
    $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Horta atualizada com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma alteração feita ou horta não encontrada.']);
    }
} catch (Throwable $e) {
    send_error('Erro ao atualizar horta: ' . $e->getMessage(), 500);
}
?>