<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../GeminiApiClient.php';
require_once __DIR__ . '/../CalendarioVerdeController.php';

class CalendarioVerdeControllerTest extends TestCase
{
    private $geminiClientMock;

    protected function setUp(): void
    {
        $this->geminiClientMock = $this->createMock(GeminiApiClient::class);
    }

    private function createController()
    {
        return new CalendarioVerdeController($this->geminiClientMock);
    }

    public function testGetSugestoesSuccess()
    {
        $cidade = 'São Paulo';
        $data = '10-11-2025';
        $expectedResponse = ['sugestoes' => [['produto' => 'Alface']]];

        $this->geminiClientMock->method('generateContent')
             ->willReturn($expectedResponse);

        $controller = $this->createController();
        $response = $controller->getSugestoes($cidade, $data);

        $this->assertEquals($expectedResponse, $response);
    }

    public function testMissingCidade()
    {
        $controller = $this->createController();
        $response = $controller->getSugestoes('', '10-11-2025');

        $this->assertEquals(['error' => 'O campo "cidade" é obrigatório.'], $response);
    }

    public function testInvalidData()
    {
        $controller = $this->createController();
        $response = $controller->getSugestoes('São Paulo', 'data-invalida');

        $this->assertEquals(['error' => 'Formato de data inválido.'], $response);
    }

    public function testEmptyDataUsesCurrentDate()
    {
        $cidade = 'São Paulo';
        $expectedResponse = ['sugestoes' => [['produto' => 'Tomate']]];

        $this->geminiClientMock->method('generateContent')
             ->willReturn($expectedResponse);

        $controller = $this->createController();
        $response = $controller->getSugestoes($cidade, '');

        $this->assertEquals($expectedResponse, $response);
    }

    public function testApiCommunicationError()
    {
        $this->geminiClientMock->method('generateContent')
            ->will($this->throwException(new Exception()));

        $controller = $this->createController();
        $response = $controller->getSugestoes('São Paulo', '10-11-2025');

        $this->assertEquals(['error' => 'Erro ao comunicar com a API Gemini.'], $response);
    }
}
