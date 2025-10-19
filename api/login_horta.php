<?php
// Define que a resposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Usa o arquivo de conexão do DB
include "banco_mysql.php";

// Inclui o autoload do Composer para carregar a biblioteca JWT
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Lembre-se de mover esta chave para uma variável de ambiente em produção!
$chave_secreta = 'sua-chave-secreta-super-longa-e-aleatoria-aqui-gerada-com-random-bytes';

$dados = json_decode(file_get_contents("php://input"));
$resposta = array();

if ($dados && !empty($dados->email) && !empty($dados->senha)) {
    try {
        // ATUALIZAÇÃO: Adicionado 'hortas_id_hortas' ao SELECT para retorná-lo no token.
        // Isso ajuda o frontend a saber a qual horta o produtor logado pertence.
        $sql = "SELECT id_produtor, nome_produtor, hash_senha, hortas_id_hortas FROM produtor WHERE email_produtor = :email LIMIT 1";
        $stmt = $conn->prepare($sql);

        $email = htmlspecialchars(strip_tags($dados->email));
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash_senha_banco = $linha['hash_senha'];

            if (password_verify($dados->senha, $hash_senha_banco)) {
                
                $issuer = 'localhost';
                $audience = 'localhost';
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
                        // ATUALIZAÇÃO: Incluído o ID da horta no payload do token.
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
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        $resposta = array("status" => "erro", "mensagem" => "Erro no servidor. Tente novamente mais tarde.");
        error_log($e->getMessage()); // Loga o erro para o desenvolvedor
    }
} else {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Email e senha são obrigatórios.");
}

echo json_encode($resposta);
?>
