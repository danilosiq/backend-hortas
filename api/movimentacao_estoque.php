<?php
// Define que a resposta será no formato JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
// Permite os métodos POST (para criar) e OPTIONS (para pre-flight requests)
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Inclui as funções de movimentação que você já criou
include 'movimentacao.php'; 

// Pega o corpo da requisição JSON enviado pelo frontend
$dados = json_decode(file_get_contents("php://input"));

$resposta = array();

// --- Validação dos dados recebidos ---
if (
    !$dados ||
    empty($dados->id_estoque) ||       // ID do lote de estoque a ser alterado
    empty($dados->id_produtor) ||      // ID de quem está fazendo a alteração
    !isset($dados->quantidade) ||      // A quantidade a ser adicionada/removida
    empty($dados->tipo_movimentacao) || // 'entrada' ou 'saida'
    empty($dados->motivo)              // "Venda", "Doação", "Perda por praga", "Nova colheita", etc.
) {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "Campos obrigatórios não preenchidos: id_estoque, id_produtor, quantidade, tipo_movimentacao e motivo.");
    echo json_encode($resposta);
    exit;
}

// Garante que a quantidade seja um número positivo
$quantidade = abs(floatval($dados->quantidade));
if ($quantidade <= 0) {
    http_response_code(400); // Bad Request
    $resposta = array("status" => "erro", "mensagem" => "A quantidade deve ser maior que zero.");
    echo json_encode($resposta);
    exit;
}

// Converte os dados para variáveis mais fáceis de usar
$id_estoque = (int)$dados->id_estoque;
$id_produtor = (int)$dados->id_produtor;
$tipo = strtolower($dados->tipo_movimentacao);
$motivo = htmlspecialchars(strip_tags($dados->motivo));

try {
    // Decide qual função chamar com base no tipo de movimentação
    switch ($tipo) {
        case 'entrada':
            // Chama a função que você já criou em movimentacao.php
            if (registrarEntrada($id_estoque, $id_produtor, $quantidade, $motivo)) {
                http_response_code(200); // OK
                $resposta = array("status" => "sucesso", "mensagem" => "Entrada de estoque registrada com sucesso.");
            } else {
                throw new Exception("Não foi possível registrar a entrada.");
            }
            break;

        case 'saida':
            // Chama a função que você já criou em movimentacao.php
            if (registrarSaida($id_estoque, $id_produtor, $quantidade, $motivo)) {
                http_response_code(200); // OK
                $resposta = array("status" => "sucesso", "mensagem" => "Saída de estoque registrada com sucesso.");
            } else {
                throw new Exception("Não foi possível registrar a saída.");
            }
            break;

        default:
            http_response_code(400); // Bad Request
            $resposta = array("status" => "erro", "mensagem" => "Tipo de movimentação inválido. Use 'entrada' ou 'saida'.");
            break;
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $resposta = array("status" => "erro", "mensagem" => "Erro no servidor: " . $e->getMessage());
}

echo json_encode($resposta);
?>
