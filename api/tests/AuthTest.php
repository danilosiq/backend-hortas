<?php
use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/../AuthController.php';

class AuthTest extends TestCase
{
    private $conn;
    private $secret_key = 'test_secret';

    protected function setUp(): void
    {
        putenv('JWT_SECRET_KEY=' . $this->secret_key);
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cria a tabela de produtores
        $this->conn->exec("CREATE TABLE produtor (
            id_produtor INTEGER PRIMARY KEY AUTOINCREMENT,
            email_produtor TEXT NOT NULL,
            hash_senha TEXT NOT NULL
        )");

        // Cria a tabela de sessões
        $this->conn->exec("CREATE TABLE session (
            id_session INTEGER PRIMARY KEY AUTOINCREMENT,
            produtor_id_produtor INTEGER NOT NULL,
            jwt_token TEXT NOT NULL,
            data_expiracao DATETIME NOT NULL
        )");
    }

    private function createController()
    {
        return new AuthController($this->conn);
    }

    public function testSuccessfulLogin()
    {
        $email = 'teste@email.com';
        $senha = 'senha123';
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $this->conn->exec("INSERT INTO produtor (email_produtor, hash_senha) VALUES ('$email', '$hash')");

        $controller = $this->createController();
        $response = $controller->login(['email' => $email, 'senha' => $senha]);

        $this->assertEquals(200, $response['statusCode']);
        $this->assertArrayHasKey('token', $response['body']);

        // Decodifica o token para verificar o conteúdo
        $decoded = JWT::decode($response['body']['token'], new Key($this->secret_key, 'HS256'));
        $this->assertEquals($email, $decoded->data->email);
    }

    public function testInvalidCredentials()
    {
        $controller = $this->createController();
        $response = $controller->login(['email' => 'naoexiste@email.com', 'senha' => 'senhaerrada']);

        $this->assertEquals(401, $response['statusCode']);
        $this->assertEquals('Credenciais inválidas.', $response['body']['mensagem']);
    }

    public function testMissingCredentials()
    {
        $controller = $this->createController();
        $response = $controller->login([]);

        $this->assertEquals(400, $response['statusCode']);
        $this->assertEquals('Email e senha são obrigatórios.', $response['body']['mensagem']);
    }
}
