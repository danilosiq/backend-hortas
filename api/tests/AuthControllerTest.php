<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../AuthController.php';

class AuthControllerTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->conn->exec("CREATE TABLE session (
            jwt_token VARCHAR(255) PRIMARY KEY,
            data_expiracao DATETIME,
            produtor_id_produtor INT
        )");
    }

    private function createController()
    {
        return new AuthController($this->conn);
    }

    public function testNoToken()
    {
        $controller = $this->createController();
        $response = $controller->handleAuthRequest([]);
        $this->assertNull($response['id_produtor']);
        $this->assertEquals('sucesso', $response['status']);
    }

    public function testValidToken()
    {
        $token = 'valid-token';
        $id_produtor = 123;
        $agora = '2025-01-01 12:00:00';
        $expira = '2026-01-01 12:00:00';

        $stmt = $this->conn->prepare("INSERT INTO session (jwt_token, data_expiracao, produtor_id_produtor) VALUES (?, ?, ?)");
        $stmt->execute([$token, $expira, $id_produtor]);

        $controller = $this->createController();
        $response = $controller->handleAuthRequest(['token' => $token, 'data_atual' => $agora]);

        $this->assertEquals($id_produtor, $response['id_produtor']);
        $this->assertEquals($expira, $response['expira_em']);
    }

    public function testExpiredToken()
    {
        $token = 'expired-token';
        $id_produtor = 456;
        $agora = '2025-01-01 12:00:00';
        $expira = '2025-01-01 11:00:00';

        $stmt = $this->conn->prepare("INSERT INTO session (jwt_token, data_expiracao, produtor_id_produtor) VALUES (?, ?, ?)");
        $stmt->execute([$token, $expira, $id_produtor]);

        $controller = $this->createController();
        $response = $controller->handleAuthRequest(['token' => $token, 'data_atual' => $agora]);

        $this->assertNull($response['id_produtor']);
        $this->assertEquals($expira, $response['expira_em']);

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM session WHERE jwt_token = ?");
        $stmt->execute([$token]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testInvalidToken()
    {
        $controller = $this->createController();
        $response = $controller->handleAuthRequest(['token' => 'invalid-token']);
        $this->assertNull($response['id_produtor']);
    }

    public function testDatabaseError()
    {
        $this->conn->exec("DROP TABLE session");
        $controller = $this->createController();
        $response = $controller->handleAuthRequest(['token' => 'any-token']);
        $this->assertNull($response['id_produtor']);
    }
}
