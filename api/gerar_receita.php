<?php

// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400"); // Cache por 1 dia

// =====================================================
// ✅ TRATAMENTO DE REQUISIÇÕES OPTIONS (pre-flight)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit();
}

// =====================================================
// ✅ DEFINIÇÃO DO CONTENT-TYPE PADRÃO
// =====================================================
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// ✅ FUNÇÃO PARA RESPOSTA PADRONIZADA
// =====================================================
function send_json_response($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function send_error_response($message, $statusCode = 500)
{
    send_json_response(['error' => $message], $statusCode);
}

// =====================================================
// ✅ VALIDAÇÃO DO TOKEN JWT (Authorization Header)
// =====================================================
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
if (!$authHeader) {
    send_error_response("Token não fornecido.", 401);
}

// Extrai o token do cabeçalho "Bearer <token>"
$tokenParts = explode(' ', $authHeader);
if (count($tokenParts) !== 2 || strtolower($tokenParts[0]) !== 'bearer') {
    send_error_response("Formato de token inválido.", 401);
}
$jwt = $tokenParts[1];


// =====================================================
// ✅ INCLUSÃO E VALIDAÇÃO DA CONEXÃO PDO
// =====================================================
try {
    include 'db_connection.php'; // arquivo com $conn (PDO)
} catch (PDOException $e) {
    send_error_response("Falha na conexão com o banco de dados.");
}

// =====================================================
// ✅ VERIFICAÇÃO DA SESSÃO E ID DO PRODUTOR
// =====================================================
try {
    $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :token AND data_expiracao > NOW()");
    $stmt->bindParam(':token', $jwt);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send_error_response("Token inválido ou expirado.", 401);
    }

    $id_produtor = $user['produtor_id_produtor'];

} catch (PDOException $e) {
    send_error_response("Erro ao validar sessão: " . $e->getMessage());
}

// =====================================================
// ✅ LÓGICA PRINCIPAL: BUSCAR ITENS E CHAMAR API EXTERNA
// =====================================================
try {
    // 1. Buscar itens em estoque do produtor
    $stmt = $conn->prepare("
        SELECT p.nm_produto
        FROM estoques e
        JOIN produtos p ON e.produto_id_produto = p.id_produto
        JOIN hortas h ON e.hortas_id_hortas = h.id_hortas
        WHERE h.produtor_id_produtor = :id_produtor AND e.ds_quantidade > 0
    ");
    $stmt->bindParam(':id_produtor', $id_produtor);
    $stmt->execute();
    $itens_estoque = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($itens_estoque)) {
        send_json_response(['message' => 'Nenhum item em estoque para gerar receita.']);
    }

    // 2. Preparar chamada para a API do Gemini
    $chave_gemini = getenv('chave_gemini');
    if (!$chave_gemini) {
        send_error_response("Chave da API do Gemini não configurada no servidor.");
    }

    $prompt = "Crie uma receita criativa usando apenas os seguintes ingredientes: " . implode(', ', $itens_estoque) . ". A resposta deve ser um JSON com os campos 'nome_receita', 'ingredientes' (array de strings) e 'modo_preparo' (texto).";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $chave_gemini);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        send_error_response("Erro ao chamar a API de receitas. Código: " . $http_code, $http_code);
    }

    // 3. Extrair e retornar a resposta da API
    $response_data = json_decode($api_response, true);
    $recipe_text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Limpa a resposta para garantir que é um JSON válido
    $json_cleaned = trim(str_replace(['```json', '```'], '', $recipe_text));

    $recipe_json = json_decode($json_cleaned, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error_response("A API de receitas retornou um JSON inválido.");
    }

    send_json_response($recipe_json);

} catch (PDOException $e) {
    send_error_response("Erro de banco de dados ao buscar estoque: " . $e->getMessage());
} catch (Exception $e) {
    send_error_response("Erro inesperado: " . $e->getMessage());
}
