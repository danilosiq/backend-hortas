<?php
require_once __DIR__ . '/cors_comum.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/CadastroEstoqueController.php';
require_once __DIR__ . '/validador_jwt.php';

$dados = json_decode(file_get_contents("php://input"));

$dados_usuario = validar_token_jwt();
$id_produtor = $dados_usuario['id_produtor'] ?? null;

$controller = new CadastroEstoqueController($conn);
$response = $controller->createEstoque($dados, $id_produtor);

echo json_encode($response);
