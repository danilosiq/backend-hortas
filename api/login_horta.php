<?php
// Define que a resposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Lidar com requisição CORS pre-flight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Funções auxiliares para JWT
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function create_jwt($payload, $secret) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $header_encoded = base64url_encode(json_encode($header));
    $payload_encoded = base64url_encode(json_encode($payload));
    $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
    $signature_encoded = base64url_encode($signature);
    return "$header_encoded.$payload_encoded.$signature_encoded";
}

// Inicializa resposta
$resposta = array();

try {
    // Conexão com o DB
    include "banco_mysql.php";

    // Chave secreta (pode vir do .env ou definir direto aqui)
    $chave_secreta = $_ENV['JWT_SECRET_KEY'] ?? 'minha_chave_secreta';

    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->email) || empty($dados->senha)) {
        http_response_code(400);
        $resposta = ["status" => "erro", "mensagem" => "Email e senha são obrigatórios."];
    } else {
        // Busca usuário no DB
        $sql = "SELECT id_produtor, nome_produtor, hash_senha 
                FROM produtor 
                WHERE email_produtor = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':email', htmlspecialchars(strip_tags($dados->email)));
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($dados->senha, $linha['hash_senha'])) {
                // Criação do payload
                $payload = [
                    'iss' => 'localhost',
                    'aud' => 'localhost',
                    'iat' => time(),
                    'exp' => time() + 3600, // 1 hora
                    'data' => [
                        'id' => $linha['id_produtor'],
                        'nome' => $linha['nome_produtor']
                    ]
                ];

                $jwt = create_jwt($payload, $chave_secreta);

                http_response_code(200);
                $resposta = [
                    "status" => "sucesso",
                    "mensagem" => "Login bem-sucedido.",
                    "token" => $jwt
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
    $resposta = ["status" => "erro", "mensagem" => "Erro no servidor (DB). Tente novamente mais tarde."];
    error_log("PDOException: " . $e->getMessage());
} catch (Throwable $t) {
    http_response_code(500);
    $resposta = ["status" => "erro", "mensagem" => "Erro interno no servidor."];
    error_log("Throwable: " . $t->getMessage() . " em " . $t->getFile() . ":" . $t->getLine());
}

echo json_encode($resposta);
?>