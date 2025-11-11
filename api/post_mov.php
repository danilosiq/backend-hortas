<?php
include "db_connection.php";
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $estoque_id = $data['estoque_id'];
    $tipo = $data['tipo'];
    $quantidade = $data['quantidade'];
    $motivo = $data['motivo'];

    // Iniciar transação
    $conn->beginTransaction();

    try {
        // Obter quantidade atual
        $stmt = $conn->prepare("SELECT ds_quantidade FROM estoques WHERE id_estoques = :id");
        $stmt->bindParam(':id', $estoque_id);
        $stmt->execute();
        $estoque = $stmt->fetch(PDO::FETCH_ASSOC);
        $qtd_atual = $estoque['ds_quantidade'];

        if ($tipo == 'saida' && $qtd_atual < $quantidade) {
            throw new Exception("Quantidade insuficiente em estoque.");
        }

        // Atualizar estoque
        $nova_qtd = ($tipo == 'entrada') ? $qtd_atual + $quantidade : $qtd_atual - $quantidade;
        $stmt = $conn->prepare("UPDATE estoques SET ds_quantidade = :qtd WHERE id_estoques = :id");
        $stmt->bindParam(':qtd', $nova_qtd);
        $stmt->bindParam(':id', $estoque_id);
        $stmt->execute();

        // Inserir movimentação
        $stmt = $conn->prepare("INSERT INTO movimentacao_estoque (id_estoques, tipo_movimentacao, quantidade, dt_movimentacao, motivo) VALUES (:id, :tipo, :qtd, NOW(), :motivo)");
        $stmt->bindParam(':id', $estoque_id);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':qtd', $quantidade);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->execute();

        // Commit
        $conn->commit();
        echo json_encode(["message" => "Movimentação registrada com sucesso."]);

    } catch (Exception $e) {
        // Rollback
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(["message" => $e->getMessage()]);
    }
}
