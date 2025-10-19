<?php
// Define que a resposta será no formato JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Pega o corpo da requisição JSON enviado pelo frontend
$dados = json_decode(file_get_contents("php://input"));

$resposta = array();

// --- Validação dos dados recebidos ---
// Verifica se os campos obrigatórios foram enviados, agora usando o ID do produto
if (
    !$dados ||
    empty($dados->hortas_id_hortas) ||
    empty($dados->produto_id_produto) ||
    !isset($dados->ds_quantiade) // Quantidade pode ser 0, então usamos isset
) {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Campos obrigatórios não preenchidos: id da horta, id do produto e quantidade.");
    echo json_encode($resposta);
    exit;
}

// Usa o arquivo de conexão do MySQL
include "banco_mysql.php";

try {
    // ATUALIZAÇÃO: A query agora insere o 'produto_id_produto' em vez de 'nm_item'.
    // Os campos 'unidade_medida' e 'descricao' foram removidos, pois agora pertencem à tabela 'produtos'.
    $sql = "INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantiade, dt_validade, dt_colheita, dt_plantio) 
            VALUES (:id_horta, :id_produto, :quantidade, :dt_validade, :dt_colheita, :dt_plantio)";

    $stmt = $conn->prepare($sql);

    // Vincula os valores aos parâmetros da query
    $stmt->bindValue(':id_horta', (int)$dados->hortas_id_hortas);
    $stmt->bindValue(':id_produto', (int)$dados->produto_id_produto);
    $stmt->bindValue(':quantidade', $dados->ds_quantiade);
    
    // Para campos de data, se não forem enviados, serão inseridos como NULL no banco.
    $stmt->bindValue(':dt_validade', !empty($dados->dt_validade) ? $dados->dt_validade : null);
    $stmt->bindValue(':dt_colheita', !empty($dados->dt_colheita) ? $dados->dt_colheita : null);
    $stmt->bindValue(':dt_plantio', !empty($dados->dt_plantio) ? $dados->dt_plantio : null);

    if ($stmt->execute()) {
        http_response_code(201); // Created
        $resposta = array("status" => "sucesso", "mensagem" => "Lote de produto cadastrado no estoque com sucesso!");
    } else {
        http_response_code(503); // Service Unavailable
        $resposta = array("status" => "erro", "mensagem" => "Não foi possível cadastrar o lote no estoque.");
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $resposta = array("status" => "erro", "mensagem" => "Erro no banco de dados: " . $e->getMessage());
}

echo json_encode($resposta);
?>
