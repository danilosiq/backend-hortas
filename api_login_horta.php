<?php 
//define que a reposta vai ser um JSON
header("Content-Type: application/json; charset=UTF-8");
header("Acess-Control-Allow-Origin: *");
header("Acess-Control-Allow-Method: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

//usa o arquivo de conexão do DB
include "banco_mysql.php";

$dados = json_decode(file_get_contents("php://input"));
$resposta = array();

if (!empty($dados->email) && !empty($dados->senha)) {
    try {
        //Query sql compativel com postgre e mysql
        $sql = "SELECT id_hortas, nome AS nome_horta, nome_produtor, hash_senha FROM hortas WHERE email_produtor = :email LIMIT 1";
        $stmt = $conn->prepare($sql);

        $email = htmlspecialchars(strip_tags($dados->email));
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash_senha_banco = $linha['hash_senha'];

            if (password_verify($dados->senha, $hash_senha_banco)){
                http_response_code(200);
                $resposta = array (
                    "status" => "sucesso", 
                    "mensagem" => "Login bem-sucedido.", 
                    "dados_horta" => array (
                        "id" => $linha['id_hortas'],
                        "nome_horta" => $linha['nome_horta'],
                        "nome_produtor" => $linha['nome_produtor']
                    )
                );
            } else {
                http_response_code(401);
                $resposta = array ("status" => "erro", "mensagem" => "Senha incorreta.");
            }
        } else {
            http_response_code(404);
            $resposta = array("status" => "erro", "mensagem" => "Usuário não encontrado.");
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $resposta = array("status" => "erro", "mensagem" => "Erro no banco de dados.");
    }
} else {
    http_response_code(400);
    $resposta = array("status" => "erro", "mensagem" => "Email e senha são obrigatórios.");
}
echo json_encode($resposta);

?>