<?php

// 1. Inclui o autoload do Composer
require_once 'vendor/autoload.php';

use Gemini\Client;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;

/**
 * Função para retornar uma resposta JSON padronizada e finalizar o script.
 * @param bool $success - Indica se a operação foi bem-sucedida.
 * @param mixed $data - Os dados de resposta (mensagem de erro ou objeto da receita).
 * @param int $statusCode - O código de status HTTP.
 */
function send_json_response(bool $success, mixed $data, int $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

// --- Início da Lógica Principal ---

// 2. Recebe e valida o JSON do frontend
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_response(false, 'Erro: JSON inválido.', 400);
}

if (empty($data['items']) || !is_array($data['items'])) {
    send_json_response(false, 'Erro: A chave "items" é obrigatória e deve ser uma lista.', 400);
}

$items = $data['items'];
$tags = $data['tags'] ?? [];

// 3. Montagem do Prompt para o Gemini
$prompt = "Crie uma receita criativa e detalhada usando principalmente os seguintes ingredientes: " . implode(', ', $items) . ".";

if (!empty($tags)) {
    $prompt .= " A receita também deve atender a estas características: " . implode(', ', $tags) . ".";
}

// Prompt atualizado para pedir a tabela nutricional
$prompt .= " Preencha a estrutura JSON solicitada com o título da receita, uma breve descrição, a lista de ingredientes completa, o passo a passo do preparo e uma tabela nutricional estimada por porção.";

// 4. Definição do "molde" (Schema) da resposta JSON
$recipeSchema = new Schema(
    type: DataType::OBJECT,
    properties: [
        'title' => new Schema(type: DataType::STRING, description: 'O título da receita.'),
        'description' => new Schema(type: DataType::STRING, description: 'Uma descrição curta e apetitosa da receita.'),
        'ingredients' => new Schema(
            type: DataType::ARRAY,
            items: new Schema(type: DataType::STRING),
            description: 'Lista completa de ingredientes, incluindo quantidades.'
        ),
        'instructions' => new Schema(
            type: DataType::ARRAY,
            items: new Schema(type: DataType::STRING),
            description: 'Passo a passo numerado do modo de preparo.'
        ),
        'cook_time_minutes' => new Schema(type: DataType::INTEGER, description: 'Tempo total de preparo em minutos.'),
        // Nova seção para a tabela nutricional
        'nutrition_facts' => new Schema(
            type: DataType::OBJECT,
            description: 'Tabela nutricional estimada por porção.',
            properties: [
                'calories' => new Schema(type: DataType::STRING, description: 'Valor energético, ex: "350 kcal"'),
                'protein' => new Schema(type: DataType::STRING, description: 'Proteínas, ex: "15 g"'),
                'carbohydrates' => new Schema(type: DataType::STRING, description: 'Carboidratos, ex: "40 g"'),
                'fat' => new Schema(type: DataType::STRING, description: 'Gorduras totais, ex: "18 g"')
            ],
            required: ['calories', 'protein', 'carbohydrates', 'fat']
        )
    ],
    // Adicionado 'nutrition_facts' aos campos obrigatórios
    required: ['title', 'description', 'ingredients', 'instructions', 'cook_time_minutes', 'nutrition_facts']
);

// 5. Comunicação com a API do Gemini, forçando a resposta em JSON
try {
    $apiKey = 'SUA_API_KEY_AQUI'; // Lembre-se de usar variáveis de ambiente em produção
    $client = Gemini::client($apiKey);

    $generationConfig = new GenerationConfig(
        responseMimeType: ResponseMimeType::APPLICATION_JSON,
        responseSchema: $recipeSchema
    );
    
    // Agora a chamada inclui a configuração para forçar JSON
    $result = $client
        ->gemini('gemini-pro')
        ->withGenerationConfig($generationConfig)
        ->generateContent($prompt);

    // 6. Envia o objeto da receita (já decodificado) de volta para o frontend
    send_json_response(true, $result->json());

} catch (\Exception $e) {
    send_json_response(false, 'Erro ao se comunicar com a API do Gemini: ' . $e->getMessage(), 500);
}

?>

