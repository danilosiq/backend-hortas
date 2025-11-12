<?php

// Script de teste simples para a API

// Carregar variáveis de ambiente de um arquivo .env se ele existir
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Configurações do banco de dados a partir de variáveis de ambiente
$db_host = getenv('DB_HOST');
$db_port = getenv('DB_PORT');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$api_url = getenv('API_URL');

// Validar que todas as variáveis de ambiente necessárias estão definidas
if (!$db_host || !$db_port || !$db_name || !$db_user || !$db_pass || !$api_url) {
    die("Erro: Uma ou mais variáveis de ambiente necessárias (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, API_URL) não estão definidas.\n");
}

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Dados do usuário de teste
$test_email = 'test@example.com';
$test_pass = 'password';
$hashed_pass = password_hash($test_pass, PASSWORD_DEFAULT);
$id_produtor = null;

// Conectar ao banco de dados
try {
    $conn = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage() . "\n");
}

// Configurar um manipulador de encerramento para garantir a limpeza
register_shutdown_function(function() use ($conn, &$id_produtor) {
    if ($id_produtor) {
        $stmt = $conn->prepare("DELETE FROM produtor WHERE id_produtor = :id");
        $stmt->execute([':id' => $id_produtor]);
        echo "Usuário de teste removido.\n";
    }
});

try {
    // Inserir usuário de teste
    $sql = "INSERT INTO produtor (nome_produtor, email_produtor, hash_senha) VALUES (:nome, :email, :senha)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':nome' => 'Test User', ':email' => $test_email, ':senha' => $hashed_pass]);
    $id_produtor = $conn->lastInsertId();
    echo "Usuário de teste inserido com ID: $id_produtor.\n";

    // Teste de login
    $data = array('email' => $test_email, 'senha' => $test_pass);

    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        )
    );

    $context  = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);

    if ($result === FALSE) {
        echo "Teste falhou: não foi possível conectar à API.\n";
    } else {
        $response = json_decode($result);
        if (isset($response->status) && $response->status == 'sucesso' && isset($response->token)) {
            echo "Teste de login passou.\n";
        } else {
            echo "Teste de login falhou.\n";
            print_r($response);
        }
    }

} catch (Exception $e) {
    echo "Ocorreu um erro durante o teste: " . $e->getMessage() . "\n";
}

?>