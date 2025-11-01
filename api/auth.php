<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// =====================================================
// 🔧 Função de resposta padronizada
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    http_response_code(200); // Sempre retorna 200 OK
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 🔑 Passo 1: Conectar ao banco
// =====================================================
try {
    include "banco_mysql.php";
} catch (Throwable $e) {
    send_response("erro", "Erro ao conectar ao banco de dados.");
}

// =====================================================
// 📩 Passo 2: Validar método e JSON recebido
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response('erro', 'Método não permitido. Apenas POST é aceito.');
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response('erro', 'JSON inválido recebido.');
}

if (empty($input['token']) || empty($input['data_atual'])) {
    send_response('erro', 'Campos obrigatórios: token e data_atual.');
}

$jwt = htmlspecialchars(strip_tags($input['token']));
$dataAtual = htmlspecialchars(strip_tags($input['data_atual']));

// =====================================================
// 🔍 Passo 3: Buscar sessão no banco
// =====================================================
try {
    $sql = "SELECT data_expiracao, produtor_id_produtor 
            FROM session 
            WHERE jwt_token = :jwt
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':jwt', $jwt);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        send_response('erro', 'Sessão inválida ou não encontrada.');
    }

    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
    $dataExpiracao = $sessao['data_expiracao'];

    // =====================================================
    // ⏰ Passo 4: Verificar expiração
    // =====================================================
    if (strtotime($dataAtual) > strtotime($dataExpiracao)) {
        // Deleta sessão expirada (opcional)
        $delete = $conn->prepare("DELETE FROM session WHERE jwt_token = :jwt");
        $delete->bindValue(':jwt', $jwt);
        $delete->execute();

        send_response('erro', 'Sessão expirada.');
    }

    // =====================================================
    // ✅ Passo 5: Retornar sucesso
    // =====================================================
    send_response('sucesso', 'Sessão válida.', [
        'id_produtor' => $sessao['produtor_id_produtor'],
        'expira_em' => $dataExpiracao
    ]);

} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    send_response("erro", "Erro no servidor (DB).");
} catch (Throwable $t) {
    error_log("Throwable: " . $t->getMessage());
    send_response("erro", "Erro interno no servidor.");
}
?>