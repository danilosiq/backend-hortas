<?php
// =====================================================
// ✅ BLOCO CORS
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// Função de resposta padronizada
// =====================================================
function send_response($status, $mensagem, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'mensagem' => $mensagem], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// =====================================================
// Conexão
// =====================================================
include_once 'banco_mysql.php';
if (!$conn) send_response('erro','Banco não conectado',[],500);

// =====================================================
// Leitura e validação do corpo
// =====================================================
$input = json_decode(file_get_contents('php://input'), true);

$token = trim($input['token'] ?? '');
$id_produto = (int)($input['id_produto'] ?? 0);
$nome_produto = trim($input['nome_produto'] ?? '');
$descricao = trim($input['descricao_produto'] ?? '');
$unidade = trim($input['unidade'] ?? '');
$quantidade = isset($input['quantidade']) ? (float)$input['quantidade'] : null;
$dt_plantio = trim($input['dt_plantio'] ?? null);
$dt_colheita = trim($input['dt_colheita'] ?? null);

if (!$token || !$id_produto) send_response('erro','Token ou id_produto inválido',[],400);

// =====================================================
// Valida token e obtém produtor
// =====================================================
$stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token=:t LIMIT 1");
$stmt->bindValue(':t',$token);
$stmt->execute();
if($stmt->rowCount()==0) send_response('erro','Token inválido',[],401);
$id_produtor = (int)$stmt->fetchColumn();

// =====================================================
// Obtém horta
// =====================================================
$stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor=:id LIMIT 1");
$stmt->bindValue(':id',$id_produtor);
$stmt->execute();
if($stmt->rowCount()==0) send_response('erro','Produtor sem horta',[],404);
$id_horta = (int)$stmt->fetchColumn();

// =====================================================
// Atualiza produto e estoque
// =====================================================
try{
    $conn->beginTransaction();

    // Atualiza nome/descrição/unidade na tabela produtos
    $updProd = $conn->prepare("
        UPDATE produtos 
        SET nm_produto=:nome, descricao=:desc, unidade_medida_padrao=:unidade
        WHERE id_produto=:id
    ");
    $updProd->bindValue(':nome',$nome_produto);
    $updProd->bindValue(':desc',$descricao ?: null);
    $updProd->bindValue(':unidade',$unidade ?: null);
    $updProd->bindValue(':id',$id_produto);
    $updProd->execute();

    // Atualiza estoque se quantidade/datas fornecidas
    if($quantidade !== null || $dt_plantio || $dt_colheita){
        $updEstoque = $conn->prepare("
            UPDATE estoques 
            SET ds_quantidade = COALESCE(:q, ds_quantidade),
                dt_plantio = COALESCE(:pl, dt_plantio),
                dt_colheita = COALESCE(:co, dt_colheita)
            WHERE hortas_id_hortas=:h AND produto_id_produto=:p
        ");
        $updEstoque->bindValue(':q',$quantidade);
        $updEstoque->bindValue(':pl',$dt_plantio ?: null);
        $updEstoque->bindValue(':co',$dt_colheita ?: null);
        $updEstoque->bindValue(':h',$id_horta);
        $updEstoque->bindValue(':p',$id_produto);
        $updEstoque->execute();
    }

    $conn->commit();
    send_response('sucesso','Produto atualizado com sucesso',['id_produto'=>$id_produto]);

}catch(Throwable $t){
    $conn->rollBack();
    send_response('erro','Erro ao atualizar produto: '.$t->getMessage(),[],500);
}