<?php
include 'db_connection.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Gerenciamento de Produtores
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar produtor
    $data = json_decode(file_get_contents("php://input"));
    $sql = "INSERT INTO produtor (nm_produtor, dt_nascimento, cpf, rg, cep, pais, estado, cidade, bairro, local_exato, email, senha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->nm_produtor, $data->dt_nascimento, $data->cpf, $data->rg, $data->cep, $data->pais, $data->estado, $data->cidade, $data->bairro, $data->local_exato, $data->email, password_hash($data->senha, PASSWORD_BCRYPT)]);
    echo json_encode(['message' => 'Produtor adicionado com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id_produtor'])) {
    // Obter produtor por ID
    $sql = "SELECT * FROM produtor WHERE id_produtor = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_produtor']]);
    $produtor = $stmt->fetch();
    echo json_encode($produtor);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar todos os produtores
    $sql = "SELECT * FROM produtor";
    $stmt = $conn->query($sql);
    $produtores = $stmt->fetchAll();
    echo json_encode($produtores);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Atualizar produtor
    $data = json_decode(file_get_contents("php://input"));
    $sql = "UPDATE produtor SET nm_produtor = ?, dt_nascimento = ?, cpf = ?, rg = ?, cep = ?, pais = ?, estado = ?, cidade = ?, bairro = ?, local_exato = ?, email = ? WHERE id_produtor = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data->nm_produtor, $data->dt_nascimento, $data->cpf, $data->rg, $data->cep, $data->pais, $data->estado, $data->cidade, $data->bairro, $data->local_exato, $data->email, $data->id_produtor]);
    echo json_encode(['message' => 'Produtor atualizado com sucesso.']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['id_produtor'])) {
    // Deletar produtor
    $sql = "DELETE FROM produtor WHERE id_produtor = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id_produtor']]);
    echo json_encode(['message' => 'Produtor deletado com sucesso.']);
}
