<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400"); // Cache por 1 dia

// =====================================================
// ✅ TRATAMENTO DE REQUISIÇÕES OPTIONS (pre-flight)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Apenas retorna os headers e encerra
    http_response_code(204); // No Content
    exit();
}

// =====================================================
// ✅ DEFINIÇÃO DO CONTENT-TYPE PADRÃO
// =====================================================
// Define o Content-Type para todas as respostas,
// exceto se for sobrescrito posteriormente no script.
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// ✅ INCLUSÃO CENTRALIZADA DA CONEXÃO
// =====================================================
// ATENÇÃO: É esperado que 'db_connection.php' defina a variável $conn (PDO)
// e já trate falhas de conexão.
include 'db_connection.php';
