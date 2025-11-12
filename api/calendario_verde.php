<?php
require_once __DIR__ . '/cors_comum.php';
require_once __DIR__ . '/GeminiApiClient.php';
require_once __DIR__ . '/CalendarioVerdeController.php';

$dados = json_decode(file_get_contents("php://input"), true);
$cidade = $dados['cidade'] ?? '';
$data = $dados['data'] ?? '';

$geminiApiKey = getenv('chave_gemini');
$geminiClient = new GeminiApiClient($geminiApiKey);
$controller = new CalendarioVerdeController($geminiClient);
$response = $controller->getSugestoes($cidade, $data);

echo json_encode($response);
