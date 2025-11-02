<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

include_once 'banco_mysql.php';
if (!$conn) die(json_encode(['status'=>'erro','mensagem'=>'Banco não conectado']));

$input=json_decode(file_get_contents('php://input'),true);
$token=trim($input['token']??'');
$id_produto=(int)($input['id_produto']??0);
$quantidade=(float)($input['quantidade']??0);
$motivo=trim($input['motivo']??null);

if(!$token || !$id_produto || $quantidade<=0) die(json_encode(['status'=>'erro','mensagem'=>'Dados inválidos']));

// Token
$stmt=$conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token=:t LIMIT 1");
$stmt->bindValue(':t',$token); $stmt->execute();
if($stmt->rowCount()==0) die(json_encode(['status'=>'erro','mensagem'=>'Token inválido']));
$id_produtor=(int)$stmt->fetchColumn();

// Horta
$stmt=$conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor=:id LIMIT 1");
$stmt->bindValue(':id',$id_produtor); $stmt->execute();
if($stmt->rowCount()==0) die(json_encode(['status'=>'erro','mensagem'=>'Produtor sem horta']));
$id_horta=(int)$stmt->fetchColumn();

try{
    $conn->beginTransaction();

    // Estoque
    $s=$conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas=:h AND produto_id_produto=:p LIMIT 1");
    $s->bindValue(':h',$id_horta);
    $s->bindValue(':p',$id_produto);
    $s->execute();
    if($s->rowCount()==0) { $conn->rollBack(); die(json_encode(['status'=>'erro','mensagem'=>'Produto não existe no estoque'])); }

    $row=$s->fetch(PDO::FETCH_ASSOC);
    $id_estoque=(int)$row['id_estoques'];
    $currentQty=(float)$row['ds_quantidade'];
    if($quantidade>$currentQty){ $conn->rollBack(); die(json_encode(['status'=>'erro','mensagem'=>'Saída maior que estoque'])); }

    $novaQtd=$currentQty-$quantidade;
    $upd=$conn->prepare("UPDATE estoques SET ds_quantidade=:q WHERE id_estoques=:id");
    $upd->bindValue(':q',$novaQtd);
    $upd->bindValue(':id',$id_estoque);
    $upd->execute();

    $m=$conn->prepare("INSERT INTO saidas_estoque(estoques_id_estoques,produtor_id_produtor,quantidade,motivo) VALUES(:e,:pr,:q,:m)");
    $m->bindValue(':e',$id_estoque);
    $m->bindValue(':pr',$id_produtor);
    $m->bindValue(':q',$quantidade);
    $m->bindValue(':m',$motivo?:null);
    $m->execute();

    $conn->commit();
    echo json_encode(['status'=>'sucesso','id_produto'=>$id_produto,'nova_quantidade'=>$novaQtd]);

}catch(Throwable $t){
    $conn->rollBack();
    echo json_encode(['status'=>'erro','mensagem'=>$t->getMessage()]);
}