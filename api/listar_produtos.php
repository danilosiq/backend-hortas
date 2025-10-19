<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include "banco_mysql.php";

try {
    // Query simples para selecionar todos os produtos disponíveis no catálogo
    $sql = "SELECT id_produto, nm_produto, descricao, unidade_medida_padrao FROM produtos ORDER BY nm_produto ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $produtos_array = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode($produtos_array);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $resposta = array("status" => "erro", "mensagem" => "Ocorreu um erro ao buscar os produtos.");
    error_log($e->getMessage()); // Loga o erro para o desenvolvedor
    echo json_encode($resposta);
}
?>
