<?php
require_once __DIR__ . '/cors_comum.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/CadastroHortaController.php';

$dados = json_decode(file_get_contents("php://input"), true);

$controller = new CadastroHortaController($conn);
$response = $controller->createHorta($dados);

echo json_encode($response);
