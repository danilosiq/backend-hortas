<?php

require_once 'GeminiApiClient.php';

class ReceitaController
{
    private $geminiApiClient;

    public function __construct(GeminiApiClient $client)
    {
        $this->geminiApiClient = $client;
    }

    public function gerarReceita($inputData)
    {
        if (empty($inputData) || !is_array($inputData)) {
            return $this->send_error('Dados de entrada inválidos. Esperava-se um array de itens.', 400);
        }

        $alimentosList = [];
        $restricoesList = [];
        $adicionaisList = [];

        foreach ($inputData as $item) {
            if (!empty($item['Alimentos'])) $alimentosList[] = $item['Alimentos'];
            if (!empty($item['Restrições']) && strtolower($item['Restrições']) !== 'nenhuma')
                $restricoesList[] = $item['Restrições'];
            if (!empty($item['Adicionais'])) $adicionaisList[] = $item['Adicionais'];
        }

        if (empty($alimentosList)) {
            return $this->send_error('A lista de alimentos não pode estar vazia.', 400);
        }

        $userPrompt = "Crie uma receita detalhada em português que utilize principalmente os seguintes ingredientes: " . implode(', ', $alimentosList) . ".";
        if (!empty($restricoesList))
            $userPrompt .= " Leve em consideração as seguintes restrições: " . implode(', ', array_unique($restricoesList)) . ".";
        if (!empty($adicionaisList))
            $userPrompt .= " Considere também estas notas: " . implode(', ', array_unique($adicionaisList)) . ".";
        $userPrompt .= " A resposta deve ser um JSON único e bem formatado contendo nome, descrição, ingredientes, instruções, tempo de preparo, porções e tabela nutricional estimada.";

        $apiResponse = $this->geminiApiClient->generateContent($userPrompt);

        if ($apiResponse['httpCode'] !== 200 || $apiResponse['body'] === false) {
            error_log("Erro na API Gemini: " . $apiResponse['body']);
            return $this->send_error("Erro ao comunicar com a API Gemini. Código HTTP: " . $apiResponse['httpCode'], $apiResponse['httpCode'] ?: 500);
        }

        $result = json_decode($apiResponse['body'], true);
        $jsonString = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$jsonString) {
            return $this->send_error("A resposta da API não continha o JSON esperado da receita.", 500);
        }

        header("Content-Type: application/json; charset=utf-8");
        echo $jsonString;
        return null;
    }

    public function send_error($message, $statusCode = 500) {
        http_response_code($statusCode);
        return ['error' => $message];
    }
}
