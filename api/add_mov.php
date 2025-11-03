<?php
// =====================================================
// ✅ CORS e Preflight (TEM que vir antes de qualquer saída!)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

// Headers globais
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// ✅ Código original começa aqui
// =====================================================
include_once 'banco_mysql.php';
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status'=>'erro','mensagem'=>'Banco não conectado']);
    exit();
}

// Lê o corpo JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status'=>'erro','mensagem'=>'JSON inválido']);
    exit();
}

$token = trim($input['token'] ?? '');
$nome_produto = trim($input['nome_produto'] ?? '');
$descricao_produto = trim($input['descricao_produto'] ?? '');
$unidade = trim($input['unidade'] ?? '');
$quantidade = (float)($input['quantidade'] ?? 0);
$dt_plantio = trim($input['dt_plantio'] ?? null);
$dt_colheita = trim($input['dt_colheita'] ?? null);
$motivo = trim($input['motivo'] ?? null);

if (!$token || !$nome_produto || $quantidade <= 0) {
    http_response_code(400);
    echo json_encode(['status'=>'erro','mensagem'=>'Dados inválidos']);
    exit();
}

// Valida token
$stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :t LIMIT 1");
$stmt->bindValue(':t',$token);
$stmt->execute();
if($stmt->rowCount()==0) {
    http_response_code(401);
    echo json_encode(['status'=>'erro','mensagem'=>'Token inválido']);
    exit();
}
$id_produtor = (int)$stmt->fetchColumn();

// Horta
$stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor=:id LIMIT 1");
$stmt->bindValue(':id',$id_produtor);
$stmt->execute();
if($stmt->rowCount()==0) {
    http_response_code(404);
    echo json_encode(['status'=>'erro','mensagem'=>'Produtor sem horta']);
    exit();
}
$id_horta = (int)$stmt->fetchColumn();

try {
    $conn->beginTransaction();

    // Produto
    $p = $conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto)=LOWER(:n) LIMIT 1");
    $p->bindValue(':n',$nome_produto);
    $p->execute();
    if($p->rowCount()>0) {
        $id_produto=(int)$p->fetchColumn();
    } else {
        $ins=$conn->prepare("INSERT INTO produtos(nm_produto,descricao,unidade_medida_padrao) VALUES(:n,:d,:u)");
        $ins->bindValue(':n',$nome_produto);
        $ins->bindValue(':d',$descricao_produto ?: null);
        $ins->bindValue(':u',$unidade ?: null);
        $ins->execute();
        $id_produto=(int)$conn->lastInsertId();
    }

    // Estoque
    $s = $conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas=:h AND produto_id_produto=:p LIMIT 1");
    $s->bindValue(':h',$id_horta);
    $s->bindValue(':p',$id_produto);
    $s->execute();

    if($s->rowCount()==0){
        $ins=$conn->prepare("INSERT INTO estoques(hortas_id_hortas,produto_id_produto,ds_quantidade,dt_plantio,dt_colheita) VALUES(:h,:p,:q,:pl,:co)");
        $ins->bindValue(':h',$id_horta);
        $ins->bindValue(':p',$id_produto);
        $ins->bindValue(':q',$quantidade);
        $ins->bindValue(':pl',$dt_plantio ?: null);
        $ins->bindValue(':co',$dt_colheita ?: null);
        $ins->execute();
        $id_estoque=(int)$conn->lastInsertId();
        $novaQtd=$quantidade;
    } else {
        $row=$s->fetch(PDO::FETCH_ASSOC);
        $id_estoque=(int)$row['id_estoques'];
        $novaQtd=(float)$row['ds_quantidade']+$quantidade;
        $upd=$conn->prepare("UPDATE estoques SET ds_quantidade=:q WHERE id_estoques=:id");
        $upd->bindValue(':q',$novaQtd);
        $upd->bindValue(':id',$id_estoque);
        $upd->execute();
    }

    // Log de movimentação
    $m=$conn->prepare("INSERT INTO entradas_estoque(estoques_id_estoques,produtor_id_produtor,quantidade,motivo) VALUES(:e,:pr,:q,:m)");
    $m->bindValue(':e',$id_estoque);
    $m->bindValue(':pr',$id_produtor);
    $m->bindValue(':q',$quantidade);
    $m->bindValue(':m',$motivo ?: null);
    $m->execute();

    $conn->commit();
    echo json_encode(['status'=>'sucesso','id_produto'=>$id_produto,'nova_quantidade'=>$novaQtd]);

} catch(Throwable $t){
    $conn->rollBack();
    error_log("ERRO ADD_MOV: " . $t->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'erro','mensagem'=>$t->getMessage()]);
}