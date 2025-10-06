<?php
// Define que a resposta será no formato JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-control-allow-headers: content-type");

// Pega o corpo da requisição JSON enviado pelo frontend
$dados = json_decode(file_get_contents("php://input"));

$resposta = array();

// --- Validação dos dados recebidos ---
// Adicionei uma verificação para ver se $dados não é nulo
if (!$dados || empty($dados->nome_horta) || empty($dados->cnpj) || empty($dados->nome_produtor) || empty($dados->nr_cpf) || empty($dados->email_produtor) || empty($dados->senha) || empty($dados->rua) || empty($dados->bairro) || empty($dados->cep) || empty($dados->cidade) || empty($dados->estado) || empty($dados->pais)) {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Todos os campos são obrigatórios.");
    echo json_encode($resposta);
    exit;
}

// Usa o arquivo de conexão do MySQL
// Certifique-se que este arquivo cria uma instância do PDO chamada $conn
include "banco_mysql.php";

try {
    $conn->beginTransaction();

    // 1. Inserir o endereço
    $sql_endereco = "INSERT INTO endereco_hortas (nm_rua, nr_cep, nm_bairro, nm_estado, nm_cidade, nm_pais) 
                       VALUES (:rua, :cep, :bairro, :estado, :cidade, :pais)";
    $stmt_endereco = $conn->prepare($sql_endereco);
    $stmt_endereco->bindValue(':rua', htmlspecialchars(strip_tags($dados->rua)));
    $stmt_endereco->bindValue(':cep', htmlspecialchars(strip_tags($dados->cep)));
    $stmt_endereco->bindValue(':bairro', htmlspecialchars(strip_tags($dados->bairro)));
    $stmt_endereco->bindValue(':estado', htmlspecialchars(strip_tags($dados->estado)));
    $stmt_endereco->bindValue(':cidade', htmlspecialchars(strip_tags($dados->cidade)));
    $stmt_endereco->bindValue(':pais', htmlspecialchars(strip_tags($dados->pais)));
    $stmt_endereco->execute();

    // Pega o último ID inserido na conexão atual. Simples e direto.
    $id_endereco_inserido = $conn->lastInsertId();

    // 2. Inserir a horta, usando o ID do endereço
    $sql_horta = "INSERT INTO hortas (endereco_hortas_id_endereco_hortas, nr_cnpj, nome, descricao, receitas_geradas) 
                    VALUES (:id_endereco, :cnpj, :nome_horta, :descricao, 0)";
    
    $stmt_horta = $conn->prepare($sql_horta);
    $stmt_horta->bindValue(':id_endereco', $id_endereco_inserido);
    $stmt_horta->bindValue(':cnpj', htmlspecialchars(strip_tags($dados->cnpj)));
    $stmt_horta->bindValue(':nome_horta', htmlspecialchars(strip_tags($dados->nome_horta)));
    $stmt_horta->bindValue(':descricao', htmlspecialchars(strip_tags($dados->descricao ?? ''))); // Usando o operador de coalescência nula para o campo opcional 'descricao'
    $stmt_horta->execute();

    $id_horta_inserida = $conn->lastInsertId();
    
    // Prepara a senha para ser inserida
    $hash_senha = password_hash($dados->senha, PASSWORD_DEFAULT);

    // 3. Inserir o produtor, usando o ID da horta
    $sql_produtor = "INSERT INTO produtor (hortas_id_hortas, nome_produtor, nr_cpf, email_produtor, hash_senha) 
                       VALUES (:id_horta, :nome_produtor, :nr_cpf, :email_produtor, :hash_senha)";
    
    $stmt_produtor = $conn->prepare($sql_produtor);
    $stmt_produtor->bindValue(':id_horta', $id_horta_inserida);
    $stmt_produtor->bindValue(':nome_produtor', htmlspecialchars(strip_tags($dados->nome_produtor)));
    $stmt_produtor->bindValue(':nr_cpf', htmlspecialchars(strip_tags($dados->nr_cpf)));
    $stmt_produtor->bindValue(':email_produtor', htmlspecialchars(strip_tags($dados->email_produtor)));
    $stmt_produtor->bindValue(':hash_senha', $hash_senha);

    // A execução final determina se a transação será concluída ou desfeita
    if ($stmt_produtor->execute()) {
        $conn->commit();
        http_response_code(201); // Created
        $resposta = array("status" => "sucesso", "mensagem" => "Horta e produtor cadastrados com sucesso!");
    } else {
        $conn->rollBack();
        http_response_code(503); // Service Unavailable
        $resposta = array("status" => "erro", "mensagem" => "Não foi possível cadastrar a horta ou produtor.");
    }

} catch (PDOException $e) {
    $conn->rollBack();
    http_response_code(500); // Internal Server Error
    // Em produção, talvez seja melhor não expor a mensagem de erro detalhada
    $resposta = array("status" => "erro", "mensagem" => "Erro no banco de dados: " . $e->getMessage());
}

echo json_encode($resposta);
?>
