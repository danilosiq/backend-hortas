<?php

class CalendarioVerdeController
{
    private $geminiClient;

    public function __construct(GeminiApiClient $geminiClient)
    {
        $this->geminiClient = $geminiClient;
    }

    public function getSugestoes($cidade, $data)
    {
        if (empty($cidade)) {
            return $this->send_response(['error' => 'O campo "cidade" é obrigatório.'], 400);
        }

        if (empty($data)) {
            $data = date('d-m-Y');
        } else {
            $timestamp = strtotime(str_replace('/', '-', $data));
            if ($timestamp === false) {
                return $this->send_response(['error' => 'Formato de data inválido.'], 400);
            }
            $data = date('d-m-Y', $timestamp);
        }

        $userPrompt = "Você é um agrônomo e consultor agrícola.
Com base na cidade '$cidade' e na data '$data', sugira 3 novas culturas (frutas, legumes ou ervas) ideais para plantar agora.
Para cada cultura, explique:
1. Motivo da sazonalidade (por que é boa época);
2. Motivo de mercado (por que pode ter boa saída);
3. Dica prática de cultivo.

Responda apenas com JSON válido no formato:
{
  \"sugestoes\": [
    {
      \"produto\": \"string\",
      \"motivo_sazonalidade\": \"string\",
      \"motivo_mercado\": \"string\",
      \"dica_cultivo\": \"string\"
    }
  ]
}
Sem markdown, apenas JSON puro.";

        try {
            $response = $this->geminiClient->generateContent($userPrompt);
            return $this->send_response($response, 200);
        } catch (Exception $e) {
            return $this->send_response(['error' => 'Erro ao comunicar com a API Gemini.'], 500);
        }
    }

    private function send_response($data, $statusCode)
    {
        http_response_code($statusCode);
        return $data;
    }
}
