<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

include "banco_mysql.php";

try {
    $stmt = $conn->query("SELECT h.*, e.* FROM hortas h JOIN endereco_hortas e ON h.endereco_hortas_id_endereco_hortas = e.id_endereco_hortas");
    $hortas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($hortas);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "erro", "mensagem" => $e->getMessage()]);
}
?>
