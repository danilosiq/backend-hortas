<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS -  permite que o front-end acesse
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
// ✅ DEFINIÇÃO DO CONTENT-TYPE PADRÃO E INCLUSÃO DE CONEXÃO
// =====================================================
header("Content-Type: application/json; charset=UTF-8");

// Inclui a conexão com o banco de dados
try {
    include 'db_connection.php';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
    exit();
}

// =====================================================
// ✅ LÓGICA PRINCIPAL - BUSCAR ESTOQUE
// =====================================================

// Valida o token do cabeçalho
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token não fornecido ou mal formatado.']);
    exit();
}
$token = $matches[1];

try {
    // Busca o ID do produtor associado ao token
    $stmt = $conn->prepare("SELECT produtor_id_produtor FROM session WHERE jwt_token = :token AND data_expiracao > NOW()");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido ou expirado.']);
        exit();
    }
    $id_produtor = $user['produtor_id_produtor'];

    // Busca o estoque do produtor
    $stmt = $conn->prepare("
        SELECT 
            e.id_estoques,
            p.nm_produto,
            p.descricao,
            e.ds_quantidade,
            p.unidade_medida_padrao AS unidade,
            e.dt_plantio,
            e.dt_colheita
        FROM estoques AS e
        JOIN produtos AS p ON e.produto_id_produto = p.id_produto
        JOIN hortas AS h ON e.hortas_id_hortas = h.id_hortas
        WHERE h.produtor_id_produtor = :id_produtor
    ");
    $stmt->bindParam(':id_produtor', $id_produtor, PDO::PARAM_INT);
    $stmt->execute();

    $estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retorna o resultado
    http_response_code(200);
    echo json_encode($estoque);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no servidor: ' . $e->getMessage()]);
}
