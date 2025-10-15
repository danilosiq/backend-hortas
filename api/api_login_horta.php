<?php
// Define que a resposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Usa o arquivo de conexão do DB
include "banco_mysql.php";

// Inclui o autoload do Composer para carregar a biblioteca JWT
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key; // Importante para a versão mais recente da biblioteca

/*Lembrete do gemini
 * 
 * CORREÇÃO DE SEGURANÇA (MUITO IMPORTANTE!)
 * Nunca deixe uma chave secreta hardcoded (direto no código).
 * O ideal é armazená-la em uma variável de ambiente no seu servidor.
 * Exemplo: putenv('JWT_SECRET_KEY=SuaChaveSuperSecretaGeradaAqui');
 *
 * Para gerar uma chave segura, você pode usar o seguinte código PHP uma vez:
 * echo base64_encode(random_bytes(64));
 *
 * Por enquanto, vamos usar uma chave mais forte, mas lembre-se de movê-la para um local seguro.
*/
$chave_secreta = 'sua-chave-secreta-super-longa-e-aleatoria-aqui-gerada-com-random-bytes';

$dados = json_decode(file_get_contents("php://input"));
$resposta = array();

// Verifica se os dados foram recebidos
if ($dados && !empty($dados->email) && !empty($dados->senha)) {
    try {
        // CORREÇÃO: A coluna no banco de dados é 'nome_produtor', não 'nome'.
        $sql = "SELECT id_produtor, nome_produtor, hash_senha FROM produtor WHERE email_produtor = :email LIMIT 1";
        $stmt = $conn->prepare($sql);

        $email = htmlspecialchars(strip_tags($dados->email));
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash_senha_banco = $linha['hash_senha'];

            // Verifica se a senha enviada corresponde ao hash no banco
            if (password_verify($dados->senha, $hash_senha_banco)) {
                
                $issuer = 'localhost'; // Quem emitiu o token
                $audience = 'localhost'; // Para quem o token se destina
                $issuedAt = time(); // Quando foi emitido
                $expirationTime = $issuedAt + 3600; // Expira em 1 hora

                $payload = [
                    'iss' => $issuer,
                    'aud' => $audience,
                    'iat' => $issuedAt,
                    'exp' => $expirationTime,
                    'data' => [
                        'id' => $linha['id_produtor'],
                        'nome' => $linha['nome_produtor'] 
                    ]
                ];

                // Gera o token JWT
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
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        // Em produção, é melhor não expor a mensagem de erro detalhada do banco
        $resposta = array("status" => "erro", "mensagem" => "Erro no servidor. Tente novamente mais tarde.");
        // error_log($e->getMessage()); // Loga o erro para o desenvolvedor
    }
} else {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Email e senha são obrigatórios.");
}

echo json_encode($resposta);
?>
