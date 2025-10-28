<?php
// --- LÓGICA PARA ALTERNAR AMBIENTES ---

$host = '35.222.11.65:3306'; // Valor padrão

// Verifica se o script está rodando em um servidor local ou remoto
// Você pode mudar 'localhost' para o nome do seu host local se for diferente
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    
    // --- Configurações LOCAIS ---
    $host = '35.222.11.65:3306';
    $dbname = 'hortas_db'; // Nome do banco de dados local
    $user = 'paulistinha';
    $pass = 'ç123456ç';

} else {
    
    // --- Configurações REMOTAS (PREENCHA AQUI) ---
    // Substitua pelos dados do seu servidor de produção/remoto
    $host = '34.28.39.39:3306';     // Ex: sql.seudominio.com
    $dbname = 'hortas_db';     // O nome do banco no servidor remoto
    $user = 'paulistinha';        // O usuário do banco remoto
    $pass = 'ç123456ç';          // A senha do banco remoto
}

// --- CONEXÃO COM O BANCO DE DADOS (PDO) ---

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";

// Opções do PDO para melhor tratamento de erros
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna resultados como array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa "prepared statements" nativos
];

try {
    // Tenta estabelecer a conexão
    $conn = new PDO($dsn, $user, $pass, $options);

    // Se você chegou aqui, a conexão foi bem-sucedida!
    // echo "Conexão bem-sucedida!"; // Descomente para testar

} catch (PDOException $e) {
    // Se a conexão falhar, exibe o erro detalhado
    die("Erro detalhado de conexão: " . $e->getMessage());
}

// A variável $conn agora está pronta para ser usada no restante do seu código.
?>