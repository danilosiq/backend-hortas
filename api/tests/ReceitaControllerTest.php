<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../ReceitaController.php';
require_once __DIR__ . '/../GeminiApiClient.php';

class ReceitaControllerTest extends TestCase
{
    private $apiClientMock;

    protected function setUp(): void
    {
        // Cria um mock para o GeminiApiClient.
        // Todos os métodos serão substituídos por stubs que retornam null por padrão.
        $this->apiClientMock = $this->createMock(GeminiApiClient::class);
    }

    private function createController()
    {
        return new ReceitaController($this->apiClientMock);
    }

    public function testGerarReceitaSuccess()
    {
        $inputData = [
            ['Alimentos' => 'Tomate', 'Adicionais' => 'Manjericão'],
            ['Alimentos' => 'Queijo'],
        ];

        $geminiResponse = ['body' => '{"candidates":[{"content":{"parts":[{"text":"{\"NomeDaReceita\":\"Salada Caprese\"}"}]}}]}', 'httpCode' => 200];

        // Configura o mock para esperar uma chamada ao método generateContent e retornar a resposta simulada
        $this->apiClientMock->expects($this->once())
                            ->method('generateContent')
                            ->willReturn($geminiResponse);

        $controller = $this->createController();

        ob_start();
        $controller->gerarReceita($inputData);
        $output = ob_get_clean();

        $this->assertJsonStringEqualsJsonString('{"NomeDaReceita":"Salada Caprese"}', $output);
    }

    public function testGerarReceitaWithRestrictions()
    {
        $inputData = [
            ['Alimentos' => 'Frango', 'Restrições' => 'Sem glúten'],
            ['Alimentos' => 'Arroz'],
        ];

        $geminiResponse = ['body' => '{"candidates":[{"content":{"parts":[{"text":"{\"NomeDaReceita\":\"Frango com Arroz Sem Glúten\"}"}]}}]}', 'httpCode' => 200];

        $this->apiClientMock->expects($this->once())
                            ->method('generateContent')
                            ->willReturn($geminiResponse);

        $controller = $this->createController();

        ob_start();
        $controller->gerarReceita($inputData);
        $output = ob_get_clean();

        $this->assertJsonStringEqualsJsonString('{"NomeDaReceita":"Frango com Arroz Sem Glúten"}', $output);
    }

    public function testGerarReceitaWithIgnoredRestrictions()
    {
        $inputData = [['Alimentos' => 'Peixe', 'Restrições' => 'nenhuma']];
        $geminiResponse = ['body' => '{"candidates":[{"content":{"parts":[{"text":"{\"NomeDaReceita\":\"Peixe\"}"}]}}]}', 'httpCode' => 200];
        $this->apiClientMock->method('generateContent')->willReturn($geminiResponse);
        $controller = $this->createController();
        ob_start();
        $controller->gerarReceita($inputData);
        $output = ob_get_clean();
        $this->assertJsonStringEqualsJsonString('{"NomeDaReceita":"Peixe"}', $output);
    }

    public function testInvalidInput()
    {
        $controller = $this->createController();
        $response = $controller->gerarReceita(null);
        $this->assertEquals(['error' => 'Dados de entrada inválidos. Esperava-se um array de itens.'], $response);
    }

    public function testEmptyFoodList()
    {
        $controller = $this->createController();
        $response = $controller->gerarReceita([['Alimentos' => '']]);
        $this->assertEquals(['error' => 'A lista de alimentos não pode estar vazia.'], $response);
    }

    public function testGeminiApiHttpError()
    {
        $geminiResponse = ['body' => 'Internal Server Error', 'httpCode' => 500];
        $this->apiClientMock->method('generateContent')->willReturn($geminiResponse);
        $controller = $this->createController();
        $response = $controller->gerarReceita([['Alimentos' => 'Tomate']]);
        $this->assertEquals(['error' => 'Erro ao comunicar com a API Gemini. Código HTTP: 500'], $response);
    }

    public function testGeminiApiCurlError()
    {
        $geminiResponse = ['body' => false, 'httpCode' => 500];
        $this->apiClientMock->method('generateContent')->willReturn($geminiResponse);
        $controller = $this->createController();
        $response = $controller->gerarReceita([['Alimentos' => 'Tomate']]);
        $this->assertEquals(['error' => 'Erro ao comunicar com a API Gemini. Código HTTP: 500'], $response);
    }

    public function testEmptyGeminiApiResponse()
    {
        $geminiResponse = ['body' => json_encode([]), 'httpCode' => 200];
        $this->apiClientMock->method('generateContent')->willReturn($geminiResponse);
        $controller = $this->createController();
        $response = $controller->gerarReceita([['Alimentos' => 'Tomate']]);
        $this->assertEquals(['error' => 'A resposta da API não continha o JSON esperado da receita.'], $response);
    }
}
