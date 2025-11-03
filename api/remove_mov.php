<?php
// =====================================================
// ✅ BLOCO CORS - sempre deve vir antes de qualquer saída
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
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
// ✅ Conexão com o banco
// =====================================================
include_once 'banco_mysql.php';
if (!$conn) {
    send_response('erro', 'Banco não conectado.', [], 500);
}

// =====================================================
// ✅ Leitura e validação do corpo da requisição
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$id_produto = (int)($input['id_produto'] ?? 0);
$quantidade = (float)($input['quantidade'] ?? 0);
$motivo = trim($input['motivo'] ?? '');

if (!$token || !$id_produto || $quantidade <= 0) {
    send_response('erro', 'Dados inválidos. Verifique token, produto e quantidade.', [], 400);
}

// =====================================================
// ✅ Validação do token e identificação do produtor
// =====================================================
try {
    $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :t LIMIT 1");
    $stmt->bindValue(':t', $token);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        send_response('erro', 'Token inválido ou expirado.', [], 401);
    }

    $id_produtor = (int)$stmt->fetchColumn();
} catch (Throwable $t) {
    send_response('erro', 'Erro ao validar token: ' . $t->getMessage(), [], 500);
}

// =====================================================
// ✅ Obtém horta do produtor
// =====================================================
try {
    $stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id LIMIT 1");
    $stmt->bindValue(':id', $id_produtor);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        send_response('erro', 'Produtor não possui horta cadastrada.', [], 404);
    }

    $id_horta = (int)$stmt->fetchColumn();
} catch (Throwable $t) {
    send_response('erro', 'Erro ao buscar horta: ' . $t->getMessage(), [], 500);
}

// =====================================================
// ✅ Processa saída do estoque
// =====================================================
try {
    $conn->beginTransaction();

    $s = $conn->prepare("
        SELECT id_estoques, ds_quantidade 
        FROM estoques 
        WHERE hortas_id_hortas = :h 
          AND produto_id_produto = :p 
        LIMIT 1
    ");
    $s->bindValue(':h', $id_horta);
    $s->bindValue(':p', $id_produto);
    $s->execute();

    if ($s->rowCount() === 0) {
        $conn->rollBack();
        send_response('erro', 'Produto não encontrado no estoque.', [], 404);
    }

    $row = $s->fetch(PDO::FETCH_ASSOC);
    $id_estoque = (int)$row['id_estoques'];
    $qtd_atual = (float)$row['ds_quantidade'];

    if ($quantidade > $qtd_atual) {
        $conn->rollBack();
        send_response('erro', 'A quantidade de saída é maior que o estoque disponível.', [], 400);
    }

    $novaQtd = $qtd_atual - $quantidade;

    // Atualiza estoque
    $upd = $conn->prepare("UPDATE estoques SET ds_quantidade = :q WHERE id_estoques = :id");
    $upd->bindValue(':q', $novaQtd);
    $upd->bindValue(':id', $id_estoque);
    $upd->execute();

    // Registra saída
    $ins = $conn->prepare("
        INSERT INTO saidas_estoque(estoques_id_estoques, produtor_id_produtor, quantidade, motivo)
        VALUES (:e, :p, :q, :m)
    ");
    $ins->bindValue(':e', $id_estoque);
    $ins->bindValue(':p', $id_produtor);
    $ins->bindValue(':q', $quantidade);
    $ins->bindValue(':m', $motivo ?: null);
    $ins->execute();

    $conn->commit();

    send_response('sucesso', 'Saída registrada com sucesso.', [
        'id_produto' => $id_produto,
        'nova_quantidade' => $novaQtd
    ]);

} catch (Throwable $t) {
    $conn->rollBack();
    send_response('erro', 'Erro ao registrar saída: ' . $t->getMessage(), [], 500);
}