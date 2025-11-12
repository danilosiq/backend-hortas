<?php
require_once __DIR__ . '/cors_comum.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/MovimentacaoController.php';
require_once __DIR__ . '/validador_jwt.php';

$dados_usuario = validar_token_jwt();
$id_produtor = $dados_usuario['id_produtor'] ?? null;

$dados = json_decode(file_get_contents("php://input"), true);

$controller = new MovimentacaoController($conn);
$response = $controller->addMov($dados, $id_produtor);

http_response_code($response['statusCode']);
echo json_encode($response['body']);
