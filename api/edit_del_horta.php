<?php
include 'banco_mysql.php';

// Editar horta
function atualizarHorta($id, $nome, $descricao, $endereco_id, $cnpj, $visibilidade) {
    global $conn;
    $sql = "UPDATE hortas SET nome=?, descricao=?, endereco_hortas_id_endereco_hortas=?, nr_cnpj=?, visibilidade=? WHERE id_hortas=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisii", $nome, $descricao, $endereco_id, $cnpj, $visibilidade, $id);
    return $stmt->execute();
}

// Deletar horta
function deletarHorta($id) {
    global $conn;
    $sql = "DELETE FROM hortas WHERE id_hortas=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}
?>
