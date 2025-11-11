<?php
include 'db_connection.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Roteamento para a tabela movimentacao_estoque
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar movimentacao
    $data = json_decode(file_get_contents("php://input"));
    $sql = "INSERT INTO movimentacao_estoque (id_estoques, tipo_movimentacao, quantidade, dt_movimentacao, motivo) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->id_estoques, $data->tipo_movimentacao, $data->quantidade, $data->dt_movimentacao, $data->motivo]);
    echo json_encode(['message' => 'Movimentacao adicionada com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id_movimentacao'])) {
    // Obter movimentacao por ID
    $sql = "SELECT * FROM movimentacao_estoque WHERE id_movimentacao = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_movimentacao']]);
    $movimentacao = $stmt->fetch();
    echo json_encode($movimentacao);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar todos as movimentacoes
    $sql = "SELECT * FROM movimentacao_estoque";
    $stmt = $conn->query($sql);
    $movimentacoes = $stmt->fetchAll();
    echo json_encode($movimentacoes);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Atualizar movimentacao
    $data = json_decode(file_get_contents("php://input"));
    $sql = "UPDATE movimentacao_estoque SET id_estoques = ?, tipo_movimentacao = ?, quantidade = ?, dt_movimentacao = ?, motivo = ? WHERE id_movimentacao = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->id_estoques, $data->tipo_movimentacao, $data->quantidade, $data->dt_movimentacao, $data->motivo, $data->id_movimentacao]);
    echo json_encode(['message' => 'Movimentacao atualizada com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id_movimentacao'])) {
    // Deletar movimentacao
    $sql = "DELETE FROM movimentacao_estoque WHERE id_movimentacao = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_movimentacao']]);
    echo json_encode(['message' => 'Movimentacao deletada com sucesso.']);
}
