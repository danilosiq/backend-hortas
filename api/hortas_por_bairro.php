<?php
// =====================================================
// ✅ ATIVAÇÃO DE CORS E HEADERS
// =====================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Trata requisições OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =====================================================
// ✅ FUNÇÃO DE RESPOSTA PADRONIZADA
// =====================================================
function send_json_error($message, $statusCode) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

// =====================================================
// ✅ CONEXÃO COM O BANCO
// =====================================================
try {
    include 'db_connection.php'; // define $conn
} catch (PDOException $e) {
    send_json_error("Falha na conexão com o banco de dados.", 500);
}

// =====================================================
// ✅ LÓGICA PRINCIPAL
// =====================================================

// Valida se o bairro foi fornecido
$bairro = $_GET['bairro'] ?? '';
if (empty($bairro)) {
    send_json_error("O parâmetro 'bairro' é obrigatório.", 400);
}

try {
    // Consulta SQL para buscar hortas e seus produtores
    $sql = "
        SELECT
            h.nm_horta,
            h.cidade_horta,
            h.bairro_horta,
            h.local_exato_horta,
            p.nm_produtor,
            p.email AS email_produtor
        FROM hortas h
        JOIN produtor p ON h.produtor_id_produtor = p.id_produtor
        WHERE h.bairro_horta LIKE :bairro
    ";

    $stmt = $conn->prepare($sql);
    $searchTerm = '%' . $bairro . '%';
    $stmt->bindParam(':bairro', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    $hortas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retorna os resultados
    http_response_code(200);
    echo json_encode($hortas);

} catch (PDOException $e) {
    send_json_error("Erro ao consultar o banco de dados: " . $e->getMessage(), 500);
}
