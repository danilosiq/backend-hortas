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

// Gerenciamento de Hortas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar horta
    $data = json_decode(file_get_contents("php://input"));
    $sql = "INSERT INTO hortas (nm_horta, cep_horta, pais_horta, estado_horta, cidade_horta, bairro_horta, local_exato_horta, produtor_id_produtor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->nm_horta, $data->cep_horta, $data->pais_horta, $data->estado_horta, $data->cidade_horta, $data->bairro_horta, $data->local_exato_horta, $data->produtor_id_produtor]);
    echo json_encode(['message' => 'Horta adicionada com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id_hortas'])) {
    // Obter horta por ID
    $sql = "SELECT * FROM hortas WHERE id_hortas = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_hortas']]);
    $horta = $stmt->fetch();
    echo json_encode($horta);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar todas as hortas
    $sql = "SELECT * FROM hortas";
    $stmt = $conn->query($sql);
    $hortas = $stmt->fetchAll();
    echo json_encode($hortas);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Atualizar horta
    $data = json_decode(file_get_contents("php://input"));
    $sql = "UPDATE hortas SET nm_horta = ?, cep_horta = ?, pais_horta = ?, estado_horta = ?, cidade_horta = ?, bairro_horta = ?, local_exato_horta = ?, produtor_id_produtor = ? WHERE id_hortas = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->nm_horta, $data->cep_horta, $data->pais_horta, $data->estado_horta, $data->cidade_horta, $data->bairro_horta, $data->local_exato_horta, $data->produtor_id_produtor, $data->id_hortas]);
    echo json_encode(['message' => 'Horta atualizada com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id_hortas'])) {
    // Deletar horta
    $sql = "DELETE FROM hortas WHERE id_hortas = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_hortas']]);
    echo json_encode(['message' => 'Horta deletada com sucesso.']);
}
