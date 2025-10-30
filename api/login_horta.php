<?php
// Define que a resposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- CORREÇÃO 1: Lidar com requisição CORS pre-flight (OPTIONS) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Responde OK para o pre-flight
    exit;
}
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Dotenv\Dotenv;

$resposta = array(); // Inicializa a resposta fora do try

// --- CORREÇÃO 3: Bloco try...catch genérico ---
try {
    
    // Usa o arquivo de conexão do DB
    include "banco_mysql.php";

    // Inclui o autoload do Composer para carregar a biblioteca JWT
    // require __DIR__.'/../api/vendor/autoload.php';
    
    // --- Carregamento do .env ---
    // $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    // $dotenv->load();
    $chave_secreta = $_ENV['JWT_SECRET_KEY'] ?? null;

    // --- CORREÇÃO 2: Verificar se a chave secreta foi carregada ---
    if (is_null($chave_secreta)) {
        // Loga o erro para o desenvolvedor, mas não para o usuário
        error_log("FATAL: JWT_SECRET_KEY não está definida no arquivo .env");
        // Lança uma exceção para ser pega pelo bloco catch
        throw new \Exception("Erro de configuração interna do servidor.");
    }

    // --- Processamento da Requisição ---
    $dados = json_decode(file_get_contents("php://input"));

    if (!$dados || empty($dados->email) || empty($dados->senha)) {
        http_response_code(400); // Bad Request
        $resposta = array("status" => "erro", "mensagem" => "Email e senha são obrigatórios.");
    } else {
        
        // --- Lógica de Banco de Dados ---
        $sql = "SELECT id_produtor, nome_produtor, hash_senha, hortas_id_hortas FROM produtor WHERE email_produtor = :email LIMIT 1";
        $stmt = $conn->prepare($sql);

        $email = htmlspecialchars(strip_tags($dados->email));
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash_senha_banco = $linha['hash_senha'];

            if (password_verify($dados->senha, $hash_senha_banco)) {
                
                // --- Geração do JWT ---
                $issuer = 'localhost'; // Recomendação: Mover para .env (ex: $_ENV['APP_URL'])
                $audience = 'localhost'; // Recomendação: Mover para .env
                $issuedAt = time();
                $expirationTime = $issuedAt + 3600; // Expira em 1 hora

                $payload = [
                    'iss' => $issuer,
                    'aud' => $audience,
                    'iat' => $issuedAt,
                    'exp' => $expirationTime,
                    'data' => [
                        'id' => $linha['id_produtor'],
                        'nome' => $linha['nome_produtor'],
                        'id_horta' => $linha['hortas_id_hortas']
                    ]
                ];

                $jwt = JWT::encode($payload, $chave_secreta, 'HS256');
                
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
    // --- Captura específica para erros de Banco de Dados ---
    http_response_code(500); // Internal Server Error
    $resposta = array("status" => "erro", "mensagem" => "Erro no servidor (DB). Tente novamente mais tarde.");
    error_log("PDOException: " . $e->getMessage()); // Loga o erro real para depuração

} catch (Throwable $t) {
    // --- Captura genérica para todos os outros erros (Dotenv, JWT, PHP, etc.) ---
    http_response_code(500); // Internal Server Error
    $resposta = array("status" => "erro", "mensagem" => "Erro interno no servidor.");
    // Loga a mensagem real, o arquivo e a linha para depuração
    error_log("Throwable: " . $t->getMessage() . " em " . $t->getFile() . ":" . $t->getLine());
}

// Resposta final
echo json_encode($resposta);
?>