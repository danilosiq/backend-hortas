<?php
include 'db_connection.php';
include 'validador_jwt.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Apenas métodos que modificam dados precisam de autenticação
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    $dados_usuario = validar_token_jwt();
    if (!$dados_usuario) {
        http_response_code(401);
        echo json_encode(['message' => 'Acesso não autorizado.']);
        exit;
    }
}

// Gerenciamento de Estoque
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar estoque
    $data = json_decode(file_get_contents("php://input"));
    $sql = "INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantidade, dt_plantio, dt_colheita) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->hortas_id_hortas, $data->produto_id_produto, $data->ds_quantidade, $data->dt_plantio, $data->dt_colheita]);
    echo json_encode(['message' => 'Estoque adicionado com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id_estoques'])) {
    // Obter estoque por ID
    $sql = "SELECT * FROM estoques WHERE id_estoques = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_estoques']]);
    $estoque = $stmt->fetch();
    echo json_encode($estoque);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar todos os estoques
    $sql = "SELECT * FROM estoques";
    $stmt = $conn->query($sql);
    $estoques = $stmt->fetchAll();
    echo json_encode($estoques);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Atualizar estoque
    $data = json_decode(file_get_contents("php://input"));
    $sql = "UPDATE estoques SET hortas_id_hortas = ?, produto_id_produto = ?, ds_quantidade = ?, dt_plantio = ?, dt_colheita = ? WHERE id_estoques = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->hortas_id_hortas, $data->produto_id_produto, $data->ds_quantidade, $data->dt_plantio, $data->dt_colheita, $data->id_estoques]);
    echo json_encode(['message' => 'Estoque atualizado com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id_estoques'])) {
    // Deletar estoque
    $sql = "DELETE FROM estoques WHERE id_estoques = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_estoques']]);
    echo json_encode(['message' => 'Estoque deletado com sucesso.']);
}
