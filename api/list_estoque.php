<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

include_once 'banco_mysql.php';
if (!$conn) die(json_encode(['status'=>'erro','mensagem'=>'Banco não conectado']));

$input=json_decode(file_get_contents('php://input'),true);
$id_produtor=(int)($input['id_produtor']??0);
if(!$id_produtor) die(json_encode(['status'=>'erro','mensagem'=>'ID do produtor obrigatório']));

$stmt=$conn->prepare("SELECT h.id_hortas, e.id_estoques, e.produto_id_produto, p.nm_produto, e.ds_quantidade, p.unidade_medida_padrao, e.dt_plantio, e.dt_colheita
FROM hortas h
LEFT JOIN estoques e ON h.id_hortas=e.hortas_id_hortas
LEFT JOIN produtos p ON e.produto_id_produto=p.id_produto
WHERE h.produtor_id_produtor=:id");
$stmt->bindValue(':id',$id_produtor);
$stmt->execute();

$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$horta=[];
foreach($rows as $r){
    $horta['id_horta']=$r['id_hortas'];
    $horta['estoques'][]=[
        'id_estoque'=>$r['id_estoques'],
        'id_produto'=>$r['produto_id_produto'],
        'nm_produto'=>$r['nm_produto'],
        'quantidade'=>$r['ds_quantidade'],
        'unidade'=>$r['unidade_medida_padrao'],
        'dt_plantio'=>$r['dt_plantio'],
        'dt_colheita'=>$r['dt_colheita'],
    ];
}

echo json_encode(['status'=>'sucesso','horta'=>$horta]);