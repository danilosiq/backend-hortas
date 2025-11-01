<?php
// =====================================================
// ✅ CORS - sempre retorna OK
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Responde apenas com 200 e encerra
    echo json_encode(['status' => 'ok', 'mensagem' => 'Pré-voo CORS aceito.']);
    exit();
}

// =====================================================
// 🔧 Função de resposta padronizada
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    // Sempre retorna 200, mesmo em caso de erro
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
    include __DIR__ . "/banco_mysql.php";
} catch (Throwable $e) {
    send_response("erro", "Falha ao conectar ao banco de dados.");
}

// =====================================================
// 📩 Passo 2: Ler JSON recebido
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// =====================================================
// 🔍 Passo 3: Buscar sessão se existir token
// =====================================================
$jwt = isset($input['token']) ? htmlspecialchars(strip_tags($input['token'])) : null;
$dataAtual = isset($input['data_atual']) ? htmlspecialchars(strip_tags($input['data_atual'])) : date('Y-m-d H:i:s');

$id_produtor = null;
$dataExpiracao = null;

if ($jwt) {
    try {
        $sql = "SELECT data_expiracao, produtor_id_produtor 
                FROM session 
                WHERE jwt_token = :jwt
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':jwt', $jwt);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
            $dataExpiracao = $sessao['data_expiracao'];
            $id_produtor = $sessao['produtor_id_produtor'];

            // Se expirado, deleta sessão
            if (strtotime($dataAtual) > strtotime($dataExpiracao)) {
                $delete = $conn->prepare("DELETE FROM session WHERE jwt_token = :jwt");
                $delete->bindValue(':jwt', $jwt);
                $delete->execute();
                $id_produtor = null;
            }
        }
    } catch (Throwable $t) {
        // Ignora erros de banco, nunca falha
        $id_produtor = null;
    }
}

// =====================================================
// ✅ Passo 4: Retornar resposta sempre 200
// =====================================================
send_response('sucesso', 'Requisição processada.', [
    'id_produtor' => $id_produtor,
    'expira_em' => $dataExpiracao
]);
?>