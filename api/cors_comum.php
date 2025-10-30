<?php
// =====================================================
// ✅ CORS - deve ser o primeiro bloco do arquivo
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit();
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

// =====================================================
// 🔧 Função de erro padronizada
// =====================================================
function send_error($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(['status' => 'erro', 'mensagem' => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// 🧩 Importa conexão com o banco
// ATENÇÃO: É esperado que 'banco_mysql.php' defina a variável $conn (PDO)
// =====================================================
include 'banco_mysql.php';

// =====================================================
// 📩 Validação da requisição e Leitura do JSON
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Apenas POST é aceito.', 405);
}

$dados = json_decode(file_get_contents("php://input"), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('JSON inválido enviado.', 400);
}
?>
