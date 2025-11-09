<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS"); // Adicionando DELETE
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// =====================================================
// 🚫 Função para resposta JSON padronizada
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 🔌 Conexão com o banco
// =====================================================
try {
    include "banco_mysql.php";
} catch (Throwable $e) {
    send_response("erro", "Falha ao conectar ao banco: " . $e->getMessage());
}

// =====================================================
// 📥 Recebe e valida o JSON
// =====================================================
// Lida com métodos POST e DELETE
$dados = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido recebido.");
}

// =ções
if (empty($dados['id_horta'])) {
    send_response("erro", "O campo 'id_horta' é obrigatório para a exclusão.");
}

$id_horta = (int)$dados['id_horta'];

try {
    $conn->beginTransaction();

    // =====================================================
    // 1️⃣ Buscar ID do Endereço (necessário para exclusão em cascata manual)
    // =====================================================
    $sql_busca_endereco = "SELECT endereco_hortas_id_endereco_hortas FROM hortas WHERE id_horta = :id_horta";
    $stmt = $conn->prepare($sql_busca_endereco);
    $stmt->execute([':id_horta' => $id_horta]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        $conn->rollBack();
        send_response("erro", "Horta com ID $id_horta não encontrada.");
    }

    $id_endereco = $resultado['endereco_hortas_id_endereco_hortas'];

    // =====================================================
    // 2️⃣ Deletar Horta
    // =====================================================
    $sql_horta = "DELETE FROM hortas WHERE id_horta = :id_horta";
    $stmt = $conn->prepare($sql_horta);
    $stmt->execute([':id_horta' => $id_horta]);
    
    $linhas_horta = $stmt->rowCount();

    // =====================================================
    // 3️⃣ Deletar Endereço Associado
    // =====================================================
    $sql_endereco = "DELETE FROM endereco_hortas WHERE id_endereco_hortas = :id_endereco";
    $stmt = $conn->prepare($sql_endereco);
    $stmt->execute([':id_endereco' => $id_endereco]);

    $linhas_endereco = $stmt->rowCount();
    
    $conn->commit();

    if ($linhas_horta > 0) {
        send_response("sucesso", "Horta e endereço associado excluídos com sucesso.", [
            'id_horta_excluida' => $id_horta,
            'id_endereco_excluido' => $id_endereco
        ]);
    } else {
        // Se a horta não foi encontrada antes da exclusão (apesar da busca inicial), mas a lógica de transação garante
        // que chegamos aqui, geralmente significa que a horta já foi deletada ou o ID estava incorreto.
        send_response("aviso", "Nenhuma horta foi excluída. ID $id_horta não encontrado.", [
            'id_horta_tentada' => $id_horta
        ]);
    }

} catch (PDOException $e) {
    $conn->rollBack();
    send_response("erro", "Erro no banco de dados durante a exclusão: " . $e->getMessage());
} catch (Throwable $t) {
    send_response("erro", "Erro interno durante a exclusão: " . $t->getMessage());
}
?>