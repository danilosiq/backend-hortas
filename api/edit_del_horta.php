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
