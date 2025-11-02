<?php
// api/mov.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit;
}

function send_response($status,$msg,$extra=[]){
    http_response_code(200);
    echo json_encode(array_merge(['status'=>$status,'mensagem'=>$msg],$extra),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

// conexão
$conn = null;
foreach ([__DIR__.'/banco_mysql.php',__DIR__.'/../banco_mysql.php'] as $f){
    if(file_exists($f)){ include_once $f; if(isset($conn) && $conn instanceof PDO) break; }
}
if(!$conn) send_response("erro","Banco não encontrado ou \$conn não inicializado");

// lê input
$input=json_decode(file_get_contents('php://input'),true);
if(json_last_error()!==JSON_ERROR_NONE) send_response("erro","JSON inválido");

// campos
$token=trim($input['token']??'');
$tipo=strtolower(trim($input['tipo']??''));
$quantidade=floatval($input['quantidade']??0);
$motivo=trim($input['motivo']??'');
$id_produto=isset($input['id_produto'])?(int)$input['id_produto']:null;
$nome_produto=trim($input['nome_produto']??'');
$descricao_produto=trim($input['descricao_produto']??'');
$unidade=trim($input['unidade']??'');
$dt_plantio=trim($input['dt_plantio']??null);
$dt_colheita=trim($input['dt_colheita']??null);

if(!$token) send_response("erro","Token obrigatório");
if(!in_array($tipo,['entrada','saida'])) send_response("erro","Tipo inválido");
if($quantidade<=0) send_response("erro","Quantidade deve ser >0");

// valida token
$stmt=$conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token=:jwt LIMIT 1");
$stmt->bindValue(':jwt',$token);
$stmt->execute();
if($stmt->rowCount()===0) send_response("erro","Token inválido");
$id_produtor=(int)$stmt->fetch(PDO::FETCH_ASSOC)['produtor_id_produtor'];

// pega horta
$stmt=$conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor=:id LIMIT 1");
$stmt->bindValue(':id',$id_produtor,PDO::PARAM_INT);
$stmt->execute();
if($stmt->rowCount()===0) send_response("erro","Produtor sem horta");
$id_horta=(int)$stmt->fetch(PDO::FETCH_ASSOC)['id_hortas'];

try {
    $conn->beginTransaction();

    // resolve produto
    if($id_produto){
        $p=$conn->prepare("SELECT nm_produto FROM produtos WHERE id_produto=:id LIMIT 1");
        $p->bindValue(':id',$id_produto,PDO::PARAM_INT); $p->execute();
        if($p->rowCount()===0) $id_produto=null;
        else $nome_produto=$nome_produto?:$p->fetch(PDO::FETCH_ASSOC)['nm_produto'];
    }
    if(!$id_produto){
        if($tipo==='saida') $conn->rollBack() & send_response("erro","Produto não existe, não é possível saída");
        if(!$nome_produto) $conn->rollBack() & send_response("erro","Nome do produto obrigatório");
        // busca ou cria produto
        $p=$conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto)=LOWER(:nome) LIMIT 1");
        $p->bindValue(':nome',$nome_produto); $p->execute();
        if($p->rowCount()>0) $id_produto=(int)$p->fetch(PDO::FETCH_ASSOC)['id_produto'];
        else {
            $ins=$conn->prepare("INSERT INTO produtos (nm_produto,descricao,unidade_medida_padrao) VALUES (:nome,:desc,:uni)");
            $ins->bindValue(':nome',$nome_produto);
            $ins->bindValue(':desc',$descricao_produto?:null);
            $ins->bindValue(':uni',in_array($unidade,['g','kg','ton','unidade'])?$unidade:null);
            $ins->execute();
            $id_produto=(int)$conn->lastInsertId();
        }
    }

    // verifica estoque
    $s=$conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas=:hid AND produto_id_produto=:pid LIMIT 1");
    $s->bindValue(':hid',$id_horta,PDO::PARAM_INT);
    $s->bindValue(':pid',$id_produto,PDO::PARAM_INT);
    $s->execute();

    if($s->rowCount()===0){
        if($tipo==='saida') $conn->rollBack() & send_response("erro","Saída impossível, produto sem estoque");
        $ins=$conn->prepare("INSERT INTO estoques (hortas_id_hortas,produto_id_produto,ds_quantidade,dt_plantio,dt_colheita) VALUES (:hid,:pid,:qtd,:dp,:dc)");
        $ins->bindValue(':hid',$id_horta,PDO::PARAM_INT);
        $ins->bindValue(':pid',$id_produto,PDO::PARAM_INT);
        $ins->bindValue(':qtd',$quantidade);
        $ins->bindValue(':dp',$dt_plantio?:null);
        $ins->bindValue(':dc',$dt_colheita?:null);
        $ins->execute();
        $id_estoque=(int)$conn->lastInsertId();
        $novaQuantidade=$quantidade;
    } else {
        $row=$s->fetch(PDO::FETCH_ASSOC);
        $id_estoque=(int)$row['id_estoques'];
        $cur=(float)$row['ds_quantidade'];
        if($tipo==='entrada') $novaQuantidade=$cur+$quantidade;
        else {
            if($quantidade>$cur) $conn->rollBack() & send_response("erro","Saída maior que estoque ({$cur})");
            $novaQuantidade=$cur-$quantidade;
        }
        $upd=$conn->prepare("UPDATE estoques SET ds_quantidade=:q, dt_plantio=COALESCE(:dp,dt_plantio), dt_colheita=COALESCE(:dc,dt_colheita) WHERE id_estoques=:id");
        $upd->bindValue(':q',$novaQuantidade);
        $upd->bindValue(':dp',$dt_plantio?:null);
        $upd->bindValue(':dc',$dt_colheita?:null);
        $upd->bindValue(':id',$id_estoque,PDO::PARAM_INT);
        $upd->execute();
    }

    // registra movimentação
    if($tipo==='entrada'){
        $m=$conn->prepare("INSERT INTO entradas_estoque (estoques_id_estoques,produtor_id_produtor,quantidade,motivo) VALUES (:id_estoque,:id_prod,:q,:motivo)");
    } else {
        $m=$conn->prepare("INSERT INTO saidas_estoque (estoques_id_estoques,produtor_id_produtor,quantidade,motivo) VALUES (:id_estoque,:id_prod,:q,:motivo)");
    }
    $m->bindValue(':id_estoque',$id_estoque,PDO::PARAM_INT);
    $m->bindValue(':id_prod',$id_produtor,PDO::PARAM_INT);
    $m->bindValue(':q',$quantidade);
    $m->bindValue(':motivo',$motivo?:null);
    $m->execute();

    $conn->commit();
    send_response("sucesso","Movimentação registrada",[
        'id_produto'=>$id_produto,
        'nome_produto'=>$nome_produto,
        'id_estoque'=>$id_estoque,
        'id_horta'=>$id_horta,
        'nova_quantidade'=>$novaQuantidade,
        'tipo'=>$tipo
    ]);

}catch(Throwable $e){
    try{$conn->rollBack();}catch(Throwable $x){}
    send_response("erro","Erro ao registrar movimentação: ".$e->getMessage());
}