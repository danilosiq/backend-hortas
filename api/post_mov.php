<?php
include "banco_mysql.php";
include "validador_jwt.php";

// Função padrão de resposta
function send_response($status, $mensagem, $extra = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// Recebe JSON
$dados = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido");
}

// Valida token e pega ID do produtor
$usuario = validar_token_jwt();
$id_produtor = $usuario['id_produtor'] ?? null;
if (!$id_produtor) {
    send_response("erro", "Token inválido");
}

// Campos obrigatórios
foreach (['id_produto','quantidade','tipo'] as $campo) {
    if (!isset($dados[$campo])) {
        send_response("erro", "Campo obrigatório: $campo");
    }
}

$id_produto = (int)$dados['id_produto'];
$quantidade = (float)$dados['quantidade'];
$tipo = $dados['tipo'];
$motivo = $dados['motivo'] ?? null;

// Pega a horta do produtor
$sqlHorta = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id LIMIT 1");
$sqlHorta->bindValue(':id', $id_produtor);
$sqlHorta->execute();
if ($sqlHorta->rowCount() === 0) {
    send_response("erro", "Produtor não possui horta");
}
$horta = $sqlHorta->fetch(PDO::FETCH_ASSOC);
$id_horta = $horta['id_hortas'];

// Verifica se já existe estoque
$sqlEstoque = $conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas = :id_horta AND produto_id_produto = :id_produto LIMIT 1");
$sqlEstoque->bindValue(':id_horta', $id_horta);
$sqlEstoque->bindValue(':id_produto', $id_produto);
$sqlEstoque->execute();

$conn->beginTransaction();
try {
    if ($sqlEstoque->rowCount() === 0) {
        // Novo estoque
        $novaQuantidade = $tipo === 'entrada' ? $quantidade : -$quantidade;
        $sqlInsertEstoque = $conn->prepare("INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantidade) VALUES (:id_horta, :id_produto, :quantidade)");
        $sqlInsertEstoque->bindValue(':id_horta', $id_horta);
        $sqlInsertEstoque->bindValue(':id_produto', $id_produto);
        $sqlInsertEstoque->bindValue(':quantidade', $novaQuantidade);
        $sqlInsertEstoque->execute();
        $id_estoque = $conn->lastInsertId();
    } else {
        // Atualiza estoque existente
        $estoque = $sqlEstoque->fetch(PDO::FETCH_ASSOC);
        $id_estoque = $estoque['id_estoques'];
        $novaQuantidade = $tipo === 'entrada' ? $estoque['ds_quantidade'] + $quantidade : $estoque['ds_quantidade'] - $quantidade;
        if ($novaQuantidade < 0) $novaQuantidade = 0;
        $sqlUpdate = $conn->prepare("UPDATE estoques SET ds_quantidade = :quantidade WHERE id_estoques = :id_estoque");
        $sqlUpdate->bindValue(':quantidade', $novaQuantidade);
        $sqlUpdate->bindValue(':id_estoque', $id_estoque);
        $sqlUpdate->execute();
    }

    // Registrar movimentação
    if ($tipo === 'entrada') {
        $sqlMov = $conn->prepare("INSERT INTO entradas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    } else {
        $sqlMov = $conn->prepare("INSERT INTO saidas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    }
    $sqlMov->bindValue(':id_estoque', $id_estoque);
    $sqlMov->bindValue(':id_produtor', $id_produtor);
    $sqlMov->bindValue(':quantidade', $quantidade);
    $sqlMov->bindValue(':motivo', $motivo);
    $sqlMov->execute();

    $conn->commit();
    send_response("sucesso", "Movimentadffsção registrada com sucesso", [
        'id_estoque' => $id_estoque,
        'nova_quantidade' => $novaQuantidade
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    send_response("erro", "Erro no banco: ".$e->getMessage());
} catch (Throwable $t) {
    $conn->rollBack();
    send_response("erro", "Erro interno: ".$t->getMessage());
}