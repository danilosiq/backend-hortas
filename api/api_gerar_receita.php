<?php
require_once __DIR__ . '/cors_comum.php';
require_once __DIR__ . '/GeminiApiClient.php';
require_once __DIR__ . '/ReceitaController.php';

$dados = json_decode(file_get_contents("php://input"), true);

$geminiApiKey = getenv('chave_gemini');
$geminiClient = new GeminiApiClient($geminiApiKey);
$controller = new ReceitaController($geminiClient);
$response = $controller->gerarReceita($dados);

echo json_encode($response);
