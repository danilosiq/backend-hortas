<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../CadastroEstoqueController.php';

class CadastroEstoqueControllerTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->conn->exec("CREATE TABLE estoques (
            id_estoque INTEGER PRIMARY KEY AUTOINCREMENT,
            hortas_id_hortas INTEGER,
            produto_id_produto INTEGER,
            ds_quantiade REAL,
            dt_validade DATE,
            dt_colheita DATE,
            dt_plantio DATE
        )");
    }

    private function createController()
    {
        return new CadastroEstoqueController($this->conn);
    }

    public function testCreateEstoqueSuccess()
    {
        $dados = (object)[
            'hortas_id_hortas' => 1,
            'produto_id_produto' => 1,
            'ds_quantiade' => 10.5,
            'dt_validade' => '2025-12-31',
            'dt_colheita' => '2025-11-10',
            'dt_plantio' => '2025-01-01'
        ];

        $controller = $this->createController();
        $response = $controller->createEstoque($dados, 1);

        $this->assertEquals('sucesso', $response['status']);
    }

    public function testMissingRequiredField()
    {
        $dados = (object)[
            'hortas_id_hortas' => 1,
            'ds_quantiade' => 10.5
        ];

        $controller = $this->createController();
        $response = $controller->createEstoque($dados, 1);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals('Campos obrigatórios não preenchidos.', $response['mensagem']);
    }

    public function testMissingProdutorId()
    {
        $dados = (object)[
            'hortas_id_hortas' => 1,
            'produto_id_produto' => 1,
            'ds_quantiade' => 10.5
        ];

        $controller = $this->createController();
        $response = $controller->createEstoque($dados, null);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals('ID do produtor não fornecido.', $response['mensagem']);
    }

    public function testDatabaseError()
    {
        $this->conn->exec("DROP TABLE estoques");
        $dados = (object)[
            'hortas_id_hortas' => 1,
            'produto_id_produto' => 1,
            'ds_quantiade' => 10.5
        ];

        $controller = $this->createController();
        $response = $controller->createEstoque($dados, 1);

        $this->assertEquals('erro', $response['status']);
        $this->assertStringContainsString('no such table', $response['mensagem']);
    }

    public function testExecuteFails()
    {
        $dados = (object)[
            'hortas_id_hortas' => 1,
            'produto_id_produto' => 1,
            'ds_quantiade' => 10.5
        ];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(false);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('prepare')->willReturn($stmtMock);

        $controller = new CadastroEstoqueController($pdoMock);
        $response = $controller->createEstoque($dados, 1);

        $this->assertEquals('erro', $response['status']);
        $this->assertEquals('Não foi possível cadastrar o lote no estoque.', $response['mensagem']);
    }
}
