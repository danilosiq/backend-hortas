<?php
// Permite o acesso de qualquer origem
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Inclui o arquivo de conexão
include "db_connection.php";

// Prepara a consulta SQL para selecionar todos os produtos
$query = "SELECT * FROM produtos";

try {
    // Prepara e executa a consulta
    $stmt = $conn->prepare($query);
    $stmt->execute();

    // Busca todos os resultados
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retorna os produtos em formato JSON
    echo json_encode($produtos);

} catch(PDOException $e) {
    // Em caso de erro, retorna uma mensagem de erro
    http_response_code(500);
    echo json_encode(array("message" => "Não foi possível buscar os produtos. Erro: " . $e->getMessage()));
}
