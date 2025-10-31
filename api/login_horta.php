<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

// Headers globais
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// Função de erro padronizada
// =====================================================
function send_error($msg, $code = 500) {
    http_response_code($code);
    echo json_encode(["status" => "erro", "mensagem" => $msg]);
    exit;
}

$resposta = [];

try {
    include "banco_mysql.php"; // Conexão

    $chave_secreta = $_ENV['JWT_SECRET_KEY'] ?? 'minha_chave_super_secreta_123';

    if (is_null($chave_secreta)) {
        error_log("FATAL: JWT_SECRET_KEY não definida");
        send_error("Erro de configuração interna do servidor.", 500);
    }

    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->email) || empty($dados->senha)) {
        send_error("Email e senha são obrigatórios.", 400);
    }

    // --- Busca o produtor ---
    $sql = "SELECT id_produtor, nome_produtor, hash_senha 
            FROM produtor 
            WHERE email_produtor = :email 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':email', htmlspecialchars(strip_tags($dados->email)));
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        send_error("Credenciais inválidas.", 401);
    }

    $linha = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($dados->senha, $linha['hash_senha'])) {
        send_error("Credenciais inválidas.", 401);
    }

    // --- Criação do JWT manualmente ---
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $issuedAt = time();
    $expirationTime = $issuedAt + 3600; // 1 hora
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

    // --- REGISTRAR A SESSÃO NO BANCO ---
    $sqlSessao = "INSERT INTO session (jwt_token, data_criacao, data_expiracao, produtor_id_produtor)
                  VALUES (:jwt, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), :id_produtor)";
    $stmtSessao = $conn->prepare($sqlSessao);
    $stmtSessao->bindValue(':jwt', $jwt);
    $stmtSessao->bindValue(':id_produtor', $linha['id_produtor']);
    $stmtSessao->execute();

    http_response_code(200);
    echo json_encode([
        "status" => "sucesso",
        "mensagem" => "Login bem-sucedido.",
        "token" => $jwt,
        "expira_em" => date('Y-m-d H:i:s', $expirationTime)
    ]);

} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    send_error("Erro no servidor (DB).", 500);
} catch (Throwable $t) {
    error_log("Throwable: " . $t->getMessage());
    send_error("Erro interno no servidor.", 500);
}
?>