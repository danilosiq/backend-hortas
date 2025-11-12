<?php
require_once __DIR__ . '/cors_comum.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/AuthController.php';

$dados = json_decode(file_get_contents("php://input"), true);

$controller = new AuthController($conn);
$response = $controller->login($dados);

http_response_code($response['statusCode']);
echo json_encode($response['body']);
