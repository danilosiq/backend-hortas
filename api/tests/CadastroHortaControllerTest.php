<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../CadastroHortaController.php';

class CadastroHortaControllerTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->conn->exec("CREATE TABLE endereco_hortas (
            id_endereco_hortas INTEGER PRIMARY KEY AUTOINCREMENT,
            nm_rua TEXT,
            nr_cep TEXT,
            nm_bairro TEXT,
            nm_estado TEXT,
            nm_cidade TEXT,
            nm_pais TEXT
        )");

        $this->conn->exec("CREATE TABLE hortas (
            id_hortas INTEGER PRIMARY KEY AUTOINCREMENT,
            endereco_hortas_id_endereco_hortas INTEGER,
            produtor_id_produtor INTEGER,
            nr_cnpj TEXT UNIQUE,
            nome TEXT,
            descricao TEXT,
            visibilidade INTEGER,
            receitas_geradas INTEGER
        )");
    }

    private function createController()
    {
        return new CadastroHortaController($this->conn);
    }

    public function testCreateHortaSuccess()
    {
        $dados = [
            'nome_horta' => 'Horta Teste',
            'rua' => 'Rua Teste',
            'bairro' => 'Bairro Teste',
            'cep' => '12345-678',
            'cidade' => 'Cidade Teste',
            'estado' => 'Estado Teste',
            'pais' => 'País Teste',
            'id_produtor' => 1,
            'cnpj' => '12.345.678/0001-99'
        ];

        $controller = $this->createController();
        $response = $controller->createHorta($dados);

        $this->assertEquals('sucesso', $response['status']);
        $this->assertEquals(1, $response['id_horta']);
        $this->assertEquals(1, $response['id_endereco']);
    }

    public function testMissingRequiredField()
    {
        $dados = [
            'rua' => 'Rua Teste',
            'bairro' => 'Bairro Teste',
            'cep' => '12345-678',
            'cidade' => 'Cidade Teste',
            'estado' => 'Estado Teste',
            'pais' => 'País Teste'
        ];

        $controller = $this->createController();
        $response = $controller->createHorta($dados);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals("O campo 'nome_horta' é obrigatório.", $response['mensagem']);
    }

    public function testDatabaseError()
    {
        // Força um erro de banco de dados derrubando a tabela
        $this->conn->exec("DROP TABLE hortas");

        $dados = [
            'nome_horta' => 'Horta Teste',
            'rua' => 'Rua Teste',
            'bairro' => 'Bairro Teste',
            'cep' => '12345-678',
            'cidade' => 'Cidade Teste',
            'estado' => 'Estado Teste',
            'pais' => 'País Teste',
        ];

        $controller = $this->createController();
        $response = $controller->createHorta($dados);

        $this->assertEquals('erro', $response['status']);
        $this->assertStringContainsString('no such table', $response['mensagem']);
    }

    public function testInvalidDataType()
    {
        $dados = "invalid data";
        $controller = $this->createController();
        $response = $controller->createHorta($dados);
        $this->assertEquals('erro', $response['status']);
        $this->assertEquals("Erro interno: Dados de entrada inválidos.", $response['mensagem']);
    }

    public function testRollbackOnGenericError()
    {
        $dados = [
            'nome_horta' => 'Horta Teste',
            'rua' => 'Rua Teste',
            'bairro' => 'Bairro Teste',
            'cep' => '12345-678',
            'cidade' => 'Cidade Teste',
            'estado' => 'Estado Teste',
            'pais' => 'País Teste',
        ];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->will($this->throwException(new Exception("Generic Error")));

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('beginTransaction')->willReturn(true);
        $pdoMock->method('prepare')->willReturn($stmtMock);
        $pdoMock->method('inTransaction')->willReturn(true);
        $pdoMock->expects($this->once())->method('rollBack');

        $controller = new CadastroHortaController($pdoMock);
        $response = $controller->createHorta($dados);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals("Erro interno: Generic Error", $response['mensagem']);
    }
}
