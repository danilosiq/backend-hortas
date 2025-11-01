<?php
// =====================================================
// ✅ CORS e Headers
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// =====================================================
// 🔧 Função de resposta padronizada
// =====================================================
function send_response($status, $mensagem, $extra = []) {
    http_response_code(200);
    echo json_encode(array_merge([
        'status' => $status,
        'mensagem' => $mensagem
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 🔑 Conectar ao banco
// =====================================================
try {
    include "banco_mysql.php"; // Certifique-se que $conn é PDO
} catch (Throwable $e) {
    send_response("erro", "Erro ao conectar ao banco de dados.");
}

// =====================================================
// 🔒 Validador JWT minimalista
// =====================================================
function validar_token_jwt() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $_POST['token'] ?? $input['token'] ?? null;

    if (!$token) return null;

    try {
        $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :jwt LIMIT 1");
        $stmt->bindValue(':jwt', $token);
        $stmt->execute();
        if ($stmt->rowCount() === 0) return null;

        $sessao = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['id_produtor' => $sessao['produtor_id_produtor']];
    } catch (Throwable $t) {
        return null;
    }
}

// =====================================================
// 📩 Receber JSON e validar campos
// =====================================================
$dados = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_response("erro", "JSON inválido.");
}

$usuario = validar_token_jwt();
$id_produtor = $usuario['id_produtor'] ?? null;
if (!$id_produtor) {
    send_response("erro", "Token inválido ou sessão não encontrada.");
}

// Campos obrigatórios
foreach (['nome_produto', 'descricao_produto', 'unidade', 'quantidade', 'tipo'] as $campo) {
    if (!isset($dados[$campo])) {
        send_response("erro", "Campo obrigatório: $campo");
    }
}

$nome_produto = trim($dados['nome_produto']);
$descricao_produto = trim($dados['descricao_produto']);
$unidade = trim($dados['unidade']); // g, kg, ton, unidade
$quantidade = (float)$dados['quantidade'];
$tipo = strtolower($dados['tipo']); // entrada ou saida
$motivo = $dados['motivo'] ?? null;

if (!in_array($tipo, ['entrada','saida'])) {
    send_response("erro", "Tipo inválido. Deve ser 'entrada' ou 'saida'.");
}

// =====================================================
// 🔍 Pegar a horta do produtor
// =====================================================
try {
    $sqlHorta = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor = :id LIMIT 1");
    $sqlHorta->bindValue(':id', $id_produtor);
    $sqlHorta->execute();
    if ($sqlHorta->rowCount() === 0) {
        send_response("erro", "Produtor não possui horta.");
    }
    $horta = $sqlHorta->fetch(PDO::FETCH_ASSOC);
    $id_horta = $horta['id_hortas'];
} catch (Throwable $t) {
    send_response("erro", "Erro ao buscar horta.");
}

// =====================================================
// 🔄 Criar ou pegar produto, atualizar/registrar estoque e movimentação
// =====================================================
try {
    $conn->beginTransaction();

    // 1️⃣ Verificar se o produto já existe
    $sqlProduto = $conn->prepare("SELECT id_produto FROM produtos WHERE nm_produto = :nome LIMIT 1");
    $sqlProduto->bindValue(':nome', $nome_produto);
    $sqlProduto->execute();

    if ($sqlProduto->rowCount() === 0) {
        // Cria produto
        $sqlInsertProduto = $conn->prepare("INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES (:nome, :descricao, :unidade)");
        $sqlInsertProduto->bindValue(':nome', $nome_produto);
        $sqlInsertProduto->bindValue(':descricao', $descricao_produto);
        $sqlInsertProduto->bindValue(':unidade', $unidade);
        $sqlInsertProduto->execute();
        $id_produto = $conn->lastInsertId();
    } else {
        $produto = $sqlProduto->fetch(PDO::FETCH_ASSOC);
        $id_produto = $produto['id_produto'];
    }

    // 2️⃣ Verificar se existe estoque da horta para o produto
    $sqlEstoque = $conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas = :id_horta AND produto_id_produto = :id_produto LIMIT 1");
    $sqlEstoque->bindValue(':id_horta', $id_horta);
    $sqlEstoque->bindValue(':id_produto', $id_produto);
    $sqlEstoque->execute();

    if ($sqlEstoque->rowCount() === 0) {
        // Cria novo estoque
        $novaQuantidade = $tipo === 'entrada' ? $quantidade : 0;
        $sqlInsertEstoque = $conn->prepare("INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantidade) VALUES (:id_horta, :id_produto, :quantidade)");
        $sqlInsertEstoque->bindValue(':id_horta', $id_horta);
        $sqlInsertEstoque->bindValue(':id_produto', $id_produto);
        $sqlInsertEstoque->bindValue(':quantidade', $novaQuantidade);
        $sqlInsertEstoque->execute();
        $id_estoque = $conn->lastInsertId();
    } else {
        // Atualiza estoque existente
        $estoque = $sqlEstoque->fetch(PDO::FETCH_ASSOC);
        $id_estoque = $estoque['id_estoques'];
        $novaQuantidade = $tipo === 'entrada' ? $estoque['ds_quantidade'] + $quantidade : max(0, $estoque['ds_quantidade'] - $quantidade);

        $sqlUpdate = $conn->prepare("UPDATE estoques SET ds_quantidade = :quantidade WHERE id_estoques = :id_estoque");
        $sqlUpdate->bindValue(':quantidade', $novaQuantidade);
        $sqlUpdate->bindValue(':id_estoque', $id_estoque);
        $sqlUpdate->execute();
    }

    // 3️⃣ Registrar movimentação
    if ($tipo === 'entrada') {
        $sqlMov = $conn->prepare("INSERT INTO entradas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    } else {
        $sqlMov = $conn->prepare("INSERT INTO saidas_estoque (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:id_estoque, :id_produtor, :quantidade, :motivo)");
    }
    $sqlMov->bindValue(':id_estoque', $id_estoque);
    $sqlMov->bindValue(':id_produtor', $id_produtor);
    $sqlMov->bindValue(':quantidade', $quantidade);
    $sqlMov->bindValue(':motivo', $motivo);
    $sqlMov->execute();

    $conn->commit();
    send_response("sucesso", "Produto/estoque/movimentação registrados com sucesso.", [
        'id_produto' => $id_produto,
        'id_estoque' => $id_estoque,
        'nova_quantidade' => $novaQuantidade
    ]);

} catch (Throwable $t) {
    $conn->rollBack();
    send_response("erro", "Erro ao registrar movimentação: " . $t->getMessage());
}
?>