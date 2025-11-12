<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../GeminiApiClient.php';
require_once __DIR__ . '/../ReceitaController.php';

class ReceitaControllerTest extends TestCase
{
    private $geminiClientMock;

    protected function setUp(): void
    {
        $this->geminiClientMock = $this->createMock(GeminiApiClient::class);
    }

    private function createController()
    {
        return new ReceitaController($this->geminiClientMock);
    }

    public function testGerarReceitaSuccess()
    {
        $input = ['ingredientes' => ['Tomate', 'Queijo']];
        $expectedApiResponse = ['nome_receita' => 'Salada Caprese'];

        $this->geminiClientMock->method('generateContent')
             ->willReturn($expectedApiResponse);

        $controller = $this->createController();
        $response = $controller->gerarReceita($input);

        $this->assertEquals(200, $response['statusCode']);
        $this->assertEquals($expectedApiResponse, $response['body']);
    }

    public function testGerarReceitaWithRestrictions()
    {
        $input = [
            'ingredientes' => ['Frango', 'Arroz'],
            'restricoes' => ['Glúten']
        ];
        $expectedApiResponse = ['nome_receita' => 'Frango com Arroz (Sem Glúten)'];

        $this->geminiClientMock->method('generateContent')
             ->willReturn($expectedApiResponse);

        $controller = $this->createController();
        $response = $controller->gerarReceita($input);

        $this->assertEquals(200, $response['statusCode']);
        $this->assertEquals($expectedApiResponse, $response['body']);
    }

    public function testGerarReceitaWithIgnoredRestrictions()
    {
        $input = [
            'ingredientes' => ['Frango', 'Pão'],
            'restricoes' => ['Pão'],
            'ignorarRestricoes' => true
        ];
        $expectedApiResponse = ['nome_receita' => 'Sanduíche de Frango'];

        $this->geminiClientMock->method('generateContent')
            ->willReturn($expectedApiResponse);

        $controller = $this->createController();
        $response = $controller->gerarReceita($input);

        $this->assertEquals(200, $response['statusCode']);
        $this->assertEquals($expectedApiResponse, $response['body']);
    }

    public function testGerarReceitaNoIngredients()
    {
        $input = ['ingredientes' => []];

        $controller = $this->createController();
        $response = $controller->gerarReceita($input);

        $this->assertEquals(400, $response['statusCode']);
        $this->assertEquals(['status' => 'erro', 'mensagem' => 'Nenhum ingrediente fornecido.'], $response['body']);
    }

    public function testGerarReceitaMatchingIngredientsAndRestrictions()
    {
        $input = [
            'ingredientes' => ['Tomate', 'Queijo'],
            'restricoes' => ['Queijo']
        ];

        $controller = $this->createController();
        $response = $controller->gerarReceita($input);

        $this->assertEquals(400, $response['statusCode']);
        $this->assertEquals(['status' => 'erro', 'mensagem' => 'Ingredientes e restrições não podem ser os mesmos.'], $response['body']);
    }

    public function testApiCommunicationError()
    {
        $this->geminiClientMock->method('generateContent')
            ->will($this->throwException(new Exception('Erro de API')));

        $controller = $this->createController();
        $response = $controller->gerarReceita(['ingredientes' => ['Cebola']]);

        $this->assertEquals(500, $response['statusCode']);
        $this->assertEquals(['status' => 'erro', 'mensagem' => 'Erro ao gerar receita: Erro de API'], $response['body']);
    }
}
