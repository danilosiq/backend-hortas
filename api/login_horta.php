<?php
// Define que a resposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- Lidar com requisição CORS pre-flight (OPTIONS) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Responde OK para o pre-flight
    exit;
}

// --- Bibliotecas e dependências ---
// Removendo o uso do autoload e Dotenv, pois não há vendor na Vercel
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;
// use Dotenv\Dotenv;

$resposta = array(); // Inicializa a resposta fora do try

try {
    // Conexão com o DB
    include "banco_mysql.php";

    // --- Chave secreta do JWT (pode vir do ambiente ou ser definida manualmente) ---
    $chave_secreta = $_ENV['JWT_SECRET_KEY'] ?? 'minha_chave_super_secreta_123';

    if (is_null($chave_secreta)) {
        error_log("FATAL: JWT_SECRET_KEY não está definida no ambiente");
        throw new \Exception("Erro de configuração interna do servidor.");
    }

    // --- Recebe os dados JSON ---
    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->email) || empty($dados->senha)) {
        http_response_code(400); // Bad Request
        $resposta = array("status" => "erro", "mensagem" => "Email e senha são obrigatórios.");
    } else {
        // --- Lógica de Banco de Dados ---
        $sql = "SELECT id_produtor, nome_produtor, hash_senha 
                FROM produtor 
                WHERE email_produtor = :email 
                LIMIT 1";
        $stmt = $conn->prepare($sql);

        $email = htmlspecialchars(strip_tags($dados->email));
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash_senha_banco = $linha['hash_senha'];

            if (password_verify($dados->senha, $hash_senha_banco)) {
                // --- Criação manual do JWT (sem Firebase/JWT) ---
                $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);

                $issuedAt = time();
                $expirationTime = $issuedAt + 3600; // expira em 1 hora
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
                $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $chave_secreta, true);
                $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

                $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

                http_response_code(200); // OK
                $resposta = array(
                    "status" => "sucesso",
                    "mensagem" => "Login bem-sucedido.",
                    "token" => $jwt
                );
            } else {
                http_response_code(401); // Unauthorized
                $resposta = array("status" => "erro", "mensagem" => "Credenciais inválidas.");
            }
        } else {
            http_response_code(401); // Unauthorized
            $resposta = array("status" => "erro", "mensagem" => "Credenciais inválidas.");
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    $resposta = array("status" => "erro", "mensagem" => "Erro no servidor (DB).");
    error_log("PDOException: " . $e->getMessage());
} catch (Throwable $t) {
    http_response_code(500);
    $resposta = array("status" => "erro", "mensagem" => "Erro interno no servidor.");
    error_log("Throwable: " . $t->getMessage() . " em " . $t->getFile() . ":" . $t->getLine());
}

echo json_encode($resposta);
?>