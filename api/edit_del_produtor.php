<?php
include 'banco_mysql.php';

include "validador_jwt.php"; // Nosso novo validador de token

// ---
// Passo 1: Autenticação do Usuário via JWT
// ---
$dados_usuario = validar_token_jwt();
$id_produtor = $dados_usuario['id_produtor'] ?? null;

if (!$id_produtor) {
    send_error('Token inválido ou não contém o ID do produtor.', 401);
}
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
