<?php
// Define que a resposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- Lidar com requisição CORS pre-flight (OPTIONS) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$resposta = array();

try {
    include "banco_mysql.php"; // conexão

    $chave_secreta = $_ENV['JWT_SECRET_KEY'] ?? 'minha_chave_super_secreta_123';

    if (is_null($chave_secreta)) {
        error_log("FATAL: JWT_SECRET_KEY não definida");
        throw new \Exception("Erro de configuração interna do servidor.");
    }

    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->email) || empty($dados->senha)) {
        http_response_code(400);
        $resposta = ["status" => "erro", "mensagem" => "Email e senha são obrigatórios."];
    } else {
        // --- Busca o produtor ---
        $sql = "SELECT id_produtor, nome_produtor, hash_senha 
                FROM produtor 
                WHERE email_produtor = :email 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':email', htmlspecialchars(strip_tags($dados->email)));
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($dados->senha, $linha['hash_senha'])) {
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
                $resposta = [
                    "status" => "sucesso",
                    "mensagem" => "Login bem-sucedido.",
                    "token" => $jwt,
                    "expira_em" => date('Y-m-d H:i:s', $expirationTime)
                ];
            } else {
                http_response_code(401);
                $resposta = ["status" => "erro", "mensagem" => "Credenciais inválidas."];
            }
        } else {
            http_response_code(401);
            $resposta = ["status" => "erro", "mensagem" => "Credenciais inválidas."];
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    $resposta = ["status" => "erro", "mensagem" => "Erro no servidor (DB)."];
    error_log("PDOException: " . $e->getMessage());
} catch (Throwable $t) {
    http_response_code(500);
    $resposta = ["status" => "erro", "mensagem" => "Erro interno no servidor."];
    error_log("Throwable: " . $t->getMessage() . " em " . $t->getFile() . ":" . $t->getLine());
}

echo json_encode($resposta);
?>