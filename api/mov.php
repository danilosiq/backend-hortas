<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { header("Access-Control-Allow-Methods: POST, OPTIONS"); header("Access-Control-Allow-Headers: Content-Type, Authorization"); exit(0); }

function send_response($status, $msg, $extra=[]) { echo json_encode(array_merge(['status'=>$status,'mensagem'=>$msg], $extra), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }

$connFile = __DIR__.'/banco_mysql.php';
if(!file_exists($connFile)) send_response('erro','Arquivo banco_mysql.php não encontrado');
include_once $connFile;
if(!isset($conn) || !($conn instanceof PDO)) send_response('erro','PDO $conn não inicializado');

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$tipo = strtolower(trim($input['tipo'] ?? ''));
$quantidade = floatval($input['quantidade'] ?? 0);
$id_produto = isset($input['id_produto']) ? intval($input['id_produto']) : null;
$nome_produto = trim($input['nome_produto'] ?? '');
$descricao_produto = trim($input['descricao_produto'] ?? '');
$unidade = trim($input['unidade'] ?? '');
$dt_plantio = trim($input['dt_plantio'] ?? '');
$dt_colheita = trim($input['dt_colheita'] ?? '');
$motivo = trim($input['motivo'] ?? '');

if(!$token) send_response('erro','Token obrigatório');
if(!in_array($tipo,['entrada','saida'])) send_response('erro','Tipo inválido');
if($quantidade <=0) send_response('erro','Quantidade deve ser maior que zero');

try {
    // autentica token
    $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token=:jwt LIMIT 1");
    $stmt->execute([':jwt'=>$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user) send_response('erro','Token inválido');
    $id_produtor = intval($user['produtor_id_produtor']);

    // busca horta
    $stmt = $conn->prepare("SELECT id_hortas FROM hortas WHERE produtor_id_produtor=:id LIMIT 1");
    $stmt->execute([':id'=>$id_produtor]);
    $horta = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$horta) send_response('erro','Produtor sem horta');
    $id_horta = intval($horta['id_hortas']);

    $conn->beginTransaction();

    // resolve produto
    if($id_produto){
        $stmt = $conn->prepare("SELECT id_produto, nm_produto FROM produtos WHERE id_produto=:id LIMIT 1");
        $stmt->execute([':id'=>$id_produto]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$prod) $id_produto = null;
        else $nome_produto = $nome_produto ?: $prod['nm_produto'];
    }
    if(!$id_produto){
        if($tipo==='saida') $conn->rollBack() && send_response('erro','Produto não existe, impossível registrar saída');
        if(strlen($nome_produto)<2) $conn->rollBack() && send_response('erro','Nome do produto inválido');
        $stmt = $conn->prepare("SELECT id_produto FROM produtos WHERE LOWER(nm_produto)=LOWER(:nome) LIMIT 1");
        $stmt->execute([':nome'=>$nome_produto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) $id_produto = intval($row['id_produto']);
        else {
            $allowed=['g','kg','ton','unidade'];
            $unit = in_array($unidade,$allowed)?$unidade:null;
            $stmt = $conn->prepare("INSERT INTO produtos (nm_produto, descricao, unidade_medida_padrao) VALUES (:nome,:desc,:unidade)");
            $stmt->execute([':nome'=>$nome_produto,':desc'=>$descricao_produto?:null,':unidade'=>$unit]);
            $id_produto = intval($conn->lastInsertId());
        }
    }

    // busca estoque
    $stmt = $conn->prepare("SELECT id_estoques, ds_quantidade FROM estoques WHERE hortas_id_hortas=:h AND produto_id_produto=:p LIMIT 1");
    $stmt->execute([':h'=>$id_horta,':p'=>$id_produto]);
    $estoque = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$estoque){
        if($tipo==='saida') $conn->rollBack() && send_response('erro','Não é possível registrar saída de produto sem estoque');
        // cria estoque inicial
        $stmt = $conn->prepare("INSERT INTO estoques (hortas_id_hortas, produto_id_produto, ds_quantidade, dt_plantio, dt_colheita) VALUES (:h,:p,:q,:plantio,:colheita)");
        $stmt->execute([':h'=>$id_horta,':p'=>$id_produto,':q'=>$quantidade,':plantio'=>$dt_plantio?:null,':colheita'=>$dt_colheita?:null]);
        $id_estoque = intval($conn->lastInsertId());
        $novaQtd = $quantidade;
    } else {
        $id_estoque = intval($estoque['id_estoques']);
        $current = floatval($estoque['ds_quantidade']);
        if($tipo==='entrada') $novaQtd = $current+$quantidade;
        else {
            if($quantidade>$current) $conn->rollBack() && send_response('erro',"Saída maior que estoque ({$current})");
            $novaQtd = $current-$quantidade;
        }
        $stmt = $conn->prepare("UPDATE estoques SET ds_quantidade=:qtd, dt_plantio=COALESCE(:plantio,dt_plantio), dt_colheita=COALESCE(:colheita,dt_colheita) WHERE id_estoques=:id");
        $stmt->execute([':qtd'=>$novaQtd,':plantio'=>$dt_plantio?:null,':colheita'=>$dt_colheita?:null,':id'=>$id_estoque]);
    }

    // registra movimentação
    $table = $tipo==='entrada'?'entradas_estoque':'saidas_estoque';
    $stmt = $conn->prepare("INSERT INTO {$table} (estoques_id_estoques, produtor_id_produtor, quantidade, motivo) VALUES (:e,:prod,:q,:mot)");
    $stmt->execute([':e'=>$id_estoque,':prod'=>$id_produtor,':q'=>$quantidade,':mot'=>$motivo?:null]);

    $conn->commit();
    send_response('sucesso','Movimentação registrada',['id_produto'=>$id_produto,'nome_produto'=>$nome_produto,'id_estoque'=>$id_estoque,'id_horta'=>$id_horta,'nova_quantidade'=>$novaQtd,'tipo'=>$tipo]);

}catch(Throwable $t){ try{$conn->rollBack();}catch(Throwable $_){} send_response('erro','Erro ao registrar movimentação: '.$t->getMessage()); }