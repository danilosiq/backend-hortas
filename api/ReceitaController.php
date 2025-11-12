<?php
class ReceitaController
{
    private $geminiClient;

    public function __construct(GeminiApiClient $geminiClient)
    {
        $this->geminiClient = $geminiClient;
    }

    private function send_response($data, $statusCode)
    {
        return [
            'statusCode' => $statusCode,
            'body' => $data
        ];
    }

    public function gerarReceita(array $input)
    {
        $ingredientes = $input['ingredientes'] ?? [];
        $restricoes = $input['restricoes'] ?? [];
        $ignorarRestricoes = $input['ignorarRestricoes'] ?? false;

        if (empty($ingredientes)) {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Nenhum ingrediente fornecido.'], 400);
        }

        if (!$ignorarRestricoes && !empty(array_intersect($ingredientes, $restricoes))) {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Ingredientes e restrições não podem ser os mesmos.'], 400);
        }

        $prompt = $this->buildPrompt($ingredientes, $restricoes);

        try {
            $response = $this->geminiClient->generateContent($prompt);
            return $this->send_response($response, 200);
        } catch (Exception $e) {
            return $this->send_response(['status' => 'erro', 'mensagem' => 'Erro ao gerar receita: ' . $e->getMessage()], 500);
        }
    }

    private function buildPrompt(array $ingredientes, array $restricoes): string
    {
        $prompt = "Crie uma receita criativa usando apenas os seguintes ingredientes: " . implode(', ', $ingredientes) . ".";
        if (!empty($restricoes)) {
            $prompt .= " A receita não deve conter: " . implode(', ', $restricoes) . ".";
        }
        $prompt .= " Formate a resposta como um JSON com as chaves 'nome_receita', 'ingredientes' (uma lista de strings), e 'modo_preparo'.";
        return $prompt;
    }
}
