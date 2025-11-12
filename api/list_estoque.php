<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once 'db_connection.php';

try {
    $query = "SELECT p.nm_produto, e.ds_quantidade FROM produtos p JOIN estoques e ON p.id_produto = e.produto_id_produto";
    $stmt = $conn->prepare($query);
    $stmt->execute();

    $produtos = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $produto_item = array(
            "nome" => $nm_produto,
            "quantidade" => $ds_quantidade
        );
        array_push($produtos, $produto_item);
    }

    echo json_encode($produtos);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Internal Server Error: " . $e->getMessage()));
}
