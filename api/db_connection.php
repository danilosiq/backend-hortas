<?php

function connect_to_database($dsn, $user = null, $pass = null) {
    try {
        $conn = new PDO($dsn, $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        throw new PDOException("Connection failed: " . $e->getMessage());
    }
}

// Em ambiente de produção, as variáveis de ambiente seriam usadas para construir o DSN
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s',
    getenv('DB_HOST'),
    getenv('DB_PORT'),
    getenv('DB_NAME')
);

// Apenas tenta conectar se não estivermos a executar os testes
if (!getenv('PHPUNIT_RUNNING')) {
    $conn = connect_to_database($dsn, getenv('DB_USER'), getenv('DB_PASS'));
}
