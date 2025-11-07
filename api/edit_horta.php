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
// 🔧 Função de resposta padronizada (sempre HTTP 200)
// =====================================================
function send_response($success, $message, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit();
}

// =====================================================
// 🔑 Importa dependências e valida JWT
// =====================================================
include 'banco_mysql.php';
include 'validador_jwt.php';

$dados_usuario = validar_token_jwt();
$id_produtor = $dados_usuario['id_produtor'] ?? null;

if (!$id_produtor) {
    send_response(false, "Token inválido ou não contém o ID do produtor.");
}

// =====================================================
// 📩 Valida método e corpo JSON
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, "Método inválido. Use POST.");
}

$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response(false, "JSON inválido recebido.");
}

if (empty($input['id_horta'])) {
    send_response(false, "O campo 'id_horta' é obrigatório.");
}

// =====================================================
// 🧱 Extrai dados opcionais
// =====================================================
$id_horta = $input['id_horta'];
$nome = $input['nome'] ?? null;
$descricao = $input['descricao'] ?? null;
$endereco_id = $input['endereco_hortas_id_endereco_hortas'] ?? null;
$cnpj = $input['nr_cnpj'] ?? null;
$visibilidade = $input['visibilidade'] ?? null;

// =====================================================
// 🛠️ Monta SQL dinâmico conforme os campos enviados
// =====================================================
$campos = [];
$valores = [];

if ($nome !== null) {
    $campos[] = "nome = ?";
    $valores[] = $nome;
}
if ($descricao !== null) {
    $campos[] = "descricao = ?";
    $valores[] = $descricao;
}
if ($endereco_id !== null) {
    $campos[] = "endereco_hortas_id_endereco_hortas = ?";
    $valores[] = $endereco_id;
}
if ($cnpj !== null) {
    $campos[] = "nr_cnpj = ?";
    $valores[] = $cnpj;
}
if ($visibilidade !== null) {
    $campos[] = "visibilidade = ?";
    $valores[] = $visibilidade;
}

if (empty($campos)) {
    send_response(false, "Nenhum campo foi enviado para atualização.");
}

// =====================================================
// 💾 Executa atualização no banco
// =====================================================
try {
    $sql = "UPDATE hortas SET " . implode(", ", $campos) . " WHERE id_hortas = ? AND produtor_id_produtor = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        send_response(false, "Erro ao preparar statement: " . $conn->error);
    }

    // Vincula parâmetros dinamicamente
    $tipos = str_repeat("s", count($valores)) . "ii"; // strings + id_horta + id_produtor
    $valores[] = $id_horta;
    $valores[] = $id_produtor;

    $stmt->bind_param($tipos, ...$valores);

    $success = $stmt->execute();

    if ($success && $stmt->affected_rows > 0) {
        send_response(true, "Horta atualizada com sucesso.");
    } else {
        send_response(false, "Nenhuma alteração realizada ou horta não encontrada.");
    }

} catch (Throwable $e) {
    send_response(false, "Erro interno: " . $e->getMessage());
}
?>