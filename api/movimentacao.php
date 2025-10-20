<?php
include 'banco_mysql.php';

// Registrar entrada
function registrarEntrada($estoque_id, $produtor_id, $quantidade, $motivo) {
    global $conn;
    $sql = "INSERT INTO entradas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iids", $estoque_id, $produtor_id, $quantidade, $motivo);
    $stmt->execute();

    // Atualizar estoque
    $sql2 = "UPDATE estoques SET ds_quantiade = ds_quantiade + ? WHERE id_estoques=?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("di", $quantidade, $estoque_id);
    return $stmt2->execute();
}

// Registrar saída
function registrarSaida($estoque_id, $produtor_id, $quantidade, $motivo) {
    global $conn;
    $sql = "INSERT INTO saidas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iids", $estoque_id, $produtor_id, $quantidade, $motivo);
    $stmt->execute();

    // Atualizar estoque
    $sql2 = "UPDATE estoques SET ds_quantiade = ds_quantiade - ? WHERE id_estoques=?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("di", $quantidade, $estoque_id);
    return $stmt2->execute();
}
?>
