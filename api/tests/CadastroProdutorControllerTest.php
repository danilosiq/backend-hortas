<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../CadastroProdutorController.php';

class CadastroProdutorControllerTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Tabela produtor
        $this->conn->exec("CREATE TABLE produtor (
            id_produtor INTEGER PRIMARY KEY AUTOINCREMENT,
            nome_produtor TEXT,
            nr_cpf TEXT UNIQUE,
            email_produtor TEXT UNIQUE,
            hash_senha TEXT,
            telefone_produtor TEXT
        )");

        // Tabela seguranca_produtor
        $this->conn->exec("CREATE TABLE seguranca_produtor (
            id_seguranca INTEGER PRIMARY KEY AUTOINCREMENT,
            produtor_id_produtor INTEGER,
            pergunta_1 TEXT,
            resposta_1_hash TEXT,
            pergunta_2 TEXT,
            resposta_2_hash TEXT
        )");
    }

    private function createController()
    {
        return new CadastroProdutorController($this->conn);
    }

    public function testCreateProdutorSuccess()
    {
        $dados = [
            'nome_produtor' => 'Produtor Teste',
            'nr_cpf' => '123.456.789-00',
            'email_produtor' => 'teste@email.com',
            'senha' => 'senha123',
            'pergunta_1' => 'Qual o nome do seu primeiro animal de estimação?',
            'resposta_1' => 'Rex',
            'pergunta_2' => 'Qual a sua cor favorita?',
            'resposta_2' => 'Azul',
            'telefone_produtor' => '11987654321'
        ];

        $controller = $this->createController();
        $response = $controller->createProdutor($dados);

        $this->assertEquals('sucesso', $response['status']);
        $this->assertEquals(1, $response['id_produtor']);
    }

    public function testMissingRequiredField()
    {
        $dados = [
            'nr_cpf' => '123.456.789-00',
            'email_produtor' => 'teste@email.com'
        ];

        $controller = $this->createController();
        $response = $controller->createProdutor($dados);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals("O campo 'nome_produtor' é obrigatório.", $response['mensagem']);
    }

    public function testDuplicateCpf()
    {
        $dados1 = [
            'nome_produtor' => 'Produtor 1',
            'nr_cpf' => '123.456.789-00',
            'email_produtor' => 'teste1@email.com',
            'senha' => 'senha123',
            'pergunta_1' => 'p1', 'resposta_1' => 'r1',
            'pergunta_2' => 'p2', 'resposta_2' => 'r2'
        ];

        $dados2 = [
            'nome_produtor' => 'Produtor 2',
            'nr_cpf' => '123.456.789-00', // CPF repetido
            'email_produtor' => 'teste2@email.com',
            'senha' => 'senha123',
            'pergunta_1' => 'p1', 'resposta_1' => 'r1',
            'pergunta_2' => 'p2', 'resposta_2' => 'r2'
        ];

        $controller = $this->createController();
        $controller->createProdutor($dados1);
        $response = $controller->createProdutor($dados2);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals('Erro: E-mail ou CPF já cadastrado.', $response['mensagem']);
    }

    public function testDatabaseError()
    {
        $this->conn->exec("DROP TABLE produtor");
        $dados = [
            'nome_produtor' => 'Produtor Teste', 'nr_cpf' => '123.456.789-00',
            'email_produtor' => 'teste@email.com', 'senha' => 'senha123',
            'pergunta_1' => 'p1', 'resposta_1' => 'r1',
            'pergunta_2' => 'p2', 'resposta_2' => 'r2'
        ];

        $controller = $this->createController();
        $response = $controller->createProdutor($dados);

        $this->assertEquals('erro', $response['status']);
        $this->assertStringContainsString('no such table', $response['mensagem']);
    }

    public function testGenericError()
    {
        $dados = [
            'nome_produtor' => 'Produtor Teste', 'nr_cpf' => '123.456.789-00',
            'email_produtor' => 'teste@email.com', 'senha' => 'senha123',
            'pergunta_1' => 'p1', 'resposta_1' => 'r1',
            'pergunta_2' => 'p2', 'resposta_2' => 'r2'
        ];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->will($this->throwException(new Exception("Generic Error")));

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('beginTransaction')->willReturn(true);
        $pdoMock->method('prepare')->willReturn($stmtMock);
        $pdoMock->method('inTransaction')->willReturn(true);
        $pdoMock->expects($this->once())->method('rollBack');

        $controller = new CadastroProdutorController($pdoMock);
        $response = $controller->createProdutor($dados);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals("Erro interno: Generic Error", $response['mensagem']);
    }
}
