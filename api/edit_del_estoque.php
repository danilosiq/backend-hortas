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
