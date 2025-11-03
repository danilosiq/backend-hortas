<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
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

// =====================================================
// Função de erro padronizada
// =====================================================
function send_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'erro', 'mensagem' => $msg]);
    exit;
}

include_once 'banco_mysql.php';
if (!$conn) send_error('Banco não conectado', 500);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) send_error('Corpo da requisição inválido', 400);

// =====================================================
// Validação de campos
// =====================================================
$token = trim($input['token'] ?? '');
$nome_produto = trim($input['nome_produto'] ?? '');
$descricao_produto = trim($input['descricao_produto'] ?? '');
$unidade = trim($input['unidade'] ?? '');
$quantidade = (float)($input['quantidade'] ?? 0);
$dt_plantio = trim($input['dt_plantio'] ?? null);
$dt_colheita = trim($input['dt_colheita'] ?? null);
$motivo = trim($input['motivo'] ?? null);

if (!$token || !$nome_produto || $quantidade <= 0)
    send_error('Campos obrigatórios ausentes ou inválidos', 422);

// =====================================================
// Valida token -> obtém produtor
// =====================================================
$stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :t LIMIT 1");
$stmt->bindValue(':t', $token);
$stmt->execute();

if ($stmt->rowCount() === 0) send_error('Token inválido', 401);
$id_produtor = (int)$stmt->fetchColumn();

// =====================================================
// Localiza horta do produtor
// =====================================================
$stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id LIMIT 1");
$stmt->bindValue(':id', $id_produtor);
$stmt->execute();

if ($stmt->rowCount() === 0) send_error('Produtor não possui horta registrada', 404);
$id_horta = (int)$stmt->fetchColumn();

// =====================================================
// Lógica de inserção
// =====================================================
try {
    $conn->beginTransaction();

    // Verifica se o produto já existe
    $p = $conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto) = LOWER(:n) LIMIT 1");
    $p->bindValue(':n', $nome_produto);
    $p->execute();

    if ($p->rowCount() > 0) {
        $id_produto = (int)$p->fetchColumn();
    } else {
        $ins = $conn->prepare("INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao)
                               VALUES (:n, :d, :u)");
        $ins->bindValue(':n', $nome_produto);
        $ins->bindValue(':d', $descricao_produto ?: null);
        $ins->bindValue(':u', $unidade ?: null);
        $ins->execute();
        $id_produto = (int)$conn->lastInsertId();
    }

    // Verifica estoque existente
    $s = $conn->prepare("SELECT id_estoques, ds_quantidade 
                         FROM estoques 
                         WHERE hortas_id_hortas = :h AND produto_id_produto = :p LIMIT 1");
    $s->bindValue(':h', $id_horta);
    $s->bindValue(':p', $id_produto);
    $s->execute();

    if ($s->rowCount() === 0) {
        $ins = $conn->prepare("INSERT INTO estoques
            (hortas_id_hortas, produto_id_produto, ds_quantidade, dt_plantio, dt_colheita)
            VALUES (:h, :p, :q, :pl, :co)");
        $ins->bindValue(':h', $id_horta);
        $ins->bindValue(':p', $id_produto);
        $ins->bindValue(':q', $quantidade);
        $ins->bindValue(':pl', $dt_plantio ?: null);
        $ins->bindValue(':co', $dt_colheita ?: null);
        $ins->execute();
        $id_estoque = (int)$conn->lastInsertId();
        $novaQtd = $quantidade;
    } else {
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $id_estoque = (int)$row['id_estoques'];
        $novaQtd = (float)$row['ds_quantidade'] + $quantidade;
        $upd = $conn->prepare("UPDATE estoques SET ds_quantidade = :q WHERE id_estoques = :id");
        $upd->bindValue(':q', $novaQtd);
        $upd->bindValue(':id', $id_estoque);
        $upd->execute();
    }

    // Registra movimento de entrada
    $m = $conn->prepare("INSERT INTO entradas_estoque
        (estoques_id_estoques, produtor_id_produtor, quantidade, motivo)
        VALUES (:e, :pr, :q, :m)");
    $m->bindValue(':e', $id_estoque);
    $m->bindValue(':pr', $id_produtor);
    $m->bindValue(':q', $quantidade);
    $m->bindValue(':m', $motivo ?: null);
    $m->execute();

    $conn->commit();
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Entrada registrada com sucesso',
        'id_produto' => $id_produto,
        'nova_quantidade' => $novaQtd
    ]);

} catch (Throwable $t) {
    $conn->rollBack();
    send_error('Erro: ' . $t->getMessage(), 500);
}
?>