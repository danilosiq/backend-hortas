<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../db_connection.php';

class DbConnectionTest extends TestCase
{
    public function testConnectionSuccess()
    {
        $conn = connect_to_database('sqlite::memory:');
        $this->assertInstanceOf(PDO::class, $conn);
    }

    public function testConnectionFailure()
    {
        $this->expectException(PDOException::class);

        // Tenta conectar a um DSN inválido para forçar uma falha
        connect_to_database('mysql:host=host-invalido;dbname=db-invalida', 'user-invalido', 'senha-invalida');
    }
}
