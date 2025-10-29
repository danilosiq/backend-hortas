<?php
// =====================================================
// 🔧 CONFIGURAÇÃO DO BANCO DE DADOS
// =====================================================

$host = '34.28.39.39'; // sem :3306
$port = '3306';
$dbname = 'hortas_db';
$user = 'admin';
$pass = '12345';

// =====================================================
// 🔌 CONEXÃO PDO
// =====================================================
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
    // echo "✅ Conexão bem-sucedida!";
} catch (PDOException $e) {
    die("❌ Erro detalhado de conexão: " . $e->getMessage());
}
?>