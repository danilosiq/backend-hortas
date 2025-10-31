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
// ⚠️ Função padrão para retornar erro
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['status' => 'erro', 'mensagem' => $message]);
    exit();
}

// =====================================================
// 📡 Método permitido: apenas POST
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas POST é aceito.', 405);
}

// =====================================================
// 🧾 Lê o corpo JSON da requisição
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || empty($input['jwt']) || empty($input['momento'])) {
    send_error('JSON inválido ou campos obrigatórios ausentes: jwt e momento.', 400);
}

// =====================================================
// 🧩 Conexão com o banco
// =====================================================
include "banco_mysql.php";

// =====================================================
// 🔍 Verifica se o JWT existe na tabela `session`
// =====================================================
try {
    $sql = "SELECT id, data_criacao, data_expiracao, produtor_id_produtor 
            FROM session 
            WHERE jwt_token = :jwt
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':jwt', $input['jwt']);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        send_error('Sessão não encontrada. Faça login novamente.', 401);
    }

    $sessao = $stmt->fetch(PDO::FETCH_ASSOC);

    // =====================================================
    // ⏰ Verifica expiração
    // =====================================================
    $momentoAtual = strtotime($input['momento']);
    $expiraEm = strtotime($sessao['data_expiracao']);

    if ($momentoAtual > $expiraEm) {
        send_error('Sessão expirada. Por favor, faça login novamente.', 401);
    }

    // =====================================================
    // ✅ Sessão válida
    // =====================================================
    http_response_code(200);
    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Sessão válida.',
        'id_sessao' => $sessao['id'],
        'id_produtor' => $sessao['produtor_id_produtor'],
        'expira_em' => $sessao['data_expiracao']
    ]);
    exit();

} catch (PDOException $e) {
    error_log("Erro DB: " . $e->getMessage());
    send_error('Erro ao acessar o banco de dados.', 500);
} catch (Throwable $t) {
    error_log("Erro geral: " . $t->getMessage());
    send_error('Erro interno no servidor.', 500);
}
?>