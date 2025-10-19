<?php
include 'banco_mysql.php';

// Editar estoque
function atualizarEstoque($id, $quantidade, $dt_plantio, $dt_colheita, $dt_validade) {
    global $conn;
    $sql = "UPDATE estoques SET ds_quantiade=?, dt_plantio=?, dt_colheita=?, dt_validade=? WHERE id_estoques=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dsssi", $quantidade, $dt_plantio, $dt_colheita, $dt_validade, $id);
    return $stmt->execute();
}

// Deletar estoque
function deletarEstoque($id) {
    global $conn;
    $sql = "DELETE FROM estoques WHERE id_estoques=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}
?>
