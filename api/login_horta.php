<?php
// =====================================================
// ✅ CORS — SEMPRE retorna 200 e resolve o preflight
// =====================================================

// Preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: *");
    header("Access-Control-Allow-Credentials: true");
    header("Content-Type: application/json; charset=UTF-8");

    http_response_code(200);
    echo json_encode(["cors" => "ok"]);
    exit;
}

// Headers globais para todas as requisições reais
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// 🔧 Função padrão de resposta (sempre 200)
// =====================================================
function send_response($success, $msg, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        "status" => $success ? "sucesso" : "erro",
        "mensagem" => $msg
    ], $extra));
    exit;
}

try {
    include "banco_mysql.php";

    $chave_secreta = $_ENV['JWT_SECRET_KEY'] ?? 'minha_chave_super_secreta_123';

    if (is_null($chave_secreta)) {
        error_log("FATAL: JWT_SECRET_KEY não definida");
        send_response(false, "Erro de configuração.");
    }

    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->email) || empty($dados->senha)) {
        send_response(false, "Email e senha são obrigatórios.");
    }

    // Buscar produtor
    $sql = "SELECT id_produtor, nome_produtor, hash_senha 
            FROM produtor 
            WHERE email_produtor = :email 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':email', htmlspecialchars(strip_tags($dados->email)));
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        send_response(false, "Credenciais inválidas.");
    }

    $linha = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($dados->senha, $linha['hash_senha'])) {
        send_response(false, "Credenciais inválidas.");
    }

    // Criar JWT manualmente
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $issuedAt = time();
    $expirationTime = $issuedAt + 3600;

    $payload = json_encode([
        'iss' => 'localhost',
        'aud' => 'localhost',
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'data' => [
            'id' => $linha['id_produtor'],
            'nome' => $linha['nome_produtor']
        ]
    ]);

    $base64UrlHeader = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $chave_secreta, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    $jwt = "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";

    // Registrar sessão
    $sqlSessao = "INSERT INTO session (jwt_token, data_criacao, data_expiracao, produtor_id_produtor)
                  VALUES (:jwt, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), :id_produtor)";
    $stmtSessao = $conn->prepare($sqlSessao);
    $stmtSessao->bindValue(':jwt', $jwt);
    $stmtSessao->bindValue(':id_produtor', $linha['id_produtor']);
    $stmtSessao->execute();

    // Resposta final
    send_response(true, "Login bem-sucedido.", [
        "id" => $linha['id_produtor'],
        "token" => $jwt,
        "expira_em" => date('Y-m-d H:i:s', $expirationTime)
    ]);

} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    send_response(false, "Erro no servidor (DB).");
} catch (Throwable $t) {
    error_log("Throwable: " . $t->getMessage());
    send_response(false, "Erro interdawdno no servidor.");
}
?>
