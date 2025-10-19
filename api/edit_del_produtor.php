<?php
include 'banco_mysql.php';

// Editar produtor
function atualizarProdutor($id, $nome, $telefone, $email, $cpf, $horta_id) {
    global $conn;
    $sql = "UPDATE produtor SET nome_produtor=?, telefone_produtor=?, email_produtor=?, nr_cpf=?, hortas_id_hortas=? WHERE id_produtor=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $nome, $telefone, $email, $cpf, $horta_id, $id);
    return $stmt->execute();
}

// Deletar produtor
function deletarProdutor($id) {
    global $conn;
    $sql = "DELETE FROM produtor WHERE id_produtor=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}
?>
