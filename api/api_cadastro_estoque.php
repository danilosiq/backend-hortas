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
// Verifica se os campos obrigatórios foram enviados
if (
    !$dados ||
    empty($dados->hortas_id_hortas) ||
    empty($dados->nm_item) ||
    !isset($dados->ds_quantiade) || // Quantidade pode ser 0, então usamos isset
    empty($dados->unidade_medida)
) {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Campos obrigatórios não preenchidos: id da horta, nome do item, quantidade e unidade de medida.");
    echo json_encode($resposta);
    exit;
}

// Valida a unidade de medida para garantir que seja um dos valores permitidos no ENUM
$unidades_permitidas = ['g', 'kg', 'ton', 'unidade'];
if (!in_array($dados->unidade_medida, $unidades_permitidas)) {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Unidade de medida inválida. Use 'g', 'kg', 'ton' ou 'unidade'.");
    echo json_encode($resposta);
    exit;
}

// Usa o arquivo de conexão do MySQL
include "banco_mysql.php";

try {
    // A coluna 'total_itens' não foi incluída pois é um campo que deve ser calculado/agregado.
    // Este endpoint foca em cadastrar um item individualmente no estoque.
    $sql = "INSERT INTO estoques (hortas_id_hortas, nm_item, ds_quantiade, unidade_medida, dt_validade, dt_colheita, dt_plantio, descricao) 
            VALUES (:id_horta, :nome_item, :quantidade, :unidade, :dt_validade, :dt_colheita, :dt_plantio, :descricao)";

    $stmt = $conn->prepare($sql);

    // Vincula os valores aos parâmetros da query, tratando dados opcionais com o operador de coalescência nula (??)
    $stmt->bindValue(':id_horta', (int)$dados->hortas_id_hortas);
    $stmt->bindValue(':nome_item', htmlspecialchars(strip_tags($dados->nm_item)));
    $stmt->bindValue(':quantidade', $dados->ds_quantiade);
    $stmt->bindValue(':unidade', htmlspecialchars(strip_tags($dados->unidade_medida)));
    
    // Para campos de data, se não forem enviados, serão inseridos como NULL no banco.
    $stmt->bindValue(':dt_validade', !empty($dados->dt_validade) ? $dados->dt_validade : null);
    $stmt->bindValue(':dt_colheita', !empty($dados->dt_colheita) ? $dados->dt_colheita : null);
    $stmt->bindValue(':dt_plantio', !empty($dados->dt_plantio) ? $dados->dt_plantio : null);
    $stmt->bindValue(':descricao', htmlspecialchars(strip_tags($dados->descricao ?? '')));

    if ($stmt->execute()) {
        http_response_code(201); // Created
        $resposta = array("status" => "sucesso", "mensagem" => "Produto cadastrado no estoque com sucesso!");
    } else {
        http_response_code(503); // Service Unavailable
        $resposta = array("status" => "erro", "mensagem" => "Não foi possível cadastrar o produto no estoque.");
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    $resposta = array("status" => "erro", "mensagem" => "Erro no banco de dados: " . $e->getMessage());
}

echo json_encode($resposta);
?>
