<?php
// =====================================================
// ✅ BLOCO CORS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: DELETE, POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// =====================================================
// ✅ Função de resposta padronizada
// =====================================================
function send_response($status, $mensagem, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'mensagem' => $mensagem], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// =====================================================
// ✅ Conexão com banco
// =====================================================
include_once 'banco_mysql.php';
if (!$conn) send_response('erro', 'Banco não conectado.', [], 500);

// =====================================================
// ✅ Leitura e validação do corpo da requisição
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$id_produto = (int)($input['id_produto'] ?? 0);

if (!$token || !$id_produto) send_response('erro', 'Token e id_produto são obrigatórios.', [], 400);

// =====================================================
// ✅ Validação do token
// =====================================================
try {
    $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :t LIMIT 1");
    $stmt->bindValue(':t', $token);
    $stmt->execute();
    if ($stmt->rowCount() === 0) send_response('erro', 'Token inválido ou expirado.', [], 401);
    $id_produtor = (int)$stmt->fetchColumn();
} catch (Throwable $t) {
    send_response('erro', 'Erro ao validar token: ' . $t->getMessage(), [], 500);
}

// =====================================================
// ✅ Deletar produto do estoque
// =====================================================
try {
    $conn->beginTransaction();

    // Verifica se o produto existe na horta do produtor
    $stmt = $conn->prepare("
        SELECT e.id_estoques
        FROM estoques e
        JOIN hortas h ON e.hortas_id_hortas = h.id_hortas
        WHERE e.produto_id_produto = :p AND h.produtor_id_produtor = :pr
        LIMIT 1
    ");
    $stmt->bindValue(':p', $id_produto);
    $stmt->bindValue(':pr', $id_produtor);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        send_response('erro', 'Produto não encontrado na horta do produtor.', [], 404);
    }

    $id_estoque = (int)$stmt->fetchColumn();

    // Deleta do estoque
    $delEstoque = $conn->prepare("DELETE FROM estoques WHERE id_estoques = :id");
    $delEstoque->bindValue(':id', $id_estoque);
    $delEstoque->execute();

    // Opcional: Deleta entradas e saídas relacionadas
    $conn->prepare("DELETE FROM entradas_estoque WHERE estoques_id_estoques = :id")->execute([':id' => $id_estoque]);
    $conn->prepare("DELETE FROM saidas_estoque WHERE estoques_id_estoques = :id")->execute([':id' => $id_estoque]);

    $conn->commit();

    send_response('sucesso', 'Produto deletado com sucesso.', ['id_produto' => $id_produto]);
} catch (Throwable $t) {
    $conn->rollBack();
    send_response('erro', 'Erro ao deletar produto: ' . $t->getMessage(), [], 500);
}