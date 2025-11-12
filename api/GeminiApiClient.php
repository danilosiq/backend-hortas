<?php

class GeminiApiClient
{
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generateContent($userPrompt)
    {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=" . $this->apiKey;

        $recipeSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'NomeDaReceita' => ['type' => 'STRING'],
                'Descricao' => ['type' => 'STRING'],
                'Ingredientes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'Instrucoes' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'TempoDePreparo' => ['type' => 'STRING'],
                'Porcoes' => ['type' => 'STRING'],
                'TabelaNutricional' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'Calorias' => ['type' => 'STRING'],
                        'Carboidratos' => ['type' => 'STRING'],
                        'Proteinas' => ['type' => 'STRING'],
                        'Gorduras' => ['type' => 'STRING']
                    ]
                ]
            ]
        ];

        $payload = json_encode([
            'contents' => [['parts' => [['text' => $userPrompt]]]],
            'generationConfig' => [
                'responseMimeType' => "application/json",
                'responseSchema' => $recipeSchema,
            ],
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['body' => $body, 'httpCode' => $httpCode];
    }
}
